<?php
if (!defined('ABSPATH')) exit;
final class GWR_Weekly_Emails {
    public static function schedule() {
        if (!wp_next_scheduled('gwr_weekly_emails')) wp_schedule_event(time()+HOUR_IN_SECONDS,'hourly','gwr_weekly_emails');
    }
    public static function period($now) {
        $local=(new DateTimeImmutable('@'.$now))->setTimezone(wp_timezone());
        $end=$local->modify('monday this week')->setTime(0,0);
        $start=$end->modify('-1 week');
        return ['key'=>$start->format('Y-m-d'),'start'=>$start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'end'=>$end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
    }
    public static function earned($uid,$period) {
        global $wpdb;
        // Gross action earnings, excluding gift costs, refunds and manual adjustments.
        return (int)$wpdb->get_var($wpdb->prepare('SELECT COALESCE(SUM(points),0) FROM '.GWR_Store::t('ledger')." WHERE user_id=%d AND points>0 AND kind IN ('read','share_app','share_article','listen','referral') AND created>=%s AND created<%s",$uid,$period['start'],$period['end']));
    }
    private static function signature($uid) {
        $user=get_userdata($uid);
        return hash_hmac('sha256','weekly|'.$uid.'|'.($user?$user->user_email:''),wp_salt('auth'));
    }
    public static function unsubscribe_url($uid) {
        return add_query_arg(['gwr_email_unsubscribe'=>$uid,'signature'=>self::signature($uid)],home_url('/'));
    }
    public static function unsubscribe() {
        if (!isset($_GET['gwr_email_unsubscribe'])) return;
        $uid=absint($_GET['gwr_email_unsubscribe']);
        $signature=sanitize_text_field(wp_unslash($_GET['signature']??''));
        if (!$uid || !get_userdata($uid) || !hash_equals(self::signature($uid),$signature)) wp_die('Lien invalide.', 'GOMA WEBRADIO', ['response'=>400]);
        nocache_headers(); header('Referrer-Policy: no-referrer');
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            update_user_meta($uid,'gwr_weekly_optout',1);
            wp_die('Vous ne recevrez plus les rappels hebdomadaires. Votre compte et vos points restent inchangés.', 'GOMA WEBRADIO', ['response'=>200]);
        }
        // Mail scanners may follow links: confirmation avoids unintended opt-out.
        wp_die('<p>Arrêter les rappels hebdomadaires de points ?</p><form method="post" action="'.esc_url(self::unsubscribe_url($uid)).'"><button type="submit">Confirmer le désabonnement</button></form>', 'GOMA WEBRADIO', ['response'=>200]);
    }
    public static function run($now=null) {
        global $wpdb;
        if (!get_option('gwr_weekly_enabled',1)) return;
        $now=$now??time();
        $lock='gwr_weekly_lock'; $lease=(string)(time()+600);
        if (!add_option($lock,$lease,'','no')) {
            $old=get_option($lock);
            if ((int)$old>=time()) return;
            if (!$wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND option_value=%s",$lease,$lock,$old))) return;
            wp_cache_delete($lock,'options');
        }
        try {
            $period=self::period($now); $table=GWR_Store::t('emails');
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (user_id,period_key,period_start,period_end,status,attempts,next_attempt) SELECT a.user_id,%s,%s,%s,'pending',0,0 FROM ".GWR_Store::t('accounts')." a JOIN {$wpdb->usermeta} v ON v.user_id=a.user_id AND v.meta_key='gwr_verified' AND v.meta_value='1' WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} o WHERE o.user_id=a.user_id AND o.meta_key='gwr_weekly_optout' AND o.meta_value='1')",$period['key'],$period['start'],$period['end']));
            $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status='pending' AND attempts<3 AND next_attempt<=%d AND period_end>=%s ORDER BY id LIMIT 100",time(),gmdate('Y-m-d H:i:s',time()-14*DAY_IN_SECONDS)));
            foreach($rows as $row) {
                $uid=(int)$row->user_id; $user=get_userdata($uid);
                if (!$user || !in_array('gwr_listener',$user->roles,true) || !get_user_meta($uid,'gwr_verified',true) || get_user_meta($uid,'gwr_weekly_optout',true)) {
                    $wpdb->update($table,['status'=>'skipped'],['id'=>$row->id]); continue;
                }
                $attempt=(int)$row->attempts+1;
                // Claim before sending; another worker cannot pick this recipient up.
                if (!$wpdb->query($wpdb->prepare("UPDATE $table SET attempts=%d,next_attempt=%d,status='sending' WHERE id=%d AND status='pending'",$attempt,time()+HOUR_IN_SECONDS,$row->id))) continue;
                $points=self::earned($uid,['start'=>$row->period_start,'end'=>$row->period_end]);
                $balance=(int)$wpdb->get_var($wpdb->prepare('SELECT balance FROM '.GWR_Store::t('accounts').' WHERE user_id=%d',$uid));
                $rules=GWR_Store::settings()['rules'];
                $url=GWR_Store::settings()['app_url'].'/recompenses';
                $body="Bonjour ".$user->display_name.",\n\nVotre bilan de la semaine du ".$row->period_key." : ".$points." points gagnés.\nVotre solde actuel : ".$balance." points.\n\nContinuez à lire les articles, partager et écouter GOMA WEBRADIO pour gagner encore plus de points et avancer vers vos cadeaux !\n\nArticle lu : ".$rules['read']." points\nPartage de l’application : ".$rules['share_app']." points\nPartage d’un article : ".$rules['share_article']." points\nHeure complète de radio : ".$rules['listen']." points (rien avant 60 minutes)\n\nConsultez vos points, cadeaux et limites dans l’application :\n".$url."\n\nGOMA WEBRADIO\nFasi ya ndule na infos za palais\n\nNe plus recevoir ces rappels :\n".self::unsubscribe_url($uid);
                $ok=wp_mail($user->user_email,'Votre semaine de points — GOMA WEBRADIO',$body,['Content-Type: text/plain; charset=UTF-8']);
                $wpdb->update($table,['status'=>$ok?'sent':($attempt>=3?'failed':'pending')],['id'=>$row->id]);
            }
            // Unknown outcome after a process crash is not retried: avoids duplicate emails.
            $wpdb->query($wpdb->prepare("UPDATE $table SET status='unknown' WHERE status='sending' AND next_attempt<%d",time()));
            $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE period_end<%s",gmdate('Y-m-d H:i:s',time()-90*DAY_IN_SECONDS)));
        } finally {
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",$lock,$lease));
            wp_cache_delete($lock,'options');
        }
    }
}
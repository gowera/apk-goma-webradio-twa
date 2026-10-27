<?php
if (!defined('ABSPATH')) exit;
// New-publication emails for verified app listeners, queued per post/user to prevent duplicates.
final class GWR_Newsletter {
    public static function interval($s) { $s['gwr_five_minutes']=['interval'=>300,'display'=>'Toutes les 5 minutes']; return $s; }
    public static function schedule() {
        if (!wp_next_scheduled('gwr_newsletter')) wp_schedule_event(time()+300,'gwr_five_minutes','gwr_newsletter');
    }
    public static function eligible_post($post) {
        return $post && $post->post_type==='post' && $post->post_status==='publish' && $post->post_password==='';
    }
    public static function published($new,$old,$post) {
        global $wpdb;
        if ($new!=='publish' || $old==='publish' || !get_option('gwr_newsletter_enabled',1) || !self::eligible_post($post)) return;
        if (!add_post_meta($post->ID,'gwr_newsletter_queued',time(),true)) return;
        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO ".GWR_Store::t('newsletter')." (post_id,user_id,status,attempts,next_attempt,created) SELECT %d,a.user_id,'pending',0,0,%s FROM ".GWR_Store::t('accounts')." a JOIN {$wpdb->usermeta} v ON v.user_id=a.user_id AND v.meta_key='gwr_verified' AND v.meta_value='1' WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} o WHERE o.user_id=a.user_id AND o.meta_key='gwr_newsletter_optout' AND o.meta_value='1')",$post->ID,current_time('mysql',true)));
    }
    private static function signature($uid) {
        $user=get_userdata($uid);
        return hash_hmac('sha256','newsletter|'.$uid.'|'.($user?$user->user_email:''),wp_salt('auth'));
    }
    public static function unsubscribe_url($uid) {
        return add_query_arg(['gwr_newsletter_unsubscribe'=>$uid,'signature'=>self::signature($uid)],home_url('/'));
    }
    public static function unsubscribe() {
        if (!isset($_GET['gwr_newsletter_unsubscribe'])) return;
        $uid=absint($_GET['gwr_newsletter_unsubscribe']);
        $signature=sanitize_text_field(wp_unslash($_GET['signature']??''));
        if (!$uid || !get_userdata($uid) || !hash_equals(self::signature($uid),$signature)) wp_die('Lien invalide.','GOMA WEBRADIO',['response'=>400]);
        nocache_headers(); header('Referrer-Policy: no-referrer');
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            update_user_meta($uid,'gwr_newsletter_optout',1);
            wp_die('Vous ne recevrez plus les nouvelles publications. Votre compte, vos points et vos rappels hebdomadaires restent inchangés.','GOMA WEBRADIO',['response'=>200]);
        }
        wp_die('<p>Ne plus recevoir les nouvelles publications par email ?</p><form method="post" action="'.esc_url(self::unsubscribe_url($uid)).'"><button type="submit">Confirmer le désabonnement</button></form>','GOMA WEBRADIO',['response'=>200]);
    }
    public static function article_url($post) {
        return rtrim(GWR_Store::settings()['app_url'],'/').'/articles/'.rawurlencode($post->post_name);
    }
    public static function body($post,$uid) {
        $title=esc_html(get_the_title($post));
        $image=get_the_post_thumbnail_url($post,'large');
        $excerpt=esc_html(wp_trim_words(wp_strip_all_tags(has_excerpt($post)?$post->post_excerpt:strip_shortcodes($post->post_content)),45,'…'));
        $url=esc_url(self::article_url($post));
        return '<div style="max-width:600px;margin:auto;font-family:Arial,sans-serif;color:#011b40">'
          .'<p style="font-weight:bold;color:#29abe2">GOMA WEBRADIO — Nouvelle publication</p>'
          .'<h1 style="font-size:24px;line-height:1.25">'.$title.'</h1>'
          .($image?'<img src="'.esc_url($image).'" alt="'.$title.'" style="width:100%;height:auto;border-radius:12px">':'')
          .'<p style="font-size:16px;line-height:1.6">'.$excerpt.'</p>'
          .'<p><a href="'.$url.'" style="display:inline-block;background:#29abe2;color:#ffffff;padding:12px 24px;border-radius:999px;text-decoration:none;font-weight:bold">Lire plus</a></p>'
          .'<p style="font-size:13px">Fasi ya ndule na infos za palais</p>'
          .'<p style="font-size:12px;color:#5a6b80"><a href="'.esc_url(self::unsubscribe_url($uid)).'">Ne plus recevoir les nouvelles publications</a></p></div>';
    }
    public static function run() {
        global $wpdb;
        $table=GWR_Store::t('newsletter');
        // Unknown outcome after a crash is not retried: avoids duplicate emails.
        $wpdb->query($wpdb->prepare("UPDATE $table SET status='unknown' WHERE status='sending' AND next_attempt<%d",time()));
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status='pending' AND attempts<3 AND next_attempt<=%d ORDER BY id LIMIT 50",time()));
        foreach($rows as $row) {
            $uid=(int)$row->user_id; $user=get_userdata($uid); $post=get_post((int)$row->post_id);
            if (!$user || !in_array('gwr_listener',$user->roles,true) || !get_user_meta($uid,'gwr_verified',true) || get_user_meta($uid,'gwr_newsletter_optout',true) || !self::eligible_post($post)) {
                $wpdb->update($table,['status'=>'skipped'],['id'=>$row->id]); continue;
            }
            $attempt=(int)$row->attempts+1;
            if (!$wpdb->query($wpdb->prepare("UPDATE $table SET attempts=%d,next_attempt=%d,status='sending' WHERE id=%d AND status='pending'",$attempt,time()+HOUR_IN_SECONDS,$row->id))) continue;
            $ok=wp_mail($user->user_email,wp_specialchars_decode(get_the_title($post)).' — GOMA WEBRADIO',self::body($post,$uid),['Content-Type: text/html; charset=UTF-8']);
            $wpdb->update($table,['status'=>$ok?'sent':($attempt>=3?'failed':'pending')],['id'=>$row->id]);
        }
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE created<%s",gmdate('Y-m-d H:i:s',time()-90*DAY_IN_SECONDS)));
    }
}

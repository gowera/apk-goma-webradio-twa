<?php
if (!defined('ABSPATH')) exit;
final class GWR_Admin {
    public static function menu() { add_menu_page('GOMA Récompenses','GOMA Récompenses','manage_options','goma-rewards',[self::class,'page'],'dashicons-awards',58); }
    public static function page() {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $notice='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            check_admin_referer('gwr_admin'); $action=sanitize_key($_POST['action']??'');
            if ($action==='weekly') { update_option('gwr_weekly_enabled',isset($_POST['weekly_enabled'])?1:0); $notice='Réglage des rappels enregistré.'; }
            if ($action==='settings') {
                $app=untrailingslashit(esc_url_raw(wp_unslash($_POST['app_url']??'')));
                $origins=array_filter(array_map('trim',explode("\n",wp_unslash($_POST['origins']??''))));
                $valid=wp_parse_url($app,PHP_URL_SCHEME)==='https';
                foreach($origins as $o) { $p=wp_parse_url($o); if (!$p || ($p['scheme']??'')!=='https' || empty($p['host']) || isset($p['query']) || isset($p['fragment']) || !empty($p['path']) || isset($p['user'])) $valid=false; }
                if (!$valid) $notice='Utilisez des adresses HTTPS exactes, sans chemin pour les origines.';
                else { $rules=[]; foreach(GWR_Rules::defaults() as $k=>$v) $rules[$k]=min(100000,max(0,(int)($_POST[$k]??$v))); update_option('gwr_settings',['app_url'=>$app,'origins'=>implode("\n",$origins),'rules'=>$rules]); $notice='Réglages enregistrés.'; }
            }
            if ($action==='gift') {
                $id=absint($_POST['gift_id']??0); $data=['title'=>sanitize_text_field(wp_unslash($_POST['title']??'')),'cost'=>absint($_POST['cost']??0),'stock'=>absint($_POST['stock']??0),'active'=>isset($_POST['active'])?1:0];
                if (!$data['title'] || ($data['active'] && $data['cost']<1)) $notice='Un titre et un coût positif sont nécessaires pour activer un cadeau.';
                else { $id?$wpdb->update(GWR_Store::t('gifts'),$data,['id'=>$id]):$wpdb->insert(GWR_Store::t('gifts'),$data); $notice='Cadeau enregistré.'; }
            }
            if ($action==='claim') {
                $id=absint($_POST['claim_id']??0); $status=sanitize_key($_POST['status']??'');
                $uid=(int)$wpdb->get_var($wpdb->prepare('SELECT user_id FROM '.GWR_Store::t('claims').' WHERE id=%d',$id));
                $result=GWR_Store::atomic($uid,function() use($id,$status,$wpdb,$uid) {
                    $c=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.GWR_Store::t('claims').' WHERE id=%d FOR UPDATE',$id));
                    if (!$c || $c->status!=='pending' || !in_array($status,['fulfilled','cancelled'],true)) return new WP_Error('status','Cette demande est déjà traitée.');
                    if ($status==='cancelled') { GWR_Store::credit($uid,'refund','refund:'.$id,(int)$c->cost); if ($wpdb->query($wpdb->prepare('UPDATE '.GWR_Store::t('gifts').' SET stock=stock+1 WHERE id=%d',$c->gift_id))===false) throw new Exception('stock'); }
                    if ($wpdb->update(GWR_Store::t('claims'),['status'=>$status],['id'=>$id])===false) throw new Exception('status');
                    return true;
                }); $notice=is_wp_error($result)?$result->get_error_message():'Demande traitée.';
            }
            if ($action==='adjust') {
                $uid=absint($_POST['user_id']??0); $points=(int)($_POST['points']??0); $reason=sanitize_text_field(wp_unslash($_POST['reason']??''));
                if (!$reason || abs($points)>100000) $notice='Motif requis ; ajustement limité à 100 000 points.';
                else { $result=GWR_Store::atomic($uid,function($a) use($uid,$points,$reason) {
                    if ($a->balance+$points<0) return new WP_Error('balance','Le solde ne peut pas devenir négatif.');
                    $id=wp_generate_uuid4(); $n=GWR_Store::credit($uid,'admin','admin:'.$id,$points); update_user_meta($uid,'gwr_adjustment_'.$id,['reason'=>$reason,'admin'=>get_current_user_id(),'at'=>gmdate('c')]); return $n;
                }); $notice=is_wp_error($result)?$result->get_error_message():'Ajustement enregistré.'; }
            }
        }
        $s=GWR_Store::settings();
        echo '<div class="wrap"><h1>GOMA WEBRADIO — Récompenses Application</h1>';
        if ($notice) echo '<div class="notice notice-info"><p>'.esc_html($notice).'</p></div>';
        echo '<p>Les comptes application ont le rôle « Auditeur application ». Aucun accès à l’administration. API : <code>'.esc_html(rest_url('goma-app/v1/')).'</code></p>';
        echo '<h2>Réglages et barème</h2><form method="post">'; wp_nonce_field('gwr_admin'); echo '<input type="hidden" name="action" value="settings"><table class="form-table">';
        echo '<tr><th>Adresse application</th><td><input class="regular-text" name="app_url" value="'.esc_attr($s['app_url']).'" required></td></tr>';
        echo '<tr><th>Origines autorisées</th><td><textarea name="origins" rows="5" class="large-text">'.esc_textarea($s['origins']).'</textarea><p>Une origine HTTPS exacte par ligne. Ajoutez votre URL Vercel si nécessaire. Aucun joker.</p></td></tr>';
        $labels=['read'=>'Article lu (une fois par article)','share_app'=>'Partage application (une fois par jour)','share_article'=>'Partage article (une fois par article/jour)','listen'=>'Heure complète radio (60 minutes)','referral'=>'Bonus parrainage qualifié (0 par défaut)','read_limit'=>'Maximum articles récompensés par jour','share_article_limit'=>'Maximum partages article par jour','listen_limit'=>'Maximum heures récompensées par jour'];
        foreach($labels as $k=>$label) echo '<tr><th>'.esc_html($label).'</th><td><input type="number" min="0" max="100000" name="'.esc_attr($k).'" value="'.esc_attr($s['rules'][$k]).'"></td></tr>';
        echo '</table>'; submit_button('Enregistrer'); echo '</form><p>Partages et lecture sont des actions déclaratives, pas une preuve de publication sociale ni d’attention humaine. Écoute : temps serveur et battements de 30 secondes, intervalle maximum 90 secondes, une session par compte. Pause : progression de l’heure incomplète annulée. Parrainage qualifié : email confirmé et première action récompensée ; ne prouve pas l’installation Google Play.</p>';
        echo '<h2>Emails hebdomadaires</h2><form method="post">'; wp_nonce_field('gwr_admin');
        echo '<input type="hidden" name="action" value="weekly"><label><input type="checkbox" name="weekly_enabled" '.checked(get_option('gwr_weekly_enabled',1),1,false).'> Envoyer le bilan de points de la semaine aux auditeurs avec email confirmé</label> '; submit_button('Enregistrer les rappels','secondary','submit',false); echo '</form><p>Semaine du lundi au dimanche, fuseau horaire WordPress. Envoi après la fin de semaine via WP-Cron, 100 destinataires par passage. Chaque email contient le total gagné, le solde et un lien de désabonnement. Vérifiez votre service SMTP et programmez un cron serveur pour un envoi régulier même sans visiteurs.</p>';
        echo '<p>État des envois conservés (90 jours) : ';
        foreach($wpdb->get_results('SELECT status,COUNT(*) AS total FROM '.GWR_Store::t('emails').' GROUP BY status') as $mail_status) echo esc_html($mail_status->status.' : '.$mail_status->total).' · ';
        echo '</p><p>« sent » signifie accepté par le service email, pas une preuve de réception. Échec : jusqu’à 3 tentatives espacées d’une heure. « unknown » : résultat inconnu après interruption, sans renvoi automatique.</p>';
        echo '<h2>Cadeaux</h2><p>Les cadeaux sont désactivés au départ : choisissez le coût, le stock réel et activez-les. Les demandes réservent les points et une unité ; annuler rembourse et remet en stock.</p>';
        $gifts=$wpdb->get_results('SELECT * FROM '.GWR_Store::t('gifts').' ORDER BY id');
        foreach(array_merge($gifts,[(object)['id'=>0,'title'=>'','cost'=>0,'stock'=>0,'active'=>0]]) as $g) {
            echo '<form method="post" style="margin:12px 0">'; wp_nonce_field('gwr_admin');
            echo '<input type="hidden" name="action" value="gift"><input type="hidden" name="gift_id" value="'.esc_attr($g->id).'"><label>Titre <input name="title" value="'.esc_attr($g->title).'" required></label> <label>Coût <input name="cost" type="number" min="0" value="'.esc_attr($g->cost).'"></label> <label>Stock <input name="stock" type="number" min="0" value="'.esc_attr($g->stock).'"></label> <label><input name="active" type="checkbox" '.checked($g->active,1,false).'> Actif</label> '; submit_button($g->id?'Mettre à jour':'Ajouter','secondary','submit',false); echo '</form>';
        }
        echo '<h2>Demandes de cadeaux (100 dernières)</h2><table class="widefat"><thead><tr><th>Référence</th><th>Auditeur</th><th>Cadeau</th><th>Points</th><th>État</th><th>Traitement</th></tr></thead><tbody>';
        foreach($wpdb->get_results('SELECT c.*,g.title,u.user_email FROM '.GWR_Store::t('claims').' c JOIN '.GWR_Store::t('gifts').' g ON c.gift_id=g.id JOIN '.$wpdb->users.' u ON u.ID=c.user_id ORDER BY c.id DESC LIMIT 100') as $c) {
            echo '<tr><td>#'.esc_html($c->id).'</td><td>'.esc_html($c->user_email).'</td><td>'.esc_html($c->title).'</td><td>'.esc_html($c->cost).'</td><td>'.esc_html($c->status).'</td><td>';
            if ($c->status==='pending') { echo '<form method="post">'; wp_nonce_field('gwr_admin'); echo '<input type="hidden" name="action" value="claim"><input type="hidden" name="claim_id" value="'.esc_attr($c->id).'"><select name="status"><option value="fulfilled">Remis à l’auditeur</option><option value="cancelled">Annuler et rembourser</option></select> '; submit_button('Valider','secondary','submit',false); echo '</form>'; } echo '</td></tr>';
        }
        echo '</tbody></table><h2>Auditeurs et classement (100 premiers)</h2><table class="widefat"><thead><tr><th>ID</th><th>Nom / Email (privé)</th><th>Solde</th><th>Parrainages qualifiés</th></tr></thead><tbody>';
        foreach($wpdb->get_results('SELECT a.*,u.display_name,u.user_email,(SELECT COUNT(*) FROM '.GWR_Store::t('accounts').' r WHERE r.referrer=a.user_id AND r.qualified=1) AS referrals FROM '.GWR_Store::t('accounts').' a JOIN '.$wpdb->users.' u ON u.ID=a.user_id ORDER BY referrals DESC,a.balance DESC LIMIT 100') as $a) echo '<tr><td>'.esc_html($a->user_id).'</td><td>'.esc_html($a->display_name.' / '.$a->user_email).'</td><td>'.esc_html($a->balance).'</td><td>'.esc_html($a->referrals).'</td></tr>';
        echo '</tbody></table><h2>Ajustement manuel audité</h2><form method="post">'; wp_nonce_field('gwr_admin'); echo '<input type="hidden" name="action" value="adjust"><label>ID auditeur <input type="number" name="user_id" required min="1"></label> <label>Points (+ / −) <input type="number" name="points" required></label> <label>Motif <input name="reason" required></label> '; submit_button('Ajuster','secondary','submit',false); echo '</form><h2>Journal des points (100 dernières opérations)</h2><table class="widefat"><thead><tr><th>Date UTC</th><th>Auditeur ID</th><th>Action</th><th>Points</th></tr></thead><tbody>';
        foreach($wpdb->get_results('SELECT * FROM '.GWR_Store::t('ledger').' ORDER BY id DESC LIMIT 100') as $l) echo '<tr><td>'.esc_html($l->created).'</td><td>'.esc_html($l->user_id).'</td><td>'.esc_html($l->kind).'</td><td>'.esc_html($l->points).'</td></tr>';
        echo '</tbody></table><p>Conservation : aucune suppression à la désinstallation pour éviter de perdre les soldes. Supprimer un utilisateur WordPress supprime ses données du plugin. Prévoir sauvegardes et politique de conservation.</p></div>';
    }
}

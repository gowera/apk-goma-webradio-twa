<?php
// Run only with wp eval-file on isolated WordPress.
if (!defined('WP_CLI') || !WP_CLI) exit;
global $wpdb; $seq=bin2hex(random_bytes(4)); $mails=[];
function gn_check($ok,$label) { if (!$ok) throw new Exception('FAIL '.$label); echo "PASS $label\n"; }
add_filter('pre_wp_mail',function($result,$args) use(&$mails) { $mails[]=$args; return true; },10,2);
$uid=wp_insert_user(['user_login'=>'newsletter_'.$seq,'user_email'=>'newsletter_'.$seq.'@example.test','user_pass'=>'isolated-test-password','role'=>'gwr_listener']);
update_user_meta($uid,'gwr_verified',1);
$wpdb->insert(GWR_Store::t('accounts'),['user_id'=>$uid,'code'=>GWR_Store::new_code(),'created'=>current_time('mysql',true)]);
$post=wp_insert_post(['post_title'=>'Newsletter '.$seq,'post_name'=>'newsletter-'.$seq,'post_excerpt'=>'Les nouvelles de Goma','post_content'=>'Actualité de la radio','post_status'=>'draft']);
$attachment=wp_insert_attachment(['post_title'=>'Cover','post_mime_type'=>'image/jpeg','guid'=>'https://example.test/cover.jpg']);
update_post_meta($attachment,'_wp_attached_file','cover.jpg'); set_post_thumbnail($post,$attachment);
wp_update_post(['ID'=>$post,'post_status'=>'publish']);
$table=GWR_Store::t('newsletter');
gn_check((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE post_id=%d AND user_id=%d",$post,$uid))===1,'Publication queues one email for confirmed member');
GWR_Newsletter::published('publish','draft',get_post($post));
gn_check((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE post_id=%d AND user_id=%d",$post,$uid))===1,'Republishing does not duplicate email');
GWR_Newsletter::run(); $sent=array_values(array_filter($mails,fn($m)=>$m['to']===get_userdata($uid)->user_email));
gn_check(count($sent)===1,'Cron sends publication email');
$body=$sent[0]['message'];
gn_check(str_contains($body,'Newsletter '.$seq) && str_contains($body,'Les nouvelles de Goma') && str_contains($body,'<img') && str_contains($body,'Lire plus'),'Email has title, cover, excerpt and read button');
gn_check(str_contains($body,'https://app.gomawebradio.com/articles/newsletter-'.$seq),'Read button opens app article');
GWR_Newsletter::run(); gn_check(count($mails)===1,'Subsequent cron does not resend accepted email');
$url=GWR_Newsletter::unsubscribe_url($uid); parse_str(wp_parse_url($url,PHP_URL_QUERY),$query);
gn_check(strlen($query['signature'])===64 && (int)$query['gwr_newsletter_unsubscribe']===$uid,'Unsubscribe link is signed and user-specific');
update_user_meta($uid,'gwr_newsletter_optout',1);
$post2=wp_insert_post(['post_title'=>'Optout '.$seq,'post_status'=>'publish']);
gn_check(!(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE post_id=%d AND user_id=%d",$post2,$uid)),'Opted-out member is excluded');
gn_check(wp_next_scheduled('gwr_newsletter')!==false,'Newsletter cron is scheduled');
require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($uid); wp_delete_post($post,true); wp_delete_post($post2,true); wp_delete_attachment($attachment,true);
echo "ALL NEWSLETTER CHECKS PASSED\n";
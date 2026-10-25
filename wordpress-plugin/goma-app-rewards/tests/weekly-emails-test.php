<?php
// Run only with wp eval-file on isolated WordPress; email delivery is intercepted.
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) exit;
function weekly_check($ok,$label) { if (!$ok) throw new Exception('FAIL '.$label); echo "PASS $label\n"; }
global $wpdb;
$old_timezone=get_option('timezone_string'); update_option('timezone_string','Africa/Lubumbashi');
$p=GWR_Weekly_Emails::period(strtotime('2026-10-22 13:47:00 UTC'));
weekly_check($p['key']==='2026-10-12' && $p['start']==='2026-10-11 22:00:00' && $p['end']==='2026-10-18 22:00:00','Last complete Monday-Sunday week in WordPress timezone');
$seq=bin2hex(random_bytes(4)); $users=[]; $captured=[]; $fail=false;
$filter=function($result,$args) use(&$captured,&$fail) { $captured[]=$args; return !$fail; };
add_filter('pre_wp_mail',$filter,99,2);
foreach(['verified','unverified','optout','zero'] as $kind) {
    $uid=wp_insert_user(['user_login'=>'weekly_'.$seq.'_'.$kind,'user_email'=>'weekly-'.$seq.'-'.$kind.'@example.test','user_pass'=>wp_generate_password(24),'role'=>'gwr_listener','display_name'=>'Test']);
    $users[$kind]=$uid;
    $wpdb->insert(GWR_Store::t('accounts'),['user_id'=>$uid,'code'=>bin2hex(random_bytes(8)),'balance'=>19,'created'=>current_time('mysql',true)]);
    if ($kind!=='unverified') update_user_meta($uid,'gwr_verified',1);
    if ($kind==='optout') update_user_meta($uid,'gwr_weekly_optout',1);
}
$period=GWR_Weekly_Emails::period(time()); $uid=$users['verified'];
foreach([['read',3,$period['start']],['share_app',7,$period['start']],['share_article',6,$period['start']],['listen',30,$period['start']],['gift',-20,$period['start']],['refund',20,$period['start']],['admin',100,$period['start']],['read',3,$period['end']],['read',3,gmdate('Y-m-d H:i:s',strtotime($period['start'].' UTC')-1)]] as $i=>$entry)
    $wpdb->insert(GWR_Store::t('ledger'),['user_id'=>$uid,'kind'=>$entry[0],'points'=>$entry[1],'event_key'=>'weekly-test-'.$i,'created'=>$entry[2]]);
weekly_check(GWR_Weekly_Emails::earned($uid,$period)===46,'Weekly earned points exactly 3+7+6+30; excludes spending, refunds, manual adjustment and other weeks');
$wpdb->insert(GWR_Store::t('ledger'),['user_id'=>$uid,'kind'=>'welcome','points'=>30,'event_key'=>'welcome:'.$uid,'created'=>$period['start']]);
weekly_check(GWR_Weekly_Emails::earned($uid,$period)===76,'Weekly summary includes exactly 30 welcome points');
update_option('gwr_weekly_enabled',0); GWR_Weekly_Emails::run();
weekly_check(count($captured)===0,'Global disable prevents all emails');
update_option('gwr_weekly_enabled',1); GWR_Weekly_Emails::run();
$to=array_column($captured,'to');
weekly_check(in_array(get_userdata($uid)->user_email,$to,true),'Verified account receives weekly email through wp_mail');
weekly_check(in_array(get_userdata($users['zero'])->user_email,$to,true),'Inactive verified account receives weekly reminder');
weekly_check(!in_array(get_userdata($users['unverified'])->user_email,$to,true),'Unverified account is not emailed');
weekly_check(!in_array(get_userdata($users['optout'])->user_email,$to,true),'Unsubscribed account is not emailed');
$count=count($captured); GWR_Weekly_Emails::run();
weekly_check(count($captured)===$count,'Repeat cron does not duplicate weekly emails');
$link=GWR_Weekly_Emails::unsubscribe_url($uid); parse_str(wp_parse_url($link,PHP_URL_QUERY),$query);
weekly_check((int)$query['gwr_email_unsubscribe']===$uid && strlen($query['signature'])===64,'Personal unsubscribe link is signed');
$wpdb->delete(GWR_Store::t('emails'),['user_id'=>$users['zero']]); $fail=true;
GWR_Weekly_Emails::run();
$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.GWR_Store::t('emails').' WHERE user_id=%d',$users['zero']));
weekly_check($row->status==='pending' && (int)$row->attempts===1,'Failed email queued for retry');
$count=count($captured); GWR_Weekly_Emails::run(); weekly_check(count($captured)===$count,'Retry waits an hour');
for($i=0;$i<2;$i++) { $wpdb->update(GWR_Store::t('emails'),['next_attempt'=>0],['user_id'=>$users['zero']]); GWR_Weekly_Emails::run(); }
$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.GWR_Store::t('emails').' WHERE user_id=%d',$users['zero']));
weekly_check($row->status==='failed' && (int)$row->attempts===3,'Stops after exactly three failures');
weekly_check((bool)wp_next_scheduled('gwr_weekly_emails'),'WordPress cron registered');
remove_filter('pre_wp_mail',$filter,99); require_once ABSPATH.'wp-admin/includes/user.php';
foreach($users as $id) wp_delete_user($id);
update_option('timezone_string',$old_timezone);
echo "ALL WEEKLY EMAIL CHECKS PASSED\n";
<?php
// Run only with wp eval-file on isolated WordPress.
if (!defined('WP_CLI') || !WP_CLI) exit;
global $wpdb;
function gs_call($path,$body=null,$token='') {
    $r=new WP_REST_Request($body===null?'GET':'POST','/goma-app/v1/'.$path);
    if ($body!==null) $r->set_body_params($body);
    if ($token) $r->set_header('authorization','Bearer '.$token);
    return rest_do_request($r);
}
function gs_check($ok,$label) { if (!$ok) throw new Exception('FAIL '.$label); echo "PASS $label\n"; }
$users=[]; $tokens=[]; $seq=bin2hex(random_bytes(4));
for ($i=0;$i<3;$i++) {
    $uid=wp_insert_user(['user_login'=>'social_'.$seq.'_'.$i,'user_email'=>'social_'.$seq.'_'.$i.'@example.test','user_pass'=>'isolated-test-password','display_name'=>'Social Test '.$i,'role'=>'gwr_listener']);
    $users[]=$uid; update_user_meta($uid,'gwr_verified',1);
    $wpdb->insert(GWR_Store::t('accounts'),['user_id'=>$uid,'code'=>GWR_Store::new_code(),'created'=>current_time('mysql',true)]);
    $tokens[]=gs_call('login',['email'=>get_userdata($uid)->user_email,'password'=>'isolated-test-password'])->get_data()['token'];
}
[$a,$b,$c]=$users; [$at,$bt,$ct]=$tokens;
gs_check(gs_call('social/club')->get_status()===401,'Club requires a session');
$code=gs_call('me',null,$at)->get_data()['code'];
gs_check((bool)preg_match('/^[A-Z0-9]{4}$/',$code),'Referral code has exactly four letters/digits');
gs_check(gs_call('me',null,$at)->get_data()['invite_url']==='https://app.gomawebradio.com/inscription?ref='.$code,'Referral link is inside the application');
$long='LEGACY'.strtoupper($seq); $wpdb->update(GWR_Store::t('accounts'),['code'=>$long],['user_id'=>$a]); GWR_Store::install();
gs_check(strlen(gs_call('me',null,$at)->get_data()['code'])===4,'Upgrade replaces long codes with four characters');
gs_check(GWR_Store::referral_user($long)===$a,'Previously shared referral links still resolve');
gs_check(gs_call('social/send',['user_id'=>$b,'body'=>'Bonjour'],$at)->get_status()===403,'Non-friends cannot send messages');
gs_check(gs_call('social/request',['user_id'=>$b],$at)->get_data()['relation']==='outgoing','Friend request sent');
gs_check(gs_call('social/friends',null,$bt)->get_data()['incoming'][0]['id']===$a,'Recipient sees friend request');
gs_check(gs_call('social/respond',['user_id'=>$a,'accept'=>true],$ct)->get_status()===409,'Third party cannot accept a request');
gs_check(gs_call('social/respond',['user_id'=>$a,'accept'=>true],$bt)->get_data()['relation']==='friend','Recipient accepts friendship');
$msg=gs_call('social/send',['user_id'=>$b,'body'=>'Bonjour le club'],$at)->get_data();
gs_check(!empty($msg['id']),'Message saved in WordPress');
gs_check(gs_call('social/messages?user_id='.$a,null,$bt)->get_data()['messages'][0]['body']==='Bonjour le club','Recipient reads saved message');
gs_check(gs_call('social/messages?user_id='.$b,null,$at)->get_data()['messages'][0]['seen']===true,'Sender sees read receipt');
gs_check(gs_call('social/messages?user_id='.$b,null,$ct)->get_status()===403,'Third party cannot read private messages');
gs_check(gs_call('social/send',['user_id'=>$b,'body'=>str_repeat('a',1001)],$at)->get_status()===400,'Messages above 1000 characters rejected');
for($i=0;$i<105;$i++) $wpdb->insert(GWR_Store::t('messages'),['sender'=>$a,'recipient'=>$b,'body'=>'History '.$i,'created'=>current_time('mysql',true)]);
$page=gs_call('social/messages?user_id='.$a,null,$bt)->get_data();
gs_check(count($page['messages'])===100 && $page['has_more'],'Conversation signals older history');
gs_check((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.GWR_Store::t('messages').' WHERE sender=%d AND recipient=%d AND read_at IS NULL',$a,$b))===6,'Only displayed messages are marked read');
$older=gs_call('social/messages?user_id='.$a.'&before='.$page['messages'][0]['id'],null,$bt)->get_data();
gs_check(count($older['messages'])===6 && !$older['has_more'],'Older messages are accessible');
$after=gs_call('social/messages?user_id='.$a.'&after='.$msg['id'],null,$bt)->get_data();
gs_check(count($after['messages'])===100 && $after['messages'][0]['body']==='History 0','Incremental polling returns oldest unseen batch first');
$post=gs_call('social/post',['body'=>'Notre club de Goma'],$at)->get_data();
gs_check(!empty($post['id']),'Club post stored');
gs_check(gs_call('social/like',['post_id'=>$post['id']],$bt)->get_data()['likes']===1,'Member likes a post');
gs_check(gs_call('social/like',['post_id'=>$post['id']],$bt)->get_data()['likes']===0,'Second click removes like');
gs_call('social/unpost',['post_id'=>$post['id']],$ct);
gs_check((int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.GWR_Store::t('club_posts').' WHERE id=%d',$post['id']))===$post['id'],'Other members cannot delete a post');
update_user_meta($a,'gwr_verified',0);
gs_check(!in_array($post['id'],array_column(gs_call('social/club',null,$bt)->get_data()['posts'],'id'),true),'Unverified authors are hidden from club');
gs_check(gs_call('social/like',['post_id'=>$post['id']],$bt)->get_status()===404,'Hidden posts cannot be liked');
update_user_meta($a,'gwr_verified',1);
gs_call('social/unpost',['post_id'=>$post['id']],$at);
gs_check(!$wpdb->get_var($wpdb->prepare('SELECT id FROM '.GWR_Store::t('club_posts').' WHERE id=%d',$post['id'])),'Author deletes own post');
gs_call('social/remove',['user_id'=>$b],$at);
gs_check(gs_call('social/send',['user_id'=>$b,'body'=>'No longer friends'],$at)->get_status()===403,'Removing friendship revokes messaging');
require_once ABSPATH.'wp-admin/includes/user.php'; foreach($users as $uid) wp_delete_user($uid);
echo "ALL SOCIAL CHECKS PASSED\n";
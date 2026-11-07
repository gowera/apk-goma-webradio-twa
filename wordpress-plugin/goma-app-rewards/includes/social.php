<?php
if (!defined('ABSPATH')) exit;
// Friendships and private messages between verified app listeners; only friends may message each other.
final class GWR_Social {
    public static function routes() {
        $routes=['social/search'=>'GET','social/friends'=>'GET','social/request'=>'POST','social/respond'=>'POST','social/remove'=>'POST','social/messages'=>'GET','social/send'=>'POST','social/club'=>'GET','social/post'=>'POST','social/like'=>'POST','social/unpost'=>'POST'];
        foreach($routes as $path=>$method) register_rest_route('goma-app/v1','/'.$path,['methods'=>$method,'permission_callback'=>['GWR_API','auth'],'callback'=>[self::class,substr($path,7)]]);
    }
    private static function pair($a,$b) { return [min($a,$b),max($a,$b)]; }
    public static function listener($uid) {
        $u=get_userdata($uid);
        return $u && in_array('gwr_listener',$u->roles,true) && get_user_meta($uid,'gwr_verified',true) ? $u : null;
    }
    public static function relation($me,$other) {
        global $wpdb; [$a,$b]=self::pair($me,$other);
        $row=$wpdb->get_row($wpdb->prepare('SELECT requester,status FROM '.GWR_Store::t('friends').' WHERE user_a=%d AND user_b=%d',$a,$b));
        if (!$row) return 'none';
        if ($row->status==='accepted') return 'friend';
        return (int)$row->requester===$me ? 'outgoing' : 'incoming';
    }
    private static function person($uid,$me) { $u=get_userdata($uid); return ['id'=>(int)$uid,'name'=>$u?$u->display_name:'Auditeur','relation'=>self::relation($me,(int)$uid)]; }
    private static function target($r,$me) {
        $id=absint($r->get_param('user_id'));
        if (!$id || $id===$me || !self::listener($id)) return GWR_API::error('Auditeur introuvable.',404);
        return $id;
    }
    public static function search($r) {
        global $wpdb; $me=GWR_API::uid();
        $q=trim(sanitize_text_field(wp_unslash($r->get_param('q')??'')));
        if (mb_strlen($q)<2) return [];
        $ids=$wpdb->get_col($wpdb->prepare("SELECT u.ID FROM {$wpdb->users} u JOIN ".GWR_Store::t('accounts')." a ON a.user_id=u.ID JOIN {$wpdb->usermeta} v ON v.user_id=u.ID AND v.meta_key='gwr_verified' AND v.meta_value='1' WHERE u.ID<>%d AND u.display_name LIKE %s ORDER BY u.display_name LIMIT 20",$me,'%'.$wpdb->esc_like($q).'%'));
        return array_map(fn($id)=>self::person((int)$id,$me),$ids);
    }
    public static function friends() {
        global $wpdb; $me=GWR_API::uid(); $t=GWR_Store::t('friends'); $m=GWR_Store::t('messages');
        $rows=$wpdb->get_results($wpdb->prepare("SELECT IF(user_a=%d,user_b,user_a) other,requester,status FROM $t WHERE user_a=%d OR user_b=%d ORDER BY created DESC",$me,$me,$me));
        $out=['friends'=>[],'incoming'=>[],'outgoing'=>[]];
        foreach($rows as $row) {
            $other=(int)$row->other; $p=self::person($other,$me);
            if ($row->status==='accepted') {
                $p['unread']=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $m WHERE sender=%d AND recipient=%d AND read_at IS NULL",$other,$me));
                $last=$wpdb->get_row($wpdb->prepare("SELECT body,created FROM $m WHERE (sender=%d AND recipient=%d) OR (sender=%d AND recipient=%d) ORDER BY id DESC LIMIT 1",$me,$other,$other,$me));
                $p['last']=$last?['body'=>$last->body,'created'=>$last->created]:null;
                $out['friends'][]=$p;
            } else $out[(int)$row->requester===$me?'outgoing':'incoming'][]=$p;
        }
        return $out;
    }
    public static function request($r) {
        global $wpdb; $me=GWR_API::uid(); $id=self::target($r,$me); if (is_wp_error($id)) return $id;
        $rel=self::relation($me,$id);
        if ($rel==='incoming') return self::accept($me,$id);
        if ($rel!=='none') return ['relation'=>$rel];
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.GWR_Store::t('friends')." WHERE requester=%d AND status='pending'",$me))>=50) return GWR_API::error('Trop de demandes en attente.',429);
        [$a,$b]=self::pair($me,$id);
        $wpdb->query($wpdb->prepare('INSERT IGNORE INTO '.GWR_Store::t('friends')." (user_a,user_b,requester,status,created) VALUES (%d,%d,%d,'pending',%s)",$a,$b,$me,current_time('mysql',true)));
        return ['relation'=>self::relation($me,$id)];
    }
    private static function accept($me,$id) {
        global $wpdb; [$a,$b]=self::pair($me,$id);
        $wpdb->query($wpdb->prepare('UPDATE '.GWR_Store::t('friends')." SET status='accepted' WHERE user_a=%d AND user_b=%d AND requester=%d",$a,$b,$id));
        return ['relation'=>self::relation($me,$id)];
    }
    public static function respond($r) {
        $me=GWR_API::uid(); $id=self::target($r,$me); if (is_wp_error($id)) return $id;
        if (self::relation($me,$id)!=='incoming') return GWR_API::error('Aucune demande à traiter.',409);
        return $r->get_param('accept') ? self::accept($me,$id) : self::remove($r);
    }
    public static function remove($r) {
        global $wpdb; $me=GWR_API::uid(); $id=absint($r->get_param('user_id')); [$a,$b]=self::pair($me,$id);
        $wpdb->delete(GWR_Store::t('friends'),['user_a'=>$a,'user_b'=>$b]);
        return ['relation'=>'none'];
    }
    public static function messages($r) {
        global $wpdb; $me=GWR_API::uid(); $id=self::target($r,$me); if (is_wp_error($id)) return $id;
        if (self::relation($me,$id)!=='friend') return GWR_API::error('Seuls vos amis peuvent échanger avec vous.',403);
        $m=GWR_Store::t('messages'); $after=absint($r->get_param('after')); $before=absint($r->get_param('before'));
        $order=$after ? 'ASC' : 'DESC';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,sender,body,created,read_at FROM $m WHERE id>%d AND (%d=0 OR id<%d) AND ((sender=%d AND recipient=%d) OR (sender=%d AND recipient=%d)) ORDER BY id $order LIMIT 101",$after,$before,$before,$me,$id,$id,$me),ARRAY_A);
        $has_more=count($rows)>100; $rows=array_slice($rows,0,100);
        $received=array_column(array_filter($rows,fn($x)=>(int)$x['sender']===$id),'id');
        // Mark only the messages returned to the recipient as read.
        if ($received) $wpdb->query($wpdb->prepare("UPDATE $m SET read_at=%s WHERE sender=%d AND recipient=%d AND read_at IS NULL AND id IN (".implode(',',array_map('absint',$received)).')',current_time('mysql',true),$id,$me));
        $seen=$wpdb->get_col($wpdb->prepare("SELECT id FROM $m WHERE sender=%d AND recipient=%d AND read_at IS NOT NULL ORDER BY id DESC LIMIT 100",$me,$id));
        return ['friend'=>self::person($id,$me),'has_more'=>$has_more,'seen_ids'=>array_map('intval',$seen),'messages'=>array_map(fn($x)=>['id'=>(int)$x['id'],'mine'=>(int)$x['sender']===$me,'body'=>$x['body'],'created'=>$x['created'],'seen'=>(int)$x['sender']===$me && $x['read_at']!==null],$after?$rows:array_reverse($rows))];
    }
    public static function send($r) {
        global $wpdb; $me=GWR_API::uid(); $id=self::target($r,$me); if (is_wp_error($id)) return $id;
        if (self::relation($me,$id)!=='friend') return GWR_API::error('Seuls vos amis peuvent échanger avec vous.',403);
        $body=trim(sanitize_textarea_field(wp_unslash((string)$r->get_param('body'))));
        if ($body==='' || mb_strlen($body)>1000) return GWR_API::error('Message vide ou trop long (1000 caractères maximum).');
        $m=GWR_Store::t('messages');
        if ((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $m WHERE sender=%d AND created>=%s",$me,gmdate('Y-m-d H:i:s',time()-60)))>=20) return GWR_API::error('Vous envoyez trop de messages. Patientez une minute.',429);
        if ($wpdb->insert($m,['sender'=>$me,'recipient'=>$id,'body'=>$body,'created'=>current_time('mysql',true)])===false) return GWR_API::error('Message non envoyé. Réessayez.',503);
        return ['id'=>(int)$wpdb->insert_id,'mine'=>true,'body'=>$body,'created'=>current_time('mysql',true),'seen'=>false];
    }

    // Listener club: public wall for verified members, likes, top listeners and newest members.
    public static function club($r) {
        global $wpdb; $me=GWR_API::uid(); $p=GWR_Store::t('club_posts'); $l=GWR_Store::t('club_likes'); $a=GWR_Store::t('accounts');
        $verified="JOIN {$wpdb->usermeta} v ON v.user_id=a.user_id AND v.meta_key='gwr_verified' AND v.meta_value='1'";
        $before=absint($r->get_param('before'));
        $posts=$wpdb->get_results($wpdb->prepare("SELECT p.id,p.user_id,p.body,p.created,(SELECT COUNT(*) FROM $l x WHERE x.post_id=p.id) likes,(SELECT COUNT(*) FROM $l y WHERE y.post_id=p.id AND y.user_id=%d) liked FROM $p p JOIN $a a ON a.user_id=p.user_id $verified WHERE (%d=0 OR p.id<%d) ORDER BY p.id DESC LIMIT 20",$me,$before,$before),ARRAY_A);
        $map=fn($id)=>self::person((int)$id,$me);
        return [
          'members'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $a a $verified"),
          'posts'=>array_map(fn($x)=>['id'=>(int)$x['id'],'author'=>$map($x['user_id']),'mine'=>(int)$x['user_id']===$me,'body'=>$x['body'],'created'=>$x['created'],'likes'=>(int)$x['likes'],'liked'=>(bool)$x['liked']],$posts),
          'top'=>array_map(fn($x)=>$map($x)+['points'=>(int)$wpdb->get_var($wpdb->prepare("SELECT balance FROM $a WHERE user_id=%d",$x))],$wpdb->get_col("SELECT a.user_id FROM $a a $verified ORDER BY a.balance DESC,a.user_id LIMIT 10")),
          'newest'=>array_map($map,$wpdb->get_col($wpdb->prepare("SELECT a.user_id FROM $a a $verified WHERE a.user_id<>%d ORDER BY a.created DESC LIMIT 10",$me))),
        ];
    }
    public static function post($r) {
        global $wpdb; $me=GWR_API::uid(); $p=GWR_Store::t('club_posts');
        if (!self::listener($me)) return GWR_API::error('Confirmez votre email pour publier dans le club.',403);
        $body=trim(sanitize_textarea_field(wp_unslash((string)$r->get_param('body'))));
        if ($body==='' || mb_strlen($body)>500) return GWR_API::error('Publication vide ou trop longue (500 caractères maximum).');
        if ((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $p WHERE user_id=%d AND created>=%s",$me,gmdate('Y-m-d H:i:s',time()-600)))>=5) return GWR_API::error('Trop de publications. Patientez quelques minutes.',429);
        if ($wpdb->insert($p,['user_id'=>$me,'body'=>$body,'created'=>current_time('mysql',true)])===false) return GWR_API::error('Publication impossible. Réessayez.',503);
        return ['id'=>(int)$wpdb->insert_id,'author'=>self::person($me,$me),'mine'=>true,'body'=>$body,'created'=>current_time('mysql',true),'likes'=>0,'liked'=>false];
    }
    public static function like($r) {
        global $wpdb; $me=GWR_API::uid(); $id=absint($r->get_param('post_id')); $l=GWR_Store::t('club_likes');
        $author=$wpdb->get_var($wpdb->prepare('SELECT user_id FROM '.GWR_Store::t('club_posts').' WHERE id=%d',$id));
        if (!$author || !self::listener((int)$author)) return GWR_API::error('Publication introuvable.',404);
        if (!$wpdb->delete($l,['post_id'=>$id,'user_id'=>$me])) $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $l (post_id,user_id) VALUES (%d,%d)",$id,$me));
        return ['likes'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $l WHERE post_id=%d",$id)),'liked'=>(bool)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $l WHERE post_id=%d AND user_id=%d",$id,$me))];
    }
    public static function unpost($r) {
        global $wpdb; $me=GWR_API::uid(); $id=absint($r->get_param('post_id'));
        if ($wpdb->delete(GWR_Store::t('club_posts'),['id'=>$id,'user_id'=>$me])) $wpdb->delete(GWR_Store::t('club_likes'),['post_id'=>$id]);
        return ['deleted'=>true];
    }
}

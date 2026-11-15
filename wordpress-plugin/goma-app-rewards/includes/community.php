<?php
if (!defined('ABSPATH')) exit;
// Listener social profile, photo uploads, comments and club notifications (in-app feed + FCM push from WordPress).
final class GWR_Community {
    public static function routes() {
        $routes=['social/profile'=>'GET','social/upload'=>'POST','social/comments'=>'GET','social/comment'=>'POST','social/uncomment'=>'POST','social/notices'=>'GET','social/device'=>'POST'];
        foreach($routes as $path=>$method) register_rest_route('goma-app/v1','/'.$path,['methods'=>$method,'permission_callback'=>['GWR_API','auth'],'callback'=>[self::class,substr($path,7)]]);
    }
    public static function profile_fields($uid) {
        $m=fn($k)=>(string)get_user_meta($uid,'gwr_'.$k,true);
        return ['avatar'=>$m('avatar'),'cover'=>$m('cover'),'bio'=>$m('bio'),'city'=>$m('city')];
    }
    /** Only images uploaded through this plugin may be referenced. */
    public static function own_upload($url) {
        $url=esc_url_raw(trim($url)); if ($url==='') return '';
        $base=trailingslashit(wp_get_upload_dir()['baseurl']).'goma-club/';
        if (!str_starts_with($url,$base)) return '';
        $filename=substr($url,strlen($base));
        if (!preg_match('/^[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp)$/i',$filename)) return '';
        $path=trailingslashit(wp_get_upload_dir()['basedir']).'goma-club/'.$filename;
        return is_file($path) ? $url : '';
    }
    public static function save_profile($uid,$r) {
        $bio=trim(sanitize_textarea_field(wp_unslash((string)($r['bio']??get_user_meta($uid,'gwr_bio',true)))));
        $city=trim(sanitize_text_field(wp_unslash((string)($r['city']??get_user_meta($uid,'gwr_city',true)))));
        if (mb_strlen($bio)>300) return GWR_API::error('Bio de 300 caractères maximum.');
        if (mb_strlen($city)>60) return GWR_API::error('Ville de 60 caractères maximum.');
        update_user_meta($uid,'gwr_bio',$bio); update_user_meta($uid,'gwr_city',$city);
        foreach(['avatar','cover'] as $k) if (isset($r[$k])) {
            $v=(string)$r[$k]; $url=self::own_upload($v);
            if ($v!=='' && (!$url || !in_array($url,get_user_meta($uid,'gwr_upload'),true))) return GWR_API::error('Utilisez une photo envoyée depuis votre compte.');
            update_user_meta($uid,'gwr_'.$k,$url);
        }
        return true;
    }
    public static function profile($r) {
        global $wpdb; $me=GWR_API::uid(); $id=absint($r->get_param('user_id'))?:$me;
        if ($id!==$me && !GWR_Social::listener($id)) return GWR_API::error('Auditeur introuvable.',404);
        $a=GWR_Store::t('accounts'); $f=GWR_Store::t('friends'); $u=get_userdata($id);
        return GWR_Social::person($id,$me)+self::profile_fields($id)+[
          'points'=>(int)$wpdb->get_var($wpdb->prepare("SELECT balance FROM $a WHERE user_id=%d",$id)),
          'friends'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $f WHERE status='accepted' AND (user_a=%d OR user_b=%d)",$id,$id)),
          'posts'=>(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.GWR_Store::t('club_posts').' WHERE user_id=%d',$id)),
          'since'=>$u?gmdate('Y-m-d H:i:s',strtotime($u->user_registered)):'','mine'=>$id===$me];
    }
    public static function upload($r) {
        $me=GWR_API::uid();
        if (!GWR_Social::listener($me)) return GWR_API::error('Confirmez votre email pour envoyer une photo.',403);
        $files=$r->get_file_params(); $file=$files['file']??null;
        if (!$file || !empty($file['error'])) return GWR_API::error('Photo manquante.');
        if ($file['size']>5*1024*1024) return GWR_API::error('Photo trop lourde (5 Mo maximum).');
        $type=wp_check_filetype_and_ext($file['tmp_name'],$file['name'],['jpg|jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp']);
        if (!$type['type'] || !@getimagesize($file['tmp_name'])) return GWR_API::error('Format accepté : JPG, PNG ou WebP.');
        $key='gwr_rate_upload_'.$me.'_'.floor(time()/3600); $n=(int)get_option($key,0);
        if ($n>=30) return GWR_API::error('Trop de photos envoyées. Réessayez plus tard.',429); update_option($key,$n+1,false);
        require_once ABSPATH.'wp-admin/includes/file.php';
        $dir=fn($d)=>array_merge($d,['path'=>$d['basedir'].'/goma-club','url'=>$d['baseurl'].'/goma-club','subdir'=>'/goma-club']);
        add_filter('upload_dir',$dir);
        $file['name']=wp_generate_password(16,false).'.'.$type['ext'];
        $saved=wp_handle_upload($file,['test_form'=>false,'mimes'=>['jpg|jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp']]);
        remove_filter('upload_dir',$dir);
        if (!empty($saved['error'])) return GWR_API::error('Envoi impossible. Réessayez.',503);
        $editor=wp_get_image_editor($saved['file']);
        if (!is_wp_error($editor)) { $editor->resize(1600,1600); $editor->save($saved['file']); }
        add_user_meta($me,'gwr_upload',$saved['url']);
        return ['url'=>$saved['url']];
    }
    private static function post_author($id) {
        global $wpdb; $a=$wpdb->get_var($wpdb->prepare('SELECT user_id FROM '.GWR_Store::t('club_posts').' WHERE id=%d',$id));
        return $a && GWR_Social::listener((int)$a) ? (int)$a : 0;
    }
    public static function comments($r) {
        global $wpdb; $me=GWR_API::uid(); $id=absint($r->get_param('post_id'));
        if (!self::post_author($id)) return GWR_API::error('Publication introuvable.',404);
        $before=absint($r->get_param('before'));
        $rows=$wpdb->get_results($wpdb->prepare('SELECT id,user_id,body,created FROM '.GWR_Store::t('club_comments').' WHERE post_id=%d AND (%d=0 OR id<%d) ORDER BY id DESC LIMIT 100',$id,$before,$before),ARRAY_A);
        $rows=array_values(array_filter($rows,fn($x)=>GWR_Social::listener((int)$x['user_id'])));
        return array_reverse(array_map(fn($x)=>['id'=>(int)$x['id'],'author'=>GWR_Social::person((int)$x['user_id'],$me),'mine'=>(int)$x['user_id']===$me,'body'=>$x['body'],'created'=>$x['created']],$rows));
    }
    public static function comment($r) {
        global $wpdb; $me=GWR_API::uid(); $id=absint($r->get_param('post_id')); $t=GWR_Store::t('club_comments');
        if (!GWR_Social::listener($me)) return GWR_API::error('Confirmez votre email pour commenter.',403);
        if (!self::post_author($id)) return GWR_API::error('Publication introuvable.',404);
        $body=trim(sanitize_textarea_field(wp_unslash((string)$r->get_param('body'))));
        if ($body==='' || mb_strlen($body)>300) return GWR_API::error('Commentaire vide ou trop long (300 caractères maximum).');
        if ((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE user_id=%d AND created>=%s",$me,gmdate('Y-m-d H:i:s',time()-60)))>=10) return GWR_API::error('Trop de commentaires. Patientez une minute.',429);
        $now=current_time('mysql',true);
        if ($wpdb->insert($t,['post_id'=>$id,'user_id'=>$me,'body'=>$body,'created'=>$now])===false) return GWR_API::error('Commentaire impossible. Réessayez.',503);
        return ['id'=>(int)$wpdb->insert_id,'author'=>GWR_Social::person($me,$me),'mine'=>true,'body'=>$body,'created'=>$now];
    }
    public static function uncomment($r) {
        global $wpdb; $wpdb->delete(GWR_Store::t('club_comments'),['id'=>absint($r->get_param('comment_id')),'user_id'=>GWR_API::uid()]);
        return ['deleted'=>true];
    }
    /** Club posts by other verified members newer than `after`, for the in-app bell. */
    public static function notices($r) {
        global $wpdb; $me=GWR_API::uid(); $p=GWR_Store::t('club_posts');
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,user_id,body,image,created FROM $p WHERE user_id<>%d AND id>%d ORDER BY id DESC LIMIT 15",$me,absint($r->get_param('after'))),ARRAY_A);
        $rows=array_values(array_filter($rows,fn($x)=>GWR_Social::listener((int)$x['user_id'])));
        return array_map(fn($x)=>['id'=>(int)$x['id'],'author'=>GWR_Social::person((int)$x['user_id'],$me),'body'=>$x['body'],'image'=>$x['image'],'created'=>$x['created']],$rows);
    }
    public static function device($r) {
        global $wpdb; $token=preg_replace('/[^A-Za-z0-9:_\-]/','',(string)$r->get_param('token'));
        if (strlen($token)<20 || strlen($token)>255) return GWR_API::error('Jeton invalide.');
        if ($r->get_param('enabled')===false) { $wpdb->delete(GWR_Store::t('devices'),['token'=>$token,'user_id'=>GWR_API::uid()]); return ['ok'=>true]; }
        if ($wpdb->replace(GWR_Store::t('devices'),['token'=>$token,'user_id'=>GWR_API::uid(),'updated'=>current_time('mysql',true)])===false) return GWR_API::error('Alertes non enregistrées.',503);
        return ['ok'=>true];
    }
    public static function announce($post_id) { wp_schedule_single_event(time(),'gwr_club_push',[(int)$post_id]); }
    private static function access_token($sa) {
        $cached=get_transient('gwr_fcm_access'); if ($cached) return $cached;
        $b=fn($d)=>rtrim(strtr(base64_encode($d),'+/','-_'),'=');
        $now=time(); $head=$b(wp_json_encode(['alg'=>'RS256','typ'=>'JWT']));
        $claim=$b(wp_json_encode(['iss'=>$sa['client_email'],'scope'=>'https://www.googleapis.com/auth/firebase.messaging','aud'=>'https://oauth2.googleapis.com/token','iat'=>$now,'exp'=>$now+3600]));
        if (!openssl_sign("$head.$claim",$sig,$sa['private_key'],'sha256WithRSAEncryption')) return '';
        $res=wp_remote_post('https://oauth2.googleapis.com/token',['timeout'=>15,'body'=>['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>"$head.$claim.".$b($sig)]]);
        $data=json_decode(wp_remote_retrieve_body($res),true); $tok=$data['access_token']??'';
        if ($tok) set_transient('gwr_fcm_access',$tok,3000); return $tok;
    }
    public static function push($post_id) {
        global $wpdb;
        $sa=json_decode((string)get_option('gwr_fcm_account',''),true);
        if (!is_array($sa) || empty($sa['project_id']) || empty($sa['private_key']) || empty($sa['client_email'])) return;
        $post=$wpdb->get_row($wpdb->prepare('SELECT user_id,body,image FROM '.GWR_Store::t('club_posts').' WHERE id=%d',$post_id));
        if (!$post || !GWR_Social::listener((int)$post->user_id)) return;
        $token=self::access_token($sa); if (!$token) return;
        $name=get_userdata((int)$post->user_id)->display_name; $body=trim($post->body)!==''?wp_trim_words($post->body,20):'a partagé une photo';
        $url=untrailingslashit(GWR_Store::settings()['app_url']).'/club';
        $devices=$wpdb->get_results($wpdb->prepare('SELECT d.token FROM '.GWR_Store::t('devices')." d JOIN {$wpdb->usermeta} v ON v.user_id=d.user_id AND v.meta_key='gwr_verified' AND v.meta_value='1' WHERE d.user_id<>%d LIMIT 2000",$post->user_id));
        foreach($devices as $d) {
            $msg=['token'=>$d->token,'notification'=>array_filter(['title'=>"$name a publié dans le club",'body'=>$body,'image'=>$post->image?:null]),'data'=>['url'=>$url,'notification_id'=>'goma-club-'.$post_id],'webpush'=>['fcm_options'=>['link'=>$url]]];
            $res=wp_remote_post('https://fcm.googleapis.com/v1/projects/'.rawurlencode($sa['project_id']).'/messages:send',['timeout'=>10,'headers'=>['Authorization'=>"Bearer $token",'Content-Type'=>'application/json'],'body'=>wp_json_encode(['message'=>$msg])]);
            $code=wp_remote_retrieve_response_code($res);
            $error=json_decode(wp_remote_retrieve_body($res),true);
            $details=$error['error']['details']??[];
            foreach($details as $detail) if (($detail['errorCode']??'')==='UNREGISTERED') $wpdb->delete(GWR_Store::t('devices'),['token'=>$d->token]);
        }
    }
    public static function menu() { add_submenu_page('goma-rewards','Notifications du club','Notifications du club','manage_options','goma-rewards-push',[self::class,'page']); }
    public static function page() {
        if (!current_user_can('manage_options')) return;
        $notice='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            check_admin_referer('gwr_push');
            $raw=trim(wp_unslash($_POST['account']??''));
            if ($raw==='') { delete_option('gwr_fcm_account'); $notice='Clé supprimée : les notifications téléphone du club sont désactivées.'; }
            else { $j=json_decode($raw,true); if (!is_array($j) || empty($j['project_id']) || empty($j['private_key']) || empty($j['client_email'])) $notice='Fichier JSON du compte de service Firebase invalide.'; else { update_option('gwr_fcm_account',wp_json_encode($j),false); delete_transient('gwr_fcm_access'); $notice='Clé Firebase enregistrée.'; } }
        }
        $has=(bool)get_option('gwr_fcm_account');
        echo '<div class="wrap"><h1>Notifications du club</h1>'.($notice?'<div class="notice notice-info"><p>'.esc_html($notice).'</p></div>':'');
        echo '<p>Collez le contenu du fichier JSON du compte de service Firebase (Console Firebase → Paramètres du projet → Comptes de service → Générer une nouvelle clé privée). Chaque nouvelle publication du club sera envoyée sur les téléphones des membres.</p><p>État : <b>'.($has?'activé':'non configuré').'</b></p>';
        echo '<form method="post">'; wp_nonce_field('gwr_push');
        echo '<textarea name="account" rows="10" class="large-text code" placeholder="'.($has?'Clé enregistrée — collez une nouvelle clé pour la remplacer, ou laissez vide et enregistrez pour la supprimer':'{ &quot;type&quot;: &quot;service_account&quot;, … }').'"></textarea>';
        submit_button('Enregistrer'); echo '</form></div>';
    }
}

<?php
if (!defined('ABSPATH')) exit;
final class GWR_API {
    private static $uid=0;
    public static function uid() { return self::$uid; }
    public static function routes() {
        $public=['config'=>'GET','signup'=>'POST','login'=>'POST','forgot'=>'POST','reset'=>'POST','verify'=>'POST'];
        $private=['me'=>'GET','logout'=>'POST','profile'=>'POST','event'=>'POST','read'=>'POST','listen'=>'POST','gifts'=>'GET','claim'=>'POST','leaderboard'=>'GET'];
        foreach($public+$private as $path=>$method) register_rest_route('goma-app/v1','/'.$path,[
            'methods'=>$method,'permission_callback'=>isset($public[$path])?'__return_true':[self::class,'auth'],
            'callback'=>[self::class,str_replace('-','_',$path)]
        ]);
    }
    public static function error($message,$status=400) { return new WP_Error('gwr_error',$message,['status'=>$status]); }
    public static function auth($r) {
        global $wpdb;
        self::$uid=0;
        $header=$r->get_header('authorization');
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/',$header??'',$m)) return self::error('Connectez-vous pour continuer.',401);
        $uid=(int)$wpdb->get_var($wpdb->prepare('SELECT user_id FROM '.GWR_Store::t('sessions').' WHERE hash=%s AND expires>%d',hash('sha256',$m[1]),time()));
        $u=get_userdata($uid);
        if (!$u || !in_array('gwr_listener',$u->roles,true) || !get_user_meta($uid,'gwr_verified',true)) return self::error('Votre session a expiré.',401);
        self::$uid=$uid; return true;
    }
    private static function limit($r,$kind,$max=10) {
        global $wpdb;
        // Atomic option increment; avoids trusting spoofable forwarded headers.
        $key='gwr_rate_'.hash('sha256',$kind.'|'.($_SERVER['REMOTE_ADDR']??'unknown').'|'.floor(time()/600));
        $n=$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,'1','off') ON DUPLICATE KEY UPDATE option_value=CAST(option_value AS UNSIGNED)+1",$key));
        if ($n===false) return false;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$key))<=$max;
    }
    public static function config() { return ['version'=>GWR_VERSION,'rules'=>GWR_Store::settings()['rules'],'minimum_listen_seconds'=>3600,'read_seconds'=>30,'share_proof'=>'declaration','referral_qualification'=>'verified_account_and_first_reward']; }
    private static function issue($uid) {
        global $wpdb;
        $token=bin2hex(random_bytes(32));
        $ok=$wpdb->insert(GWR_Store::t('sessions'),['user_id'=>$uid,'hash'=>hash('sha256',$token),'expires'=>time()+7*DAY_IN_SECONDS]);
        if (!$ok) return self::error('Connexion temporairement indisponible.',503);
        self::$uid=$uid;
        return ['token'=>$token,'expires'=>time()+7*DAY_IN_SECONDS,'user'=>self::me()];
    }
    private static function send_link($uid,$kind) {
        $user=get_userdata($uid); $token=bin2hex(random_bytes(32));
        update_user_meta($uid,'gwr_'.$kind,['hash'=>hash('sha256',$token),'expires'=>time()+($kind==='verify'?DAY_IN_SECONDS:3600)]);
        $app=GWR_Store::settings()['app_url'];
        $url=add_query_arg(['email'=>$user->user_email,'token'=>$token],$app.($kind==='verify'?'/verification':'/reset-password'));
        return wp_mail($user->user_email,$kind==='verify'?'Confirmez votre compte GOMA WEBRADIO':'Réinitialisez votre mot de passe GOMA WEBRADIO',"Bonjour,\n\nOuvrez ce lien pour continuer :\n".$url."\n\nSi vous n’êtes pas à l’origine de cette demande, ignorez ce message.");
    }
    public static function signup($r) {
        global $wpdb;
        if (!self::limit($r,'signup',5)) return self::error('Trop de tentatives. Réessayez dans dix minutes.',429);
        $email=sanitize_email($r['email']); $name=sanitize_text_field($r['name']); $password=(string)$r['password'];
        if (!is_email($email) || strlen($name)<2 || strlen($name)>80 || strlen($password)<10 || strlen($password)>200 || !$r['consent']) return self::error('Nom, email, mot de passe de 10 caractères et acceptation des conditions requis.');
        if (email_exists($email)) return ['message'=>'Si cette adresse peut recevoir un compte, un email de confirmation sera envoyé. Sinon, connectez-vous ou réinitialisez votre mot de passe.'];
        $ref=0;
        if ($r['referral']) { $ref=(int)$wpdb->get_var($wpdb->prepare('SELECT user_id FROM '.GWR_Store::t('accounts').' WHERE code=%s',sanitize_text_field($r['referral']))); if (!$ref) return self::error('Code de parrainage introuvable.'); }
        $uid=wp_insert_user(['user_login'=>'gwr_'.bin2hex(random_bytes(12)),'user_email'=>$email,'display_name'=>$name,'user_pass'=>$password,'role'=>'gwr_listener']);
        if (is_wp_error($uid)) return self::error('Inscription impossible. Réessayez.',400);
        $ok=$wpdb->insert(GWR_Store::t('accounts'),['user_id'=>$uid,'code'=>bin2hex(random_bytes(8)),'referrer'=>$ref,'created'=>current_time('mysql',true)]);
        if (!$ok) { require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($uid); return self::error('Inscription non enregistrée.',503); }
        update_user_meta($uid,'gwr_consent',gmdate('c'));
        // Welcome bonus is server-defined and idempotent: one credit per account, ever.
        $welcome=(int)GWR_Store::settings()['rules']['welcome'];
        if ($welcome>0) GWR_Store::atomic($uid,function() use($uid,$welcome) { return ['awarded'=>GWR_Store::credit($uid,'welcome','welcome:'.$uid,$welcome)]; });
        if (!self::send_link($uid,'verify')) return self::error('Compte créé, mais l’email n’a pas pu être envoyé. Réessayez depuis « Mot de passe oublié ».',503);
        return ['message'=>'Vérifiez votre email pour confirmer votre compte avant de vous connecter.'];
    }
    public static function login($r) {
        if (!self::limit($r,'login',15)) return self::error('Trop de tentatives. Réessayez dans dix minutes.',429);
        $u=get_user_by('email',sanitize_email($r['email']));
        if (!$u || !in_array('gwr_listener',$u->roles,true) || !wp_check_password((string)$r['password'],$u->user_pass,$u->ID)) return self::error('Email ou mot de passe incorrect.',401);
        if (!get_user_meta($u->ID,'gwr_verified',true)) return self::error('Confirmez votre email. « Mot de passe oublié » peut renvoyer le lien.',403);
        return self::issue($u->ID);
    }
    public static function forgot($r) {
        if (!self::limit($r,'forgot',5)) return self::error('Réessayez dans dix minutes.',429);
        $u=get_user_by('email',sanitize_email($r['email']));
        if ($u && in_array('gwr_listener',$u->roles,true)) self::send_link($u->ID,get_user_meta($u->ID,'gwr_verified',true)?'reset':'verify');
        return ['message'=>'Si un compte correspond à cette adresse, un lien sera envoyé par email.'];
    }
    private static function consume($r,$kind) {
        global $wpdb;
        if (!self::limit($r,$kind,15)) return self::error('Réessayez dans dix minutes.',429);
        $u=get_user_by('email',sanitize_email($r['email']));
        if (!$u || !in_array('gwr_listener',$u->roles,true)) return self::error('Lien invalide ou expiré.');
        $meta=get_user_meta($u->ID,'gwr_'.$kind,true);
        if (!is_array($meta) || $meta['expires']<time() || !hash_equals($meta['hash'],hash('sha256',(string)$r['token']))) return self::error('Lien invalide ou expiré.');
        // Compare-and-delete makes recovery/verification tokens single-use even under concurrent requests.
        if (!delete_user_meta($u->ID,'gwr_'.$kind,$meta)) return self::error('Ce lien a déjà été utilisé.');
        return $u->ID;
    }
    public static function verify($r) { $uid=self::consume($r,'verify'); if (is_wp_error($uid)) return $uid; update_user_meta($uid,'gwr_verified',1); return ['message'=>'Email confirmé. Vous pouvez vous connecter.']; }
    public static function reset($r) {
        global $wpdb;
        if (strlen((string)$r['password'])<10 || strlen((string)$r['password'])>200) return self::error('Utilisez au moins 10 caractères.');
        $uid=self::consume($r,'reset'); if (is_wp_error($uid)) return $uid;
        wp_set_password((string)$r['password'],$uid); $wpdb->delete(GWR_Store::t('sessions'),['user_id'=>$uid]);
        return ['message'=>'Mot de passe modifié. Reconnectez-vous.'];
    }
    public static function logout($r) {
        global $wpdb;
        $wpdb->delete(GWR_Store::t('sessions'),['hash'=>hash('sha256',substr($r->get_header('authorization'),7))]);
        return ['ok'=>true];
    }
    public static function me() {
        global $wpdb;
        $uid=self::$uid; $u=get_userdata($uid);
        $a=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.GWR_Store::t('accounts').' WHERE user_id=%d',$uid),ARRAY_A);
        $listen=$wpdb->get_row($wpdb->prepare('SELECT seconds,last_tick FROM '.GWR_Store::t('listens').' WHERE user_id=%d',$uid),ARRAY_A);
        return ['id'=>$uid,'name'=>$u->display_name,'email'=>$u->user_email,'balance'=>(int)$a['balance'],'code'=>$a['code'],
          'invite_url'=>add_query_arg('gwr_invite',$a['code'],home_url('/')),
          'referrals'=>(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.GWR_Store::t('accounts').' WHERE referrer=%d AND qualified=1',$uid)),
          'listen_seconds'=>$listen && time()-(int)$listen['last_tick']<=90?(int)$listen['seconds']:0,
          'ledger'=>$wpdb->get_results($wpdb->prepare('SELECT id,kind,points,created FROM '.GWR_Store::t('ledger').' WHERE user_id=%d ORDER BY id DESC LIMIT 50',$uid),ARRAY_A),
          'claims'=>$wpdb->get_results($wpdb->prepare('SELECT c.id,g.title,c.status,c.cost,c.created FROM '.GWR_Store::t('claims').' c JOIN '.GWR_Store::t('gifts').' g ON c.gift_id=g.id WHERE c.user_id=%d ORDER BY c.id DESC LIMIT 50',$uid),ARRAY_A)];
    }
    public static function profile($r) {
        $name=sanitize_text_field($r['name']); if (strlen($name)<2 || strlen($name)>80) return self::error('Nom de 2 à 80 caractères requis.');
        $result=wp_update_user(['ID'=>self::$uid,'display_name'=>$name]); if (is_wp_error($result)) return self::error('Modification impossible.'); return self::me();
    }
    private static function qualify($uid) {
        global $wpdb;
        $a=$wpdb->get_row($wpdb->prepare('SELECT referrer,qualified FROM '.GWR_Store::t('accounts').' WHERE user_id=%d',$uid));
        if (!$a || !$a->referrer || $a->qualified || (int)$a->referrer===$uid) return;
        GWR_Store::atomic((int)$a->referrer,function() use($a,$uid,$wpdb) {
            $changed=$wpdb->query($wpdb->prepare('UPDATE '.GWR_Store::t('accounts').' SET qualified=1 WHERE user_id=%d AND qualified=0',$uid));
            if ($changed) GWR_Store::credit((int)$a->referrer,'referral','referral:'.$uid,(int)GWR_Store::settings()['rules']['referral']);
            return ['ok'=>true];
        });
    }
    public static function event($r) {
        $kind=(string)$r['kind']; if (!in_array($kind,['share_app','share_article'],true)) return self::error('Action inconnue.');
        $post=(int)$r['post_id']; if ($kind==='share_article' && get_post_status($post)!=='publish') return self::error('Article introuvable.');
        $uid=self::$uid; $rules=GWR_Store::settings()['rules'];
        $result=GWR_Store::atomic($uid,function() use($uid,$kind,$post,$rules) {
            if (GWR_Store::count_today($uid,$kind)>=($kind==='share_app'?1:(int)$rules['share_article_limit'])) return ['awarded'=>0,'message'=>'Limite quotidienne atteinte.'];
            return ['awarded'=>GWR_Store::credit($uid,$kind,$kind.':'.gmdate('Y-m-d').':'.$post,(int)$rules[$kind])];
        });
        if (!is_wp_error($result) && ($result['awarded']??0)>0) self::qualify($uid);
        return $result;
    }
    public static function read($r) {
        global $wpdb; $uid=self::$uid; $post=(int)$r['post_id'];
        if (get_post_status($post)!=='publish') return self::error('Article introuvable.');
        $result=GWR_Store::atomic($uid,function() use($uid,$post,$r,$wpdb) {
            if ($r['action']==='start') {
                $token=bin2hex(random_bytes(16));
                if ($wpdb->replace(GWR_Store::t('reads'),['user_id'=>$uid,'post_id'=>$post,'token'=>$token,'started'=>time()])===false) throw new Exception('read');
                return ['token'=>$token];
            }
            $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.GWR_Store::t('reads').' WHERE user_id=%d AND post_id=%d',$uid,$post));
            if (!$row || !hash_equals($row->token,(string)$r['token']) || time()-$row->started<30) return self::error('Lecture trop courte ou session expirée.');
            if (GWR_Store::count_today($uid,'read')>=(int)GWR_Store::settings()['rules']['read_limit']) return ['awarded'=>0];
            $wpdb->delete(GWR_Store::t('reads'),['user_id'=>$uid,'post_id'=>$post]);
            return ['awarded'=>GWR_Store::credit($uid,'read','read:'.$post,(int)GWR_Store::settings()['rules']['read'])];
        });
        if (!is_wp_error($result) && ($result['awarded']??0)>0) self::qualify($uid); return $result;
    }
    public static function listen($r) {
        global $wpdb; $uid=self::$uid; $action=(string)$r['action'];
        $result=GWR_Store::atomic($uid,function() use($uid,$action,$r,$wpdb) {
            $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.GWR_Store::t('listens').' WHERE user_id=%d',$uid));
            if ($action==='start') {
                if ($row && time()-$row->last_tick<=90) return self::error('Une écoute est déjà suivie sur un autre écran. Réessayez dans 90 secondes.',409);
                $token=bin2hex(random_bytes(16));
                if ($wpdb->replace(GWR_Store::t('listens'),['user_id'=>$uid,'token'=>$token,'seconds'=>0,'last_tick'=>time()])===false) throw new Exception('listen');
                return ['token'=>$token,'seconds'=>0,'awarded'=>0];
            }
            if (!$row || !hash_equals($row->token,(string)$r['token'])) return self::error('Session d’écoute expirée.',409);
            if ($action==='stop') { $wpdb->delete(GWR_Store::t('listens'),['user_id'=>$uid]); return ['seconds'=>0,'awarded'=>0]; }
            if ($action!=='tick') return self::error('Action inconnue.');
            $gap=time()-(int)$row->last_tick;
            $seconds=GWR_Rules::listening((int)$row->seconds,$gap);
            $new_token=$gap>90?bin2hex(random_bytes(16)):$row->token;
            $rules=GWR_Store::settings()['rules']; $awarded=0;
            if (GWR_Rules::hours($seconds)>GWR_Rules::hours((int)$row->seconds) && GWR_Store::count_today($uid,'listen')<(int)$rules['listen_limit'])
                $awarded=GWR_Store::credit($uid,'listen','listen:'.hash('sha256',$row->token).':'.GWR_Rules::hours($seconds),(int)$rules['listen']);
            if ($wpdb->update(GWR_Store::t('listens'),['token'=>$new_token,'seconds'=>$seconds,'last_tick'=>time()],['user_id'=>$uid])===false) throw new Exception('tick');
            return ['seconds'=>$seconds,'awarded'=>$awarded,'token'=>$new_token];
        });
        if (!is_wp_error($result) && ($result['awarded']??0)>0) self::qualify($uid); return $result;
    }
    public static function gifts() { global $wpdb; return $wpdb->get_results('SELECT id,title,cost,stock FROM '.GWR_Store::t('gifts').' WHERE active=1 AND cost>0 ORDER BY cost,id',ARRAY_A); }
    public static function claim($r) {
        global $wpdb; $uid=self::$uid; $gift=(int)$r['gift_id']; $key=(string)$r['request_key'];
        if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/',$key)) return self::error('Référence de demande invalide.');
        return GWR_Store::atomic($uid,function($a) use($uid,$gift,$key,$wpdb) {
            $old=$wpdb->get_row($wpdb->prepare('SELECT id,status FROM '.GWR_Store::t('claims').' WHERE user_id=%d AND request_key=%s',$uid,$key),ARRAY_A); if ($old) return $old;
            $g=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.GWR_Store::t('gifts').' WHERE id=%d FOR UPDATE',$gift));
            if (!$g || !$g->active || $g->stock<1 || $g->cost<1) return self::error('Cadeau indisponible.');
            if ($a->balance<$g->cost) return self::error('Votre solde est insuffisant.');
            if ($wpdb->insert(GWR_Store::t('claims'),['user_id'=>$uid,'gift_id'=>$gift,'cost'=>$g->cost,'request_key'=>$key,'created'=>current_time('mysql',true)])===false) throw new Exception('claim');
            $id=$wpdb->insert_id;
            if ($wpdb->query($wpdb->prepare('UPDATE '.GWR_Store::t('gifts').' SET stock=stock-1 WHERE id=%d',$gift))===false) throw new Exception('stock');
            GWR_Store::credit($uid,'gift','claim:'.$id,-(int)$g->cost);
            return ['id'=>$id,'status'=>'pending'];
        });
    }
    public static function leaderboard() {
        global $wpdb;
        // Never publish emails or login handles; participants are pseudonymous.
        return $wpdb->get_results('SELECT CONCAT("Auditeur ",RIGHT(p.code,4)) AS name, COUNT(*) AS referrals FROM '.GWR_Store::t('accounts').' a JOIN '.GWR_Store::t('accounts').' p ON a.referrer=p.user_id WHERE a.qualified=1 GROUP BY p.user_id,p.code ORDER BY referrals DESC,p.user_id ASC LIMIT 20',ARRAY_A);
    }
    public static function invite() {
        if (!isset($_GET['gwr_invite'])) return;
        global $wpdb; $code=sanitize_text_field(wp_unslash($_GET['gwr_invite']));
        if (!$wpdb->get_var($wpdb->prepare('SELECT user_id FROM '.GWR_Store::t('accounts').' WHERE code=%s',$code))) { status_header(404); exit('Invitation introuvable.'); }
        $url=add_query_arg('ref',$code,GWR_Store::settings()['app_url'].'/inscription');
        nocache_headers();
        echo '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Invitation — GOMA WEBRADIO</title><body><main><h1>GOMA WEBRADIO</h1><p>Vous êtes invité à rejoindre les auditeurs de GOMA WEBRADIO.</p><p><a href="'.esc_url('https://play.google.com/store/apps/details?id=com.gomawebradio.app.twa&pcampaignid=web_share').'">Télécharger sur Google Play</a></p><p><a href="'.esc_url($url).'">Créer mon compte dans l’application</a></p><p>Code de parrainage : '.esc_html($code).'</p></main></body></html>'; exit;
    }
    public static function cors($served,$result,$request,$server) {
        if (strpos($request->get_route(),'/goma-app/v1/')!==0) return $served;
        header_remove('Access-Control-Allow-Origin'); header_remove('Access-Control-Allow-Credentials');
        $origin=get_http_origin(); $settings=GWR_Store::settings();
        $allowed=array_filter(array_map('trim',explode("\n",$settings['origins']??'')));
        // The configured app address is always allowed, even if the origins list is stale.
        $app=wp_parse_url($settings['app_url']??'');
        if (is_array($app) && !empty($app['scheme']) && !empty($app['host'])) {
            $port=isset($app['port']) && !(($app['scheme']==='https' && $app['port']===443) || ($app['scheme']==='http' && $app['port']===80)) ? ':'.$app['port'] : '';
            $allowed[]=$app['scheme'].'://'.$app['host'].$port;
        }
        header('Vary: Origin',false);
        if ($origin && in_array($origin,$allowed,true)) {
            header('Access-Control-Allow-Origin: '.$origin);
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); header('Access-Control-Allow-Headers: Authorization, Content-Type');
        }
        header('Cache-Control: no-store, private'); header('X-Content-Type-Options: nosniff');
        return $served;
    }
}

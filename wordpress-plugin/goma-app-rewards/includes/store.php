<?php
if (!defined('ABSPATH')) exit;
final class GWR_Store {
    public static function t($name) { global $wpdb; return $wpdb->prefix.'gwr_'.$name; }
    public static function install() {
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $c=$wpdb->get_charset_collate();
        $schemas=[
          'accounts'=>"user_id bigint unsigned NOT NULL, code varchar(32) NOT NULL, referrer bigint unsigned NOT NULL DEFAULT 0, qualified tinyint NOT NULL DEFAULT 0, balance bigint NOT NULL DEFAULT 0, created datetime NOT NULL, PRIMARY KEY  (user_id), UNIQUE KEY code (code)",
          'sessions'=>"hash varchar(64) NOT NULL, user_id bigint unsigned NOT NULL, expires bigint NOT NULL, PRIMARY KEY  (hash), KEY user_id (user_id)",
          'ledger'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, event_key varchar(191) NOT NULL, kind varchar(32) NOT NULL, points int NOT NULL, created datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY event (user_id,event_key)",
          'listens'=>"user_id bigint unsigned NOT NULL, token varchar(64) NOT NULL, seconds int NOT NULL DEFAULT 0, last_tick bigint NOT NULL, PRIMARY KEY  (user_id)",
          'reads'=>"user_id bigint unsigned NOT NULL, post_id bigint unsigned NOT NULL, token varchar(64) NOT NULL, started bigint NOT NULL, PRIMARY KEY  (user_id,post_id)",
          'gifts'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, title varchar(120) NOT NULL, cost int NOT NULL DEFAULT 0, stock int NOT NULL DEFAULT 0, active tinyint NOT NULL DEFAULT 0, PRIMARY KEY  (id)",
          'claims'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, gift_id bigint unsigned NOT NULL, cost int NOT NULL, status varchar(20) NOT NULL DEFAULT 'pending', request_key varchar(64) NOT NULL, created datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY request (user_id,request_key)",
          'emails'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, period_key varchar(10) NOT NULL, period_start datetime NOT NULL, period_end datetime NOT NULL, status varchar(20) NOT NULL, attempts int NOT NULL DEFAULT 0, next_attempt bigint NOT NULL DEFAULT 0, PRIMARY KEY  (id), UNIQUE KEY recipient_period (user_id,period_key), KEY dispatch (status,next_attempt)",
          'newsletter'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, post_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL, status varchar(20) NOT NULL, attempts int NOT NULL DEFAULT 0, next_attempt bigint NOT NULL DEFAULT 0, created datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY post_user (post_id,user_id), KEY dispatch (status,next_attempt)",
          'friends'=>"user_a bigint unsigned NOT NULL, user_b bigint unsigned NOT NULL, requester bigint unsigned NOT NULL, status varchar(20) NOT NULL, created datetime NOT NULL, PRIMARY KEY  (user_a,user_b), KEY user_b (user_b)",
          'messages'=>"id bigint unsigned NOT NULL AUTO_INCREMENT, sender bigint unsigned NOT NULL, recipient bigint unsigned NOT NULL, body text NOT NULL, created datetime NOT NULL, read_at datetime NULL, PRIMARY KEY  (id), KEY conversation (sender,recipient,id), KEY inbox (recipient,read_at)"
        ];
        // dbDelta requires one column/index definition per line during upgrades.
        foreach($schemas as $name=>$s) dbDelta('CREATE TABLE '.self::t($name)." (\n".str_replace(', ',",\n",$s)."\n) ENGINE=InnoDB $c;");
        add_role('gwr_listener','Auditeur application', ['read'=>false]);
        add_option('gwr_settings', ['app_url'=>'https://app.gomawebradio.com','origins'=>"https://app.gomawebradio.com\nhttps://id-preview--551fd15d-244c-43e2-a5da-7a2ab3360d7a.lovable.app", 'rules'=>GWR_Rules::defaults()]);
        if (!$wpdb->get_var('SELECT COUNT(*) FROM '.self::t('gifts'))) {
            $wpdb->insert(self::t('gifts'), ['title'=>'T-shirt GOMA WEBRADIO','cost'=>0,'stock'=>0,'active'=>0]);
            $wpdb->insert(self::t('gifts'), ['title'=>'Chapeau GOMA WEBRADIO','cost'=>0,'stock'=>0,'active'=>0]);
        }
        update_option('gwr_version', GWR_VERSION);
        if (!wp_next_scheduled('gwr_cleanup')) wp_schedule_event(time()+3600,'daily','gwr_cleanup');
    }
    public static function settings() { $s=get_option('gwr_settings',[]); $s['rules']=array_merge(GWR_Rules::defaults(),$s['rules']??[]); return $s; }
    public static function atomic($uid, $fn) {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $account=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::t('accounts').' WHERE user_id=%d FOR UPDATE',$uid));
        if (!$account) { $wpdb->query('ROLLBACK'); return new WP_Error('account','Compte application introuvable.',['status'=>403]); }
        try { $result=$fn($account); if (is_wp_error($result)) { $wpdb->query('ROLLBACK'); return $result; }
            if ($wpdb->query('COMMIT')===false) throw new Exception('commit');
            return $result;
        } catch (Throwable $e) { $wpdb->query('ROLLBACK'); return new WP_Error('storage','Opération non enregistrée. Réessayez.',['status'=>503]); }
    }
    // Caller must hold the account row lock. No client-supplied point amounts.
    public static function credit($uid,$kind,$key,$points) {
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::t('ledger').' WHERE user_id=%d AND event_key=%s',$uid,$key))) return 0;
        if ($wpdb->insert(self::t('ledger'),['user_id'=>$uid,'kind'=>$kind,'event_key'=>$key,'points'=>$points,'created'=>current_time('mysql',true)])===false) throw new Exception('ledger');
        if ($wpdb->query($wpdb->prepare('UPDATE '.self::t('accounts').' SET balance=balance+%d WHERE user_id=%d',$points,$uid))===false) throw new Exception('balance');
        return $points;
    }
    public static function count_today($uid,$kind) {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.self::t('ledger').' WHERE user_id=%d AND kind=%s AND created >= %s',$uid,$kind,gmdate('Y-m-d').' 00:00:00'));
    }
    public static function cleanup() {
        global $wpdb;
        $wpdb->query($wpdb->prepare('DELETE FROM '.self::t('sessions').' WHERE expires<%d',time()));
        $wpdb->query($wpdb->prepare('DELETE FROM '.self::t('reads').' WHERE started<%d',time()-86400));
        $wpdb->query($wpdb->prepare('DELETE FROM '.self::t('listens').' WHERE last_tick<%d',time()-86400));
        // Rate counters are short-lived; their trailing bucket is the retention boundary.
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",$wpdb->esc_like('gwr_rate_').'%'));
    }
    public static function delete_user($uid) {
        global $wpdb;
        foreach(['accounts','sessions','ledger','listens','reads','claims','emails','newsletter'] as $t) $wpdb->delete(self::t($t),['user_id'=>$uid]);
        foreach(['user_a','user_b'] as $c) $wpdb->delete(self::t('friends'),[$c=>$uid]);
        foreach(['sender','recipient'] as $c) $wpdb->delete(self::t('messages'),[$c=>$uid]);
        $wpdb->update(self::t('accounts'),['referrer'=>0],['referrer'=>$uid]);
    }
}

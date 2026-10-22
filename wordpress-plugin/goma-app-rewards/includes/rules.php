<?php
if (!defined('ABSPATH')) exit;
final class GWR_Rules {
    public static function defaults() {
        return ['read'=>3, 'share_app'=>7, 'share_article'=>6, 'listen'=>30, 'referral'=>0, 'read_limit'=>20, 'share_article_limit'=>10, 'listen_limit'=>4];
    }
    public static function listening($seconds, $gap) {
        if ($gap < 0 || $gap > 90) return 0;
        return $seconds + min($gap, 45);
    }
    public static function hours($seconds) { return (int) floor(max(0, $seconds) / 3600); }
}

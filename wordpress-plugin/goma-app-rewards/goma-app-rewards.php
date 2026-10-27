<?php
/**
 * Plugin Name: GOMA WEBRADIO — Récompenses Application
 * Description: Comptes réservés à l’application, parrainages, points et cadeaux. API sécurisée et administration WordPress.
 * Version: 1.4.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: GOMA WEBRADIO
 * License: GPL-2.0-or-later
 * Text Domain: goma-app-rewards
 */
if (!defined('ABSPATH')) exit;
define('GWR_VERSION', '1.4.0');
require_once __DIR__.'/includes/rules.php';
require_once __DIR__.'/includes/store.php';
require_once __DIR__.'/includes/api.php';
require_once __DIR__.'/includes/admin.php';
require_once __DIR__.'/includes/weekly-emails.php';
require_once __DIR__.'/includes/newsletter.php';
require_once __DIR__.'/includes/social.php';
register_activation_hook(__FILE__, ['GWR_Store','install']);
add_action('plugins_loaded', function() { if (get_option('gwr_version') !== GWR_VERSION) GWR_Store::install(); });
add_action('rest_api_init', ['GWR_API','routes']);
add_action('rest_api_init', ['GWR_Social','routes']);
add_action('admin_menu', ['GWR_Admin','menu']);
add_action('gwr_cleanup', ['GWR_Store','cleanup']);
add_action('gwr_weekly_emails', ['GWR_Weekly_Emails','run']);
add_action('init', ['GWR_Weekly_Emails','schedule']);
add_action('template_redirect', ['GWR_Weekly_Emails','unsubscribe']);
add_filter('cron_schedules', ['GWR_Newsletter','interval']);
add_action('init', ['GWR_Newsletter','schedule']);
add_action('transition_post_status', ['GWR_Newsletter','published'], 10, 3);
add_action('gwr_newsletter', ['GWR_Newsletter','run']);
add_action('template_redirect', ['GWR_Newsletter','unsubscribe']);
register_deactivation_hook(__FILE__, function() { wp_clear_scheduled_hook('gwr_cleanup'); wp_clear_scheduled_hook('gwr_weekly_emails'); wp_clear_scheduled_hook('gwr_newsletter'); });
add_action('delete_user', ['GWR_Store','delete_user']);
add_action('template_redirect', ['GWR_API','invite']);
add_filter('rest_pre_serve_request', ['GWR_API','cors'], 20, 4);

<?php
// Run with wp eval-file on an isolated WordPress served over HTTP.
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) exit;
function cors_check($ok,$label) { if (!$ok) throw new Exception('FAIL '.$label); echo "PASS $label\n"; }
$original=get_option('gwr_settings');
$settings=GWR_Store::settings();
$settings['app_url']='https://app.gomawebradio.com:8443/application';
$settings['origins']='https://preview.example.test';
update_option('gwr_settings',$settings);
function cors_request($origin,$method='OPTIONS',$path='signup') {
    $r=wp_remote_request(add_query_arg('rest_route','/goma-app/v1/'.$path,home_url('/')),['method'=>$method,'headers'=>['Origin'=>$origin,'Access-Control-Request-Method'=>'POST','Access-Control-Request-Headers'=>'authorization,content-type','Content-Type'=>'application/json'],'body'=>$method==='POST'?'{}':null]);
    if (is_wp_error($r)) throw new Exception('HTTP test unavailable: '.$r->get_error_message());
    return $r;
}
try {
    $origin='https://app.gomawebradio.com:8443';
    $r=cors_request($origin);
    cors_check(wp_remote_retrieve_response_code($r)===200 && wp_remote_retrieve_header($r,'access-control-allow-origin')===$origin,'OPTIONS allows configured app origin with port, even absent from list');
    $headers=strtolower(wp_remote_retrieve_header($r,'access-control-allow-headers'));
    cors_check(strpos($headers,'authorization')!==false && strpos($headers,'content-type')!==false,'Preflight allows session and JSON headers');
    cors_check(wp_remote_retrieve_header(cors_request('https://preview.example.test'),'access-control-allow-origin')==='https://preview.example.test','Explicit preview origin is allowed');
    cors_check(!wp_remote_retrieve_header(cors_request('https://untrusted.example.test'),'access-control-allow-origin'),'Unknown origin is not allowed');
    cors_check(!wp_remote_retrieve_header(cors_request('https://app.gomawebradio.com'),'access-control-allow-origin'),'Different port is not implicitly allowed');
    $r=cors_request($origin,'POST','login');
    cors_check(wp_remote_retrieve_response_code($r)===401 && wp_remote_retrieve_header($r,'access-control-allow-origin')===$origin,'Login error retains CORS headers');
    $r=cors_request($origin,'GET','me');
    cors_check(wp_remote_retrieve_response_code($r)===401 && wp_remote_retrieve_header($r,'access-control-allow-origin')===$origin,'Private access denial retains CORS headers');
} finally { update_option('gwr_settings',$original); }
echo "ALL CORS CHECKS PASSED\n";
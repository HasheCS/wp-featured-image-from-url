<?php
/** Disposable WordPress CI acceptance for both Featured Image distribution channels. */
if (!defined('ABSPATH') || !class_exists('WP_CLI')) {
    throw new RuntimeException('wp_cli_wordpress_runtime_required');
}
$basename = 'featured-image-from-url/featured-image-from-url.php';
$path=WP_PLUGIN_DIR.'/'.$basename;
if(!file_exists($path))WP_CLI::error('Preserved installed plugin basename missing');
require_once ABSPATH.'wp-admin/includes/plugin.php';
$data=get_plugin_data($path,false,false);
if(($data['Version']??'')!=='1.1.0')WP_CLI::error('Candidate main header mismatch');
$channel=getenv('HASHE_QA_CHANNEL');
$uri=$data['UpdateURI']??'';
if($channel==='independent'){
    if($uri!=='https://plugins.hashe.com/wp-featured-image-from-url')WP_CLI::error('Independent Update URI does not match website catalog');
    if(!has_filter('update_plugins_plugins.hashe.com'))WP_CLI::error('Independent native WordPress updater not attached');
}elseif($channel==='wordpress-org'){
    if($uri!=='')WP_CLI::error('WordPress.org channel must not contain external Update URI');
    if(has_filter('update_plugins_plugins.hashe.com'))WP_CLI::error('WordPress.org package must not use Hashe external updater');
}else WP_CLI::error('Invalid QA channel');
$option=get_option('hashe_fifu_optional_reporting_v1',false);
if($option!==false)WP_CLI::error('Optional installation reporting enabled or configured without consent');
if(get_option('hashe_fifu_show_privacy_notice')!=='1')WP_CLI::error('Admin-only privacy choice notice not prepared');
if(!has_action('admin_post_hashe_fifu_privacy_choice'))WP_CLI::error('Optional consent admin choice not registered');
if(!has_action('wp_ajax_fifu_fetch_featured_from_url'))WP_CLI::error('Existing media import AJAX unavailable');
// Real WordPress guard regression: core may allow the site's own localhost URL.
$guard = new ReflectionMethod( 'FIFU_Plugin', 'safe_url' );
$guard->setAccessible( true );
foreach ( array( 'http://127.0.0.1/image.png', 'http://10.0.0.1/x.png',
 'http://169.254.169.254/latest/meta-data/', 'http://192.168.1.1/image.png',
 'http://[::1]/image.png', 'http://localhost/image.png' ) as $unsafe ) {
 if ( false !== $guard->invoke( null, $unsafe ) ) WP_CLI::error( 'Private/loopback URL accepted: ' . $unsafe );
}
WP_CLI::log('PRIVATE_AND_LOOPBACK_SSRF_GUARD=PASS');
WP_CLI::log('CHANNEL='.$channel);
WP_CLI::log('PRESERVED_INSTALLED_BASENAME='.$basename);
WP_CLI::log('DEFAULT_REPORTING_OFF=PASS');
WP_CLI::log('CORE_MEDIA_IMPORT_REGISTERED=PASS');
WP_CLI::log('CHANNEL_LIFECYCLE_QA=PASS');

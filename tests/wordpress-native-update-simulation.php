<?php
/**
 * CI-only real WordPress Plugin_Upgrader test.
 * The synthetic 1.1.1 package is never published, merged or served by Hashe.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || getenv( 'HASHE_QA_EPHEMERAL' ) !== '1' ) {
    fwrite( STDERR, "Explicit disposable WordPress QA mode required\n" );
    exit( 2 );
}
// Require a protected disposable install and localhost-only WordPress origin.
$root = getenv( 'HASHE_QA_WP_ROOT' );
if ( ! is_string( $root ) || ! is_dir( $root ) ||
    realpath( $root ) !== realpath( ABSPATH ) ) {
    WP_CLI::error( 'Refusing an unverified disposable WordPress root' );
}
$origin = wp_parse_url( home_url( '/' ) );
if ( ! is_array( $origin ) ||
    ( $origin['scheme'] ?? null ) !== 'http' ||
    ! in_array( $origin['host'] ?? '', array( 'localhost', '127.0.0.1' ), true ) ||
    ! in_array( (int) ( $origin['port'] ?? 0 ), array( 8098, 18977 ), true ) ) {
    WP_CLI::error( 'Refusing a non-localhost isolated test URL' );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

$plugin = 'featured-image-from-url/featured-image-from-url.php';
$main = WP_PLUGIN_DIR . '/' . $plugin;
$zip = getenv( 'HASHE_QA_SYNTHETIC_ZIP' );
if ( ! is_string( $zip ) || ! is_file( $zip ) || ! is_file( $main ) ) {
    WP_CLI::error( 'Candidate or disposable update ZIP not found' );
}
$installed = get_plugin_data( $main, false, false );
if ( ( $installed['Version'] ?? '' ) !== '1.1.0' ||
    ( $installed['UpdateURI'] ?? '' ) !== 'https://plugins.hashe.com/wp-featured-image-from-url' ) {
    WP_CLI::error( 'Expected approved independent candidate was not installed' );
}
if ( false === has_filter( 'update_plugins_plugins.hashe.com' ) ||
    false === has_filter( 'upgrader_pre_download' ) ) {
    WP_CLI::error( 'WordPress native update filters were not registered' );
}
$slug = 'wp-featured-image-from-url';
$url = 'https://github.com/HasheCS/wp-featured-image-from-url/releases/download/v1.1.1/wp-featured-image-from-url-v1.1.1.zip';
$uri = 'https://plugins.hashe.com/wp-featured-image-from-url';
$sha = hash_file( 'sha256', $zip );
$manifest = array(
    'schema_version' => 1,
    'plugin' => $slug,
    'slug' => $slug,
    'status' => 'stable',
    'approved' => true,
    'prerelease' => false,
    'version' => '1.1.1',
    'new_version' => '1.1.1',
    'tag' => 'v1.1.1',
    'sha256' => $sha,
    'package_url' => $url,
    'details_url' => 'https://github.com/HasheCS/wp-featured-image-from-url/releases/tag/v1.1.1',
    'requires' => '5.8',
    'requires_wordpress' => '5.8',
    'tested' => '7.1',
    'requires_php' => '7.4',
    'release' => array( 'tag' => 'v1.1.1', 'asset_url' => $url, 'sha256' => $sha ),
);
$key = 'hashe_v1_update_' . $slug;
$fetches = 0;
$remote = static function ( $pre, $args, $requested ) use ( $url, $zip, &$fetches ) {
    if ( $requested !== $url ) {
        // Keep all other site requests unmodified; the test never publishes a release.
        return $pre;
    }
    ++$fetches;
    if ( empty( $args['stream'] ) || empty( $args['filename'] ) ||
        ! copy( $zip, $args['filename'] ) ) {
        return new WP_Error( 'qa_stream_failed', 'Disposable package download was not streamed' );
    }
    return array(
        'response' => array( 'code' => 200, 'message' => 'OK' ),
        'headers' => array( 'content-type' => 'application/zip' ),
        'body' => '',
        'cookies' => array(),
        'filename' => $args['filename'],
    );
};
add_filter( 'pre_http_request', $remote, 10, 3 );
set_site_transient( $key, $manifest, 900 );
$offer = apply_filters(
    'update_plugins_plugins.hashe.com',
    false,
    array( 'UpdateURI' => $uri, 'Version' => '1.1.0' ),
    $plugin,
    array( 'en_US' )
);
if ( ! is_array( $offer ) || ( $offer['version'] ?? '' ) !== '1.1.1' ||
    ( $offer['package'] ?? '' ) !== $url ) {
    WP_CLI::error( 'Approved synthetic update offer was not returned' );
}
echo "FEATURED_NATIVE_APPROVED_UPDATE_OFFER=PASS\n";

$options = array(
    'package' => $url,
    'destination' => WP_PLUGIN_DIR,
    'clear_destination' => true,
    'clear_working' => true,
    'is_multi' => false,
    'hook_extra' => array( 'plugin' => $plugin, 'type' => 'plugin', 'action' => 'update' ),
);
$original = hash_file( 'sha256', $main );
$manifest['sha256'] = str_repeat( 'f', 64 );
$manifest['release']['sha256'] = $manifest['sha256'];
set_site_transient( $key, $manifest, 900 );
$bad = ( new Plugin_Upgrader( new WP_Upgrader_Skin() ) )->run( $options );
if ( ! is_wp_error( $bad ) || $bad->get_error_code() !== 'hashe_fifu_checksum_failed' ||
    ! is_file( $main ) || hash_file( 'sha256', $main ) !== $original ) {
    WP_CLI::error( 'Tampered update changed the installed code or was not rejected' );
}
echo "FEATURED_NATIVE_UPGRADE_BAD_SHA256_REJECTED=PASS\n";

$manifest['sha256'] = $sha;
$manifest['release']['sha256'] = $sha;
set_site_transient( $key, $manifest, 900 );
$good = ( new Plugin_Upgrader( new WP_Upgrader_Skin() ) )->run( $options );
if ( is_wp_error( $good ) || ! $good ||
    ( get_plugin_data( $main, false, false )['Version'] ?? '' ) !== '1.1.1' ||
    $fetches !== 2 ) {
    WP_CLI::error( 'Approved package was not installed by the real WordPress upgrader' );
}
if ( get_option( 'hashe_fifu_optional_reporting_v1', false ) !== false ) {
    WP_CLI::error( 'WordPress upgrader silently activated optional telemetry' );
}
remove_filter( 'pre_http_request', $remote, 10 );
echo "FEATURED_REAL_WP_UPGRADER_VERIFIED_ZIP_INSTALL=PASS\n";
echo "FEATURED_NATIVE_UPDATE_NO_TELEMETRY=PASS\n";
echo "NOTE=Only synthetic 1.1.1 in disposable CI; no public release created\n";

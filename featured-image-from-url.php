<?php
/*
Plugin Name: Featured Image From URL
Description: Adds a "From URL" tab to the WordPress media modal so you can fetch any image by URL and set it as the featured image.
Version: 1.0.0
Author: Hashe Computer Solutions
License: MIT
Requires at least: 5.8
Tested up to: 6.6
*/

if (!defined('ABSPATH')) { exit; }

final class FIFU_Plugin {
    const HANDLE = 'fifu-from-url';
    const NONCE  = 'fifu_from_url';

    public static function init(){
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('wp_ajax_fifu_fetch_featured_from_url', [__CLASS__, 'ajax_fetch']);
    }

    public static function enqueue($hook){
        // Only where media modal is used for featured image
        if (!in_array($hook, ['post.php','post-new.php','site-editor.php','widgets.php','nav-menus.php'], true)) return;

        wp_enqueue_media();
        wp_register_script(self::HANDLE, plugins_url('assets/js/featured-image-from-url.js', __FILE__), ['media-views','jquery'], '1.0.0', true);

        $post_id = get_the_ID() ?: 0;
        wp_localize_script(self::HANDLE, 'FIFU', [
            'ajax'   => admin_url('admin-ajax.php'),
            'nonce'  => wp_create_nonce(self::NONCE),
            'postId' => $post_id,
            'tab'    => __('From URL', 'fifu'),
            'btn'    => __('Fetch and set featured', 'fifu'),
            'ph'     => __('https://example.com/image.jpg', 'fifu'),
            'fetch'  => __('Fetching...', 'fifu'),
            'ok'     => __('Featured image set', 'fifu'),
            'err'    => __('Could not fetch that image', 'fifu'),
        ]);

        wp_enqueue_script(self::HANDLE);
    }

    public static function ajax_fetch(){
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('upload_files')) wp_send_json_error(['message' => 'forbidden']);

        $post_id = isset($_POST['postId']) ? (int) $_POST['postId'] : 0;
        $url     = esc_url_raw(trim((string)($_POST['url'] ?? '')));
        if (!$url) wp_send_json_error(['message' => 'missing URL']);

        // Load required admin helpers
        if (!function_exists('media_sideload_image')) require_once ABSPATH.'wp-admin/includes/media.php';
        if (!function_exists('download_url') || !function_exists('wp_handle_sideload')) {
            require_once ABSPATH.'wp-admin/includes/file.php';
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH.'wp-admin/includes/image.php';
        }

        // Sideload and get attachment ID
        $att_id = media_sideload_image($url, $post_id ?: 0, null, 'id');
        if (is_wp_error($att_id)) {
            wp_send_json_error(['message' => $att_id->get_error_message()]);
        }

        if ($post_id){
            set_post_thumbnail($post_id, $att_id);
        }

        if (!function_exists('_wp_post_thumbnail_html')) {
            require_once ABSPATH.'wp-admin/includes/post.php';
        }
        $html = $post_id ? _wp_post_thumbnail_html(get_post_thumbnail_id($post_id), $post_id) : '';

        $thumb = wp_get_attachment_image_src($att_id, 'thumbnail');
        wp_send_json_success([
            'id'   => $att_id,
            'src'  => $thumb ? $thumb[0] : '',
            'html' => $html
        ]);
    }
}
FIFU_Plugin::init();

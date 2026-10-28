<?php
/**
 * Plugin Name: Featured Image From URL
 * Description: Import remote images into the WordPress Media Library directly from the Media modal, Add Media, or Featured Image selector.
 * Version: 1.1.0-rc.1
 * Author: Hashe Computer Solutions
 * Author URI: https://hashe.com/
 * License: MIT
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: fifu
 */

defined( 'ABSPATH' ) || exit;

final class FIFU_Plugin {
    const HANDLE = 'fifu-from-url';
    const NONCE = 'fifu_from_url';
    const MAX_BYTES = 10485760; // 10 MiB; includes a streaming hard cap.

    public static function init() {
        add_action( 'init', array( __CLASS__, 'textdomain' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        // Retain the v1.0.0 AJAX action for compatibility with existing installs.
        add_action( 'wp_ajax_fifu_fetch_featured_from_url', array( __CLASS__, 'ajax_fetch' ) );
    }

    public static function textdomain() {
        load_plugin_textdomain( 'fifu', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public static function enqueue( $hook ) {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'upload.php', 'media-new.php' ), true ) ) {
            return;
        }
        if ( ! current_user_can( 'upload_files' ) ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script(
            self::HANDLE,
            plugins_url( 'assets/js/featured-image-from-url.js', __FILE__ ),
            array( 'media-views', 'jquery', 'underscore' ),
            '1.1.0-rc.1',
            true
        );
        $post_id = 0;
        if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        wp_localize_script( self::HANDLE, 'FIFU', array(
            'ajax'       => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( self::NONCE ),
            'postId'     => $post_id,
            'tab'        => __( 'From URL', 'fifu' ),
            'label'      => __( 'Paste an image URL', 'fifu' ),
            'btn'        => __( 'Fetch image', 'fifu' ),
            'featuredBtn'=> __( 'Fetch and set featured image', 'fifu' ),
            'ph'         => 'https://example.com/photo.jpg',
            'fetch'      => __( 'Importing image...', 'fifu' ),
            'ok'         => __( 'Image imported to the Media Library.', 'fifu' ),
            'err'        => __( 'Could not import that image.', 'fifu' ),
            'libraryBtn' => __( 'Import from URL', 'fifu' ),
            'libraryTitle' => __( 'Import an image from a URL', 'fifu' ),
        ) );
    }

    private static function fail( $message, $status = 400 ) {
        wp_send_json_error( array( 'message' => $message ), $status );
    }

    /**
     * Network safety: validate the original URL and let WordPress validate
     * *each redirect* via wp_safe_remote_get/reject_unsafe_urls.
     */
    private static function safe_url( $raw ) {
        if ( ! is_string( $raw ) || strlen( $raw ) > 2048 ) {
            return false;
        }
        $url = esc_url_raw( trim( $raw ), array( 'http', 'https' ) );
        if ( ! $url || ! preg_match( '~^https?://~i', $url ) ) {
            return false;
        }
        return wp_http_validate_url( $url ) ? $url : false;
    }

    private static function fail_download( $temporary_file, $message ) {
        if ( is_file( $temporary_file ) ) {
            wp_delete_file( $temporary_file );
        }
        self::fail( $message );
    }

    private static function allowed_mimes() {
        $allowed = array(
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        );
        // WordPress installations that accept AVIF can import AVIF too.
        $avif = wp_check_filetype( 'remote-image.avif' );
        if ( isset( $avif['type'] ) && 'image/avif' === $avif['type'] ) {
            $allowed['image/avif'] = 'avif';
        }
        return $allowed;
    }

    public static function ajax_fetch() {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) {
            self::fail( __( 'You cannot upload files.', 'fifu' ), 403 );
        }

        $post_id = isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $featured = isset( $_POST['mode'] ) && 'featured' === sanitize_key( wp_unslash( $_POST['mode'] ) );
        if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
            self::fail( __( 'You cannot edit this post.', 'fifu' ), 403 );
        }
        if ( $featured && ( ! $post_id || ! post_type_supports( get_post_type( $post_id ), 'thumbnail' ) ) ) {
            self::fail( __( 'This post does not support featured images.', 'fifu' ) );
        }

        $url = self::safe_url( isset( $_POST['url'] ) ? wp_unslash( $_POST['url'] ) : '' );
        if ( ! $url ) {
            self::fail( __( 'Please provide a valid public HTTP or HTTPS image URL.', 'fifu' ) );
        }

        $temp = wp_tempnam( 'hashe-from-url' );
        if ( ! $temp ) {
            self::fail( __( 'Could not create a temporary file.', 'fifu' ), 500 );
        }
        $attachment_id = 0;
        try {
            // Stream directly to disk and stop after 10 MiB + 1 byte. No
            // misleading HEAD/Content-Length assumption or oversized download.
            $response = wp_safe_remote_get( $url, array(
                'timeout'             => 25,
                'redirection'         => 3,
                'reject_unsafe_urls'  => true,
                'stream'              => true,
                'filename'            => $temp,
                'limit_response_size' => self::MAX_BYTES + 1,
                'headers'             => array( 'Accept' => 'image/avif,image/webp,image/png,image/jpeg,image/gif' ),
            ) );
            if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
                self::fail_download( $temp, __( 'Unable to download the image from that URL.', 'fifu' ) );
            }
            clearstatcache( true, $temp );
            $size = is_file( $temp ) ? filesize( $temp ) : 0;
            if ( ! $size || $size > self::MAX_BYTES ) {
                self::fail_download( $temp, __( 'Image is empty or exceeds the 10 MB limit.', 'fifu' ) );
            }

            // Validate the real binary, not just the URL suffix or HTTP header.
            $info = @getimagesize( $temp );
            $mime = is_array( $info ) && ! empty( $info['mime'] ) ? strtolower( $info['mime'] ) : '';
            $allowed = self::allowed_mimes();
            if ( ! isset( $allowed[ $mime ] ) ) {
                self::fail_download( $temp, __( 'Only supported JPEG, PNG, GIF, WebP or AVIF images can be imported.', 'fifu' ) );
            }

            $path = wp_parse_url( $url, PHP_URL_PATH );
            $stem = is_string( $path ) ? pathinfo( urldecode( $path ), PATHINFO_FILENAME ) : '';
            $stem = sanitize_file_name( $stem );
            if ( ! $stem || '.' === $stem ) {
                $stem = 'remote-image';
            }
            $filename = $stem . '.' . $allowed[ $mime ];
            $file = array(
                'name'     => $filename,
                'tmp_name' => $temp,
                'size'     => $size,
                'type'     => $mime,
                'error'    => 0,
            );
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachment_id = media_handle_sideload( $file, $post_id );
            if ( is_wp_error( $attachment_id ) ) {
                self::fail_download( $temp, $attachment_id->get_error_message() );
            }
        } finally {
            // media_handle_sideload normally moves the file. Cleanup on any
            // early failure or a failed/malformed sideload.
            if ( is_file( $temp ) ) {
                wp_delete_file( $temp );
            }
        }

        // Assignment is deliberately opt-in. Generic media imports never
        // change a post's existing featured image.
        if ( $featured && $post_id ) {
            if ( ! set_post_thumbnail( $post_id, $attachment_id ) ) {
                // Attachment exists, but assignment should not be reported as
                // successfully completed if the post lacks an image.
                self::fail( __( 'Image imported but featured image could not be set.', 'fifu' ) );
            }
        }
        $image = wp_prepare_attachment_for_js( $attachment_id );
        if ( ! is_array( $image ) ) {
            self::fail( __( 'Image imported but its Media Library record could not be displayed.', 'fifu' ) );
        }

        $html = '';
        if ( $featured && $post_id ) {
            require_once ABSPATH . 'wp-admin/includes/post.php';
            $html = _wp_post_thumbnail_html( $attachment_id, $post_id );
        }
        wp_send_json_success( array(
            'id'         => $attachment_id,
            'attachment' => $image,
            'featured'   => $featured,
            'html'       => $html,
        ) );
    }
}
FIFU_Plugin::init();

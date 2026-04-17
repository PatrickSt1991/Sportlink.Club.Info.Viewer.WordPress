<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCV_Assets {

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_frontend' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin' ] );
        add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_block_editor' ] );
    }

    /**
     * Register (but do NOT enqueue) the Vue bundle on the frontend.
     * Shortcodes will enqueue on demand.
     */
    public static function register_frontend() {
        $js  = SCV_PLUGIN_URL . 'assets/dist/sportlink-viewer.js';
        $css = SCV_PLUGIN_URL . 'assets/dist/sportlink-viewer.css';

        wp_register_script(
            'scv-viewer',
            $js,
            [],
            SCV_VERSION,
            true   // footer
        );

        wp_register_style(
            'scv-viewer',
            $css,
            [],
            SCV_VERSION
        );
    }

    /**
     * Enqueue admin-specific assets on the plugin settings page.
     */
    public static function enqueue_admin( $hook ) {
        if ( strpos( $hook, 'sportlink-club-viewer' ) === false ) {
            return;
        }

        // WordPress built-ins
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_media();

        // Plugin admin assets
        wp_enqueue_style(
            'scv-admin',
            SCV_PLUGIN_URL . 'admin/css/admin.css',
            [],
            SCV_VERSION
        );

        wp_enqueue_script(
            'scv-admin',
            SCV_PLUGIN_URL . 'admin/js/admin.js',
            [ 'jquery', 'wp-color-picker' ],
            SCV_VERSION,
            true
        );

        $conn_status_opt = get_option( 'scv_connection_status', [] );
        wp_localize_script( 'scv-admin', 'scvAdmin', [
            'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'scv_admin_nonce' ),
            'debugMode'        => (bool) get_option( 'scv_debug_mode', 0 ),
            'connectionStatus' => is_array( $conn_status_opt ) ? ( $conn_status_opt['status'] ?? 'unknown' ) : 'unknown',
        ] );
    }

    public static function enqueue_block_editor() {
        $block_js = SCV_PLUGIN_DIR . 'blocks/sportlink-viewer.js';
        if ( ! file_exists( $block_js ) ) {
            return;
        }
        wp_enqueue_script(
            'scv-block',
            SCV_PLUGIN_URL . 'blocks/sportlink-viewer.js',
            [ 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element' ],
            SCV_VERSION,
            true
        );
    }
}

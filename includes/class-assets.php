<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCV_Assets {

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_frontend' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin' ] );
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

        wp_localize_script( 'scv-admin', 'scvAdmin', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'scv_admin_nonce' ),
            'debugMode' => (bool) get_option( 'scv_debug_mode', 0 ),
        ] );
    }
}

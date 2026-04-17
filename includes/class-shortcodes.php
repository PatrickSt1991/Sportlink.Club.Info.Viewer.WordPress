<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCV_Shortcodes {

    public static function init() {
        add_shortcode( 'sportlink_match_display',    [ __CLASS__, 'render_match_display' ] );
        add_shortcode( 'sportlink_prematch_display', [ __CLASS__, 'render_prematch_display' ] );
        add_shortcode( 'sportlink_standing_display', [ __CLASS__, 'render_standing_display' ] );
    }

    /**
     * [sportlink_match_display]
     * Shows match schedule + results (auto-switches if setting is enabled).
     */
    public static function render_match_display( $atts ) {
        return self::render( 'match-display' );
    }

    /**
     * [sportlink_prematch_display]
     * Shows pre-match room/field information.
     */
    public static function render_prematch_display( $atts ) {
        return self::render( 'prematch-display' );
    }

    /**
     * [sportlink_standing_display]
     * Shows the league standings for the configured team.
     */
    public static function render_standing_display( $atts ) {
        return self::render( 'standing-display' );
    }

    private static function render( string $app_type ): string {
        // Check if built assets exist
        $js_path  = SCV_PLUGIN_DIR . 'assets/dist/sportlink-viewer.js';
        $css_path = SCV_PLUGIN_DIR . 'assets/dist/sportlink-viewer.css';

        if ( ! file_exists( $js_path ) ) {
            return '<div class="scv-notice">'
                . esc_html__( 'Sportlink Club Viewer: de Vue-app is nog niet gebouwd. Voer `npm run build` uit in de vue-app map.', 'sportlink-club-viewer' )
                . '</div>';
        }

        // Enqueue Vue bundle (registered in SCV_Assets)
        wp_enqueue_script( 'scv-viewer' );
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'scv-viewer' );
        }

        $config    = SCV_Admin::build_vue_config( $app_type );
        $id        = 'scv-' . sanitize_key( $app_type );
        $config_id = 'scv-config-' . sanitize_key( $app_type );

        // JSON_HEX_TAG escapes < and > so a URL containing </script> can never
        // break the surrounding script tag.
        $config_json = wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP );

        // Deliver config the WordPress-native way: a <script type="application/json">
        // element embedded directly in the shortcode output.  This is the same
        // pattern WordPress itself uses for block-editor data and emoji settings —
        // it sits in the page HTML regardless of script-loading hook order, is never
        // executed by the browser, and is trivially read with JSON.parse().
        //
        // wp_localize_script is kept as an opportunistic fallback; if it works on
        // the current host it sets window.scvConfig — if not, the JSON element wins.
        wp_localize_script( 'scv-viewer', 'scvConfig', $config );

        return '<script type="application/json" id="' . esc_attr( $config_id ) . '">'
            . $config_json
            . '</script>'
            . '<div id="' . esc_attr( $id ) . '" class="scv-display-wrapper"></div>';
    }
}

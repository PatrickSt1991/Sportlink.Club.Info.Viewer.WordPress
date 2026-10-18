<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCV_Shortcodes {

    public static function init() {
        add_shortcode( 'sportlink_match_display',       [ __CLASS__, 'render_match_display' ] );
        add_shortcode( 'sportlink_prematch_display',    [ __CLASS__, 'render_prematch_display' ] );
        add_shortcode( 'sportlink_standing_display',    [ __CLASS__, 'render_standing_display' ] );
        add_shortcode( 'sportlink_standings_overview', [ __CLASS__, 'render_standings_overview' ] );
    }

    /**
     * [sportlink_match_display]
     * Shows match schedule + results (auto-switches if setting is enabled).
     */
    public static function render_match_display( $atts ) {
        $a = shortcode_atts( [ 'days' => '', 'results_days' => '', 'height' => '', 'switch' => '', 'sponsors' => '' ], $atts, 'sportlink_match_display' );
        return self::render( 'match-display', self::overrides( $a ) );
    }

    /**
     * [sportlink_prematch_display]
     * Shows pre-match room/field information.
     */
    public static function render_prematch_display( $atts ) {
        $a = shortcode_atts( [ 'height' => '', 'refresh' => '', 'sponsors' => '' ], $atts, 'sportlink_prematch_display' );
        return self::render( 'prematch-display', self::overrides( $a ) );
    }

    /**
     * [sportlink_standing_display]
     * Shows the league standings for the configured team.
     */
    public static function render_standing_display( $atts ) {
        $a = shortcode_atts( [ 'team' => '', 'pool' => '', 'height' => '', 'sponsors' => '' ], $atts, 'sportlink_standing_display' );
        return self::render( 'standing-display', self::overrides( $a ) );
    }

    /**
     * [sportlink_standings_overview]
     * Shows multiple league standings side-by-side, one per configured elftal.
     */
    public static function render_standings_overview( $atts ) {
        $a = shortcode_atts( [ 'columns' => '', 'full_width' => '', 'height' => '' ], $atts, 'sportlink_standings_overview' );
        return self::render( 'standings-overview', self::overrides( $a ) );
    }

    /**
     * Map shortcode attributes to config keys. Empty attributes keep the saved setting.
     *
     *   days="14" results_days="30" height="700" refresh="10" switch="no" sponsors="no"
     *   team="123" pool="456" columns="3" full_width="no"
     */
    private static function overrides( array $atts ): array {
        $bool = function ( $v ) {
            return in_array( strtolower( (string) $v ), [ '1', 'yes', 'ja', 'true', 'on' ], true );
        };
        $map = [
            'days'         => [ 'programmaDagen',     'absint' ],
            'results_days' => [ 'uitslagDagen',       'absint' ],
            'height'       => [ 'displayHeight',      'absint' ],
            'refresh'      => [ 'prematchRefresh',    'absint' ],
            'switch'       => [ 'enableScreenSwitch', $bool ],
            'sponsors'     => [ 'activeSponsors',     $bool ],
            'team'         => [ 'standingTeamId',     'sanitize_text_field' ],
            'pool'         => [ 'standingPoolId',     'sanitize_text_field' ],
            'columns'      => [ 'overviewColumns',    'sanitize_text_field' ],
            'full_width'   => [ 'overviewFullWidth',  $bool ],
        ];

        $overrides = [];
        foreach ( $map as $att => $def ) {
            if ( ! isset( $atts[ $att ] ) || $atts[ $att ] === '' ) continue;
            $overrides[ $def[0] ] = call_user_func( $def[1], $atts[ $att ] );
        }
        return $overrides;
    }

    private static function render( string $app_type, array $overrides = [] ): string {
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

        $config    = SCV_Admin::build_vue_config( $app_type, $overrides );
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

<?php
/**
 * Plugin Name:       Sportlink Club Viewer
 * Plugin URI:        https://github.com/PatrickSt1991/Sportlink.Club.Info.Viewer.WordPress
 * Description:       Toon wedstrijdprogramma, uitslagen, kleedkamerindeling en standen van Sportlink/Nevobo op je WordPress-site via shortcodes of een blok.
 * Version:           1.1.1
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Patrick Stel
 * Author URI:        https://github.com/PatrickSt1991
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       sportlink-club-viewer
 * Domain Path:       /languages
 * Update URI:        https://github.com/PatrickSt1991/Sportlink.Club.Info.Viewer.WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SCV_VERSION',     '1.1.1' );
define( 'SCV_PLUGIN_FILE', __FILE__ );
define( 'SCV_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'SCV_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

require_once SCV_PLUGIN_DIR . 'includes/class-sportlink-client.php';
require_once SCV_PLUGIN_DIR . 'includes/class-cache.php';
require_once SCV_PLUGIN_DIR . 'includes/class-rest.php';
require_once SCV_PLUGIN_DIR . 'includes/class-updater.php';
require_once SCV_PLUGIN_DIR . 'includes/class-assets.php';
require_once SCV_PLUGIN_DIR . 'includes/class-admin.php';
require_once SCV_PLUGIN_DIR . 'includes/class-ajax.php';
require_once SCV_PLUGIN_DIR . 'includes/class-shortcodes.php';

add_action( 'plugins_loaded', function () {
    load_plugin_textdomain( 'sportlink-club-viewer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    SCV_Cache::init();
    SCV_REST::init();
    SCV_Updater::init();
    SCV_Assets::init();
    SCV_Admin::init();
    SCV_Ajax::init();
    SCV_Shortcodes::init();
} );

add_action( 'init', function () {
    register_block_type( 'sportlink-club-viewer/display', [
        'attributes'      => [
            'displayType' => [ 'type' => 'string', 'default' => 'match' ],
        ],
        'render_callback' => function ( $attrs ) {
            switch ( $attrs['displayType'] ?? 'match' ) {
                case 'prematch':           return do_shortcode( '[sportlink_prematch_display]' );
                case 'standings':          return do_shortcode( '[sportlink_standing_display]' );
                case 'standings-overview': return do_shortcode( '[sportlink_standings_overview]' );
                default:                   return do_shortcode( '[sportlink_match_display]' );
            }
        },
    ] );
} );

register_activation_hook( __FILE__, function () {
    // Set default options on first activation
    $defaults = [
        'scv_cache_salt'           => (string) time(),
        'scv_programma_dagen'      => 7,
        'scv_uitslag_dagen'        => 7,
        'scv_prematch_refresh'     => 15,
        'scv_display_height'       => 0,
        'scv_scroll_speed'         => 2,
        'scv_enable_screen_switch' => 1,
        'scv_active_sponsors'      => 0,
        'scv_colors'               => [
            'leftBoxColor'     => '#b40808',
            'leftBoxText'      => '#ffffff',
            'leftMidBoxColor'  => '#000000',
            'leftMidBoxText'   => '#ffffff',
            'midBoxColor'      => '#de0b0b',
            'midBoxText'       => '#ffffff',
            'rightMidBoxColor' => '#000000',
            'rightMidBoxText'  => '#ffffff',
            'rightBoxColor'    => '#b40808',
            'rightBoxText'     => '#ffffff',
        ],
        'scv_sponsors'            => [],
        'scv_enable_standings'    => 0,
        'scv_standing_columns'    => [
            'totalMatches' => 1, 'won' => 1, 'draw' => 1, 'lost' => 1,
            'goalsFor' => 1, 'goalsAgainst' => 1, 'goalsDiff' => 1, 'points' => 1,
        ],
        'scv_standing_team_id'    => '',
        'scv_standing_team_name'  => '',
        'scv_standing_pool_id'    => '',
        'scv_standing_pool_name'  => '',
        'scv_standings_list'      => [],
        'scv_overview_columns'    => 'auto',
        'scv_overview_full_width' => 1,
        'scv_own_team_colors'     => [ 'bg' => '#1a5c1a', 'text' => '#ffffff' ],
        'scv_connection_status'   => [ 'status' => 'unknown', 'time' => 0, 'message' => '' ],
        'scv_layout'   => [
            'leftWidth'       => 2,  'leftMidWidth'    => 9, 'midWidth'        => 4,
            'rightMidWidth'   => 9,  'rightWidth'      => 3,
            'leftVisible'     => 1,  'leftMidVisible'  => 1, 'midVisible'      => 1,
            'rightMidVisible' => 1,  'rightVisible'    => 1, 'showLogos'       => 1,
        ],
    ];
    foreach ( $defaults as $key => $value ) {
        if ( false === get_option( $key ) ) {
            add_option( $key, $value );
        }
    }
} );

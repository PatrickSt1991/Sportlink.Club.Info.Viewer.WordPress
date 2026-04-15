<?php
/**
 * Plugin Name:       Sportlink Club Viewer
 * Plugin URI:        https://github.com/PatrickSt1991/Sportlink.Club.Info.Viewer
 * Description:       Toon wedstrijdprogramma, uitslagen en voorwedstrijdinformatie van Sportlink/Nevobo op je WordPress-site via shortcodes.
 * Version:           1.0.0
 * Author:            Patrick Stel
 * Author URI:        https://github.com/PatrickSt1991
 * License:           MIT
 * Text Domain:       sportlink-club-viewer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SCV_VERSION',     '1.0.0' );
define( 'SCV_PLUGIN_FILE', __FILE__ );
define( 'SCV_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'SCV_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

require_once SCV_PLUGIN_DIR . 'includes/class-assets.php';
require_once SCV_PLUGIN_DIR . 'includes/class-admin.php';
require_once SCV_PLUGIN_DIR . 'includes/class-ajax.php';
require_once SCV_PLUGIN_DIR . 'includes/class-shortcodes.php';

add_action( 'plugins_loaded', function () {
    SCV_Assets::init();
    SCV_Admin::init();
    SCV_Ajax::init();
    SCV_Shortcodes::init();
} );

register_activation_hook( __FILE__, function () {
    // Set default options on first activation
    $defaults = [
        'scv_programma_dagen'      => 7,
        'scv_uitslag_dagen'        => 7,
        'scv_prematch_refresh'     => 15,
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
        'scv_sponsors' => [],
    ];
    foreach ( $defaults as $key => $value ) {
        if ( false === get_option( $key ) ) {
            add_option( $key, $value );
        }
    }
} );

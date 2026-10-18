<?php
/**
 * Removes every option, transient and cached logo the plugin created.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE 'scv\\_%'
        OR option_name LIKE '\\_transient\\_scv\\_%'
        OR option_name LIKE '\\_transient\\_timeout\\_scv\\_%'"
);

$upload = wp_upload_dir();
$dir    = trailingslashit( $upload['basedir'] ) . 'scv-logos';
if ( is_dir( $dir ) ) {
    foreach ( (array) glob( $dir . '/*' ) as $file ) {
        @unlink( $file );
    }
    @rmdir( $dir );
}

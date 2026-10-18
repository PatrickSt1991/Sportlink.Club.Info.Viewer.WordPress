<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Plugin updates straight from GitHub releases, so clubs see new versions on
 * the normal WordPress "Plugins" screen and can update with one click.
 */
class SCV_Updater {

    const REPO      = 'PatrickSt1991/Sportlink.Club.Info.Viewer.WordPress';
    const TRANSIENT = 'scv_github_release';

    public static function init() {
        add_filter( 'pre_set_site_transient_update_plugins', [ __CLASS__, 'inject_update' ] );
        add_filter( 'plugins_api', [ __CLASS__, 'plugin_info' ], 20, 3 );
        add_filter( 'upgrader_source_selection', [ __CLASS__, 'fix_folder_name' ], 10, 4 );
        add_action( 'upgrader_process_complete', [ __CLASS__, 'flush' ], 10, 2 );
    }

    private static function basename(): string {
        return plugin_basename( SCV_PLUGIN_FILE );
    }

    private static function slug(): string {
        return dirname( self::basename() );
    }

    /** Latest release metadata from GitHub, cached for six hours. */
    public static function latest_release() {
        $cached = get_transient( self::TRANSIENT );
        if ( is_array( $cached ) ) {
            return ! empty( $cached ) ? $cached : null;
        }

        $response = wp_remote_get( 'https://api.github.com/repos/' . self::REPO . '/releases/latest', [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'sportlink-club-viewer/' . SCV_VERSION,
            ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( self::TRANSIENT, [], HOUR_IN_SECONDS );
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['tag_name'] ) ) {
            set_transient( self::TRANSIENT, [], HOUR_IN_SECONDS );
            return null;
        }

        $zip = '';
        foreach ( (array) ( isset( $body['assets'] ) ? $body['assets'] : [] ) as $asset ) {
            $name = isset( $asset['name'] ) ? $asset['name'] : '';
            if ( strpos( $name, 'Sportlink-Club-Viewer-Wordpress' ) === 0 && substr( $name, -4 ) === '.zip' ) {
                $zip = $asset['browser_download_url'];
                break;
            }
        }

        $release = [
            'version'   => ltrim( (string) $body['tag_name'], 'v' ),
            'zip'       => $zip,
            'url'       => isset( $body['html_url'] ) ? $body['html_url'] : 'https://github.com/' . self::REPO,
            'notes'     => isset( $body['body'] ) ? (string) $body['body'] : '',
            'published' => isset( $body['published_at'] ) ? (string) $body['published_at'] : '',
        ];
        set_transient( self::TRANSIENT, $release, 6 * HOUR_IN_SECONDS );
        return $release;
    }

    public static function inject_update( $transient ) {
        if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
            return $transient;
        }

        $release = self::latest_release();
        if ( ! $release || $release['zip'] === '' ) {
            return $transient;
        }

        $item = (object) [
            'id'           => 'github.com/' . self::REPO,
            'slug'         => self::slug(),
            'plugin'       => self::basename(),
            'new_version'  => $release['version'],
            'url'          => $release['url'],
            'package'      => $release['zip'],
            'icons'        => [],
            'banners'      => [],
            'requires'     => '5.9',
            'requires_php' => '7.4',
            'tested'       => '',
        ];

        if ( version_compare( $release['version'], SCV_VERSION, '>' ) ) {
            $transient->response[ self::basename() ] = $item;
            unset( $transient->no_update[ self::basename() ] );
        } else {
            $item->new_version = SCV_VERSION;
            $transient->no_update[ self::basename() ] = $item;
            unset( $transient->response[ self::basename() ] );
        }
        return $transient;
    }

    public static function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' || empty( $args->slug ) || $args->slug !== self::slug() ) {
            return $result;
        }

        $release = self::latest_release();
        if ( ! $release ) {
            return $result;
        }

        return (object) [
            'name'          => 'Sportlink Club Viewer',
            'slug'          => self::slug(),
            'version'       => $release['version'],
            'author'        => '<a href="https://github.com/PatrickSt1991">Patrick Stel</a>',
            'homepage'      => 'https://github.com/' . self::REPO,
            'download_link' => $release['zip'],
            'requires'      => '5.9',
            'requires_php'  => '7.4',
            'last_updated'  => $release['published'],
            'sections'      => [
                'description' => esc_html__( 'Toon wedstrijdprogramma, uitslagen, kleedkamerindeling en standen van Sportlink of Nevobo op je WordPress-site.', 'sportlink-club-viewer' ),
                'changelog'   => wpautop( esc_html( $release['notes'] ) ),
            ],
        ];
    }

    /**
     * GitHub zips extract to "Sportlink-Club-Viewer-Wordpress/"; rename to the
     * folder this installation actually uses so the update lands in place.
     */
    public static function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra ) {
        if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::basename() ) {
            return $source;
        }

        $expected = trailingslashit( $remote_source ) . self::slug();
        if ( untrailingslashit( $source ) === untrailingslashit( $expected ) ) {
            return $source;
        }

        global $wp_filesystem;
        if ( $wp_filesystem && $wp_filesystem->move( $source, $expected, true ) ) {
            return trailingslashit( $expected );
        }
        return $source;
    }

    public static function flush( $upgrader, $options ) {
        if ( isset( $options['type'] ) && $options['type'] === 'plugin' ) {
            delete_transient( self::TRANSIENT );
        }
    }
}

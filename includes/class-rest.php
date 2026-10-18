<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Public, read-only REST endpoints the front-end bundle consumes.
 *
 *   GET /wp-json/scv/v1/program            upcoming matches (raw upstream JSON)
 *   GET /wp-json/scv/v1/results            recent results
 *   GET /wp-json/scv/v1/prematch           today's home matches (kleedkamer/veld screen)
 *   GET /wp-json/scv/v1/facility/{id}      dressing rooms + field for one match (Sportlink Proxy)
 *   GET /wp-json/scv/v1/standings/{pool}   league table
 *   GET /wp-json/scv/v1/logo/{bucket}/{hash}  team logo (302 to the cached file)
 *   GET /wp-json/scv/v1/status             plugin status for diagnostics
 *
 * Responses are cached server-side (see SCV_Cache) so one upstream request
 * serves every visitor within the cache window.
 */
class SCV_REST {

    const NS = 'scv/v1';

    const TTL_PROGRAM   = 300;
    const TTL_RESULTS   = 600;
    const TTL_PREMATCH  = 30;
    const TTL_FACILITY  = 300;
    const TTL_STANDINGS = 900;

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes() {
        $public = [ 'permission_callback' => '__return_true', 'methods' => WP_REST_Server::READABLE ];

        register_rest_route( self::NS, '/program',  $public + [ 'callback' => [ __CLASS__, 'program' ] ] );
        register_rest_route( self::NS, '/results',  $public + [ 'callback' => [ __CLASS__, 'results' ] ] );
        register_rest_route( self::NS, '/prematch', $public + [ 'callback' => [ __CLASS__, 'prematch' ] ] );
        register_rest_route( self::NS, '/status',   $public + [ 'callback' => [ __CLASS__, 'status' ] ] );

        register_rest_route( self::NS, '/facility/(?P<id>[A-Za-z0-9_-]+)', $public + [
            'callback' => [ __CLASS__, 'facility' ],
            'args'     => [ 'id' => [ 'sanitize_callback' => 'sanitize_text_field' ] ],
        ] );
        register_rest_route( self::NS, '/standings/(?P<pool>[A-Za-z0-9_-]+)', $public + [
            'callback' => [ __CLASS__, 'standings' ],
            'args'     => [ 'pool' => [ 'sanitize_callback' => 'sanitize_text_field' ] ],
        ] );
        register_rest_route( self::NS, '/logo/(?P<bucket>[A-Za-z0-9._-]+)/(?P<hash>[A-Za-z0-9._-]+)', $public + [
            'callback' => [ __CLASS__, 'logo' ],
            'args'     => [
                'bucket' => [ 'sanitize_callback' => 'sanitize_text_field' ],
                'hash'   => [ 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static function settings(): array {
        return [
            'connection'      => (string) get_option( 'scv_connection_type', '' ),
            'label'           => (string) get_option( 'scv_game_type_label', '' ),
            'client_id'       => (string) get_option( 'scv_client_id', '' ),
            'club_id'         => (string) get_option( 'scv_club_id', '' ),
            'club_identifier' => (string) get_option( 'scv_club_identifier', '' ),
            'programma_dagen' => max( 1, (int) get_option( 'scv_programma_dagen', 7 ) ),
        ];
    }

    private static function not_configured() {
        return new WP_Error( 'scv_not_configured', __( 'De plugin is nog niet geconfigureerd.', 'sportlink-club-viewer' ), [ 'status' => 409 ] );
    }

    private static function creds_for( array $s ) {
        $creds = SCV_Sportlink_Client::get_app_credentials( $s['label'] );
        return $creds ? $creds : new WP_Error( 'scv_no_app_credentials', __( 'Geen API-gegevens voor dit sporttype.', 'sportlink-club-viewer' ), [ 'status' => 409 ] );
    }

    private static function respond( $data, bool $hit, int $max_age ) {
        if ( is_wp_error( $data ) ) {
            $status = $data->get_error_data();
            if ( ! is_array( $status ) || empty( $status['status'] ) ) {
                $data->add_data( [ 'status' => 502 ] );
            }
            return $data;
        }

        $response = new WP_REST_Response( $data );
        $response->header( 'Cache-Control', 'public, max-age=' . $max_age );
        $response->header( 'X-SCV-Cache', $hit ? 'HIT' : 'MISS' );
        return $response;
    }

    private static function redirect( string $url, int $max_age = DAY_IN_SECONDS ) {
        $response = new WP_REST_Response( null, 302 );
        $response->header( 'Location', $url );
        $response->header( 'Cache-Control', 'public, max-age=' . $max_age );
        return $response;
    }

    /**
     * Program JSON for the configured connection. $mode 'all' = home + away
     * for the programma screen, 'home' = home matches only (pre-match screen).
     */
    private static function load_program( array $s, string $mode ) {
        switch ( $s['connection'] ) {
            case 'Sportlink API':
                if ( $s['client_id'] === '' ) return self::not_configured();
                $query = $mode === 'home'
                    ? 'gebruiklokaleteamgegevens=NEE&eigenwedstrijden=JA&thuis=JA&uit=NEE'
                    : "gebruiklokaleteamgegevens=NEE&aantaldagen={$s['programma_dagen']}&eigenwedstrijden=JA&thuis=JA&uit=JA";
                return SCV_Sportlink_Client::get_json( "https://data.sportlink.com/programma?{$query}&client_id=" . rawurlencode( $s['client_id'] ) );

            case 'Nevobo Proxy':
                if ( $s['club_identifier'] === '' ) return self::not_configured();
                return SCV_Sportlink_Client::get_json( 'https://api.nevobo.nl/v1/competitie/wedstrijden/programma?vereniging=' . rawurlencode( $s['club_identifier'] ) );

            case 'Sportlink Proxy':
                if ( $s['club_id'] === '' ) return self::not_configured();
                $creds = self::creds_for( $s );
                if ( is_wp_error( $creds ) ) return $creds;
                $url = SCV_Sportlink_Client::base_url( $creds ) . '/entity/common/memberportal/app/club/ClubProgram?v=3&ClubId=' . rawurlencode( $s['club_id'] );
                return SCV_Sportlink_Client::sportlink_get( $url, $creds, '2' );
        }
        return self::not_configured();
    }

    private static function load_results( array $s ) {
        switch ( $s['connection'] ) {
            case 'Sportlink API':
                if ( $s['client_id'] === '' ) return self::not_configured();
                return SCV_Sportlink_Client::get_json( 'https://data.sportlink.com/uitslagen?gebruiklokaleteamgegevens=NEE&thuis=JA&uit=JA&client_id=' . rawurlencode( $s['client_id'] ) );

            case 'Nevobo Proxy':
                if ( $s['club_identifier'] === '' ) return self::not_configured();
                return SCV_Sportlink_Client::get_json( 'https://api.nevobo.nl/v1/competitie/wedstrijden/resultaat?vereniging=' . rawurlencode( $s['club_identifier'] ) );

            case 'Sportlink Proxy':
                if ( $s['club_id'] === '' ) return self::not_configured();
                $creds = self::creds_for( $s );
                if ( is_wp_error( $creds ) ) return $creds;
                $url = SCV_Sportlink_Client::base_url( $creds ) . '/entity/common/memberportal/app/club/ClubMatchResults?v=2&ClubId=' . rawurlencode( $s['club_id'] );
                return SCV_Sportlink_Client::sportlink_get( $url, $creds, '2' );
        }
        return self::not_configured();
    }

    private static function load_standings( array $s, string $pool ) {
        switch ( $s['connection'] ) {
            case 'Sportlink API':
                if ( $s['client_id'] === '' ) return self::not_configured();
                return SCV_Sportlink_Client::get_json( 'https://data.sportlink.com/poulestand?poulecode=' . rawurlencode( $pool ) . '&gebruiklokaleteamgegevens=NEE&client_id=' . rawurlencode( $s['client_id'] ) );

            case 'Sportlink Proxy':
                $creds = self::creds_for( $s );
                if ( is_wp_error( $creds ) ) return $creds;
                $url = SCV_Sportlink_Client::base_url( $creds ) . '/entity/common/memberportal/app/pool/PoolCompetitionData?v=2&PoolId=' . rawurlencode( $pool ) . '&GetTopscorers=false';
                return SCV_Sportlink_Client::sportlink_get( $url, $creds, '2' );
        }
        return new WP_Error( 'scv_unsupported', __( 'Standen zijn alleen beschikbaar via Sportlink API of Sportlink Proxy.', 'sportlink-club-viewer' ), [ 'status' => 400 ] );
    }

    // ── Endpoints ────────────────────────────────────────────────────────────

    public static function program() {
        $s = self::settings();
        list( $data, $hit ) = SCV_Cache::remember( 'program', [ $s['connection'], $s['programma_dagen'] ], self::TTL_PROGRAM, function () use ( $s ) {
            return self::load_program( $s, 'all' );
        } );
        return self::respond( $data, $hit, 120 );
    }

    public static function results() {
        $s = self::settings();
        list( $data, $hit ) = SCV_Cache::remember( 'results', [ $s['connection'] ], self::TTL_RESULTS, function () use ( $s ) {
            return self::load_results( $s );
        } );
        return self::respond( $data, $hit, 120 );
    }

    public static function prematch() {
        $s = self::settings();
        list( $data, $hit ) = SCV_Cache::remember( 'prematch', [ $s['connection'] ], self::TTL_PREMATCH, function () use ( $s ) {
            return self::load_program( $s, 'home' );
        } );
        return self::respond( $data, $hit, 15 );
    }

    public static function facility( WP_REST_Request $request ) {
        $s  = self::settings();
        $id = (string) $request['id'];

        if ( $s['connection'] !== 'Sportlink Proxy' ) {
            return new WP_Error( 'scv_unsupported', __( 'Alleen beschikbaar via Sportlink Proxy.', 'sportlink-club-viewer' ), [ 'status' => 400 ] );
        }

        list( $data, $hit ) = SCV_Cache::remember( 'facility', [ $id ], self::TTL_FACILITY, function () use ( $s, $id ) {
            $creds = self::creds_for( $s );
            if ( is_wp_error( $creds ) ) return $creds;
            $url = SCV_Sportlink_Client::base_url( $creds ) . '/entity/common/memberportal/app/match/MatchFacility?v=3&PublicMatchId=' . rawurlencode( $id );
            return SCV_Sportlink_Client::sportlink_get( $url, $creds, '3' );
        } );
        return self::respond( $data, $hit, 60 );
    }

    public static function standings( WP_REST_Request $request ) {
        $s    = self::settings();
        $pool = (string) $request['pool'];

        list( $data, $hit ) = SCV_Cache::remember( 'standings', [ $s['connection'], $pool ], self::TTL_STANDINGS, function () use ( $s, $pool ) {
            return self::load_standings( $s, $pool );
        } );
        return self::respond( $data, $hit, 300 );
    }

    public static function logo( WP_REST_Request $request ) {
        $bucket = (string) $request['bucket'];
        $hash   = (string) $request['hash'];

        $cached = SCV_Cache::logo_url( $bucket, $hash );
        if ( $cached ) {
            return self::redirect( $cached );
        }

        $fallback = SCV_PLUGIN_URL . 'assets/img/no_image.svg';
        $s        = self::settings();
        if ( $s['connection'] !== 'Sportlink Proxy' ) {
            return self::redirect( $fallback, HOUR_IN_SECONDS );
        }

        $creds = self::creds_for( $s );
        if ( is_wp_error( $creds ) ) {
            return self::redirect( $fallback, HOUR_IN_SECONDS );
        }

        $image = SCV_Sportlink_Client::sportlink_get_binary( "https://binaries.sportlink.com/{$bucket}/{$hash}", $creds );
        if ( is_wp_error( $image ) || $image['body'] === '' ) {
            return self::redirect( $fallback, HOUR_IN_SECONDS );
        }

        $url = SCV_Cache::store_logo( $bucket, $hash, $image['body'], $image['type'] );
        if ( $url ) {
            return self::redirect( $url );
        }

        // Uploads directory not writable: serve the bytes directly.
        nocache_headers();
        header( 'Content-Type: ' . ( $image['type'] ?: 'image/png' ) );
        header( 'Content-Length: ' . strlen( $image['body'] ) );
        echo $image['body']; // phpcs:ignore WordPress.Security.EscapeOutput
        exit;
    }

    public static function status() {
        $s = self::settings();
        $configured = $s['connection'] !== '' && ( $s['client_id'] !== '' || $s['club_id'] !== '' || $s['club_identifier'] !== '' );
        return self::respond( [
            'version'    => SCV_VERSION,
            'connection' => $s['connection'],
            'configured' => $configured,
            'cache'      => [
                'program'   => self::TTL_PROGRAM,
                'results'   => self::TTL_RESULTS,
                'prematch'  => self::TTL_PREMATCH,
                'standings' => self::TTL_STANDINGS,
            ],
        ], false, 0 );
    }
}

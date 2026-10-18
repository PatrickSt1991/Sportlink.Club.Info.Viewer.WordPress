<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Server-side client for the Sportlink and Nevobo APIs.
 *
 * Every upstream call of the plugin goes through this class. Credentials and
 * OAuth tokens never leave the server: the browser only talks to the plugin's
 * own REST endpoints (see SCV_REST).
 */
class SCV_Sportlink_Client {

    const APP_SUFFIX   = '/6.26.0-2025017636 android SM-N976N/samsung/25 (6.26.0)';
    const TOKEN_MARGIN = 300; // seconds before expiry at which we refresh

    // ── Static configuration ─────────────────────────────────────────────────

    /**
     * Sportlink "app" credentials per sport (mirrors the viewer's config.js).
     */
    public static function get_app_credentials( string $label ) {
        $map = [
            'voetbal'          => [ 'type' => 'Voetbal',          'client_id' => 'oCuV9oozaaz8zee',      'secret' => 'eep7Shoo7i',       'instance' => 'KNVB',  'userAgent' => 'voetbalnl', 'apiUrl' => 'vnl' ],
            'waterpolo'        => [ 'type' => 'Waterpolo',        'client_id' => '4BtKnhojt4MSnRScVak5', 'secret' => 'vLD8uPHOgIHJjAj9', 'instance' => 'KNZB',  'userAgent' => 'knzb',      'apiUrl' => 'sportlinked' ],
            'hockey belgië'    => [ 'type' => 'Hockey België',    'client_id' => 'YqTh94xQBASRCtTmpa0b', 'secret' => '15T74iIa011VVoAm', 'instance' => 'KBHB',  'userAgent' => 'kbhb',      'apiUrl' => 'sportlinked' ],
            'soft- en honkbal' => [ 'type' => 'Soft- en Honkbal', 'client_id' => '0SaoFKzAVgn3cTzxUsk8', 'secret' => 'H1LRQnWYxm10YA87', 'instance' => 'KNBSB', 'userAgent' => 'knbsb',     'apiUrl' => 'sportlinked' ],
            'basketbal'        => [ 'type' => 'Basketbal',        'client_id' => '4boXZaODcf1A5ffb7zMl', 'secret' => 'netkEQKiWAsFEwl3', 'instance' => 'NBB',   'userAgent' => 'nbb',       'apiUrl' => 'sportlinked' ],
            'handbal'          => [ 'type' => 'Handbal',          'client_id' => 'JUian2haoKqIripvaios', 'secret' => '9BdMs5h9jvr9Agte', 'instance' => 'NHV',   'userAgent' => 'nhv',       'apiUrl' => 'sportlinked' ],
            'korfbal'          => [ 'type' => 'Korfbal',          'client_id' => 'SdJSHVPuWzK066Mu28ki', 'secret' => 'j2OInPPCmWJ0VA2W', 'instance' => 'KNKV',  'userAgent' => 'knkv',      'apiUrl' => 'sportlinked' ],
        ];
        $key = mb_strtolower( trim( $label ) );
        return isset( $map[ $key ] ) ? $map[ $key ] : null;
    }

    /** Shared read-only account used when "fake credentials" is enabled. */
    public static function get_fake_credentials(): array {
        return [ 'username' => 'rxxnrextolzwlqsspy@hthlm.com', 'password' => 'test1234' ];
    }

    /** The Cloudflare worker that fronts the Sportlink app API (it rejects plain server IPs). */
    public static function cors_proxy(): string {
        return (string) apply_filters( 'scv_cors_proxy', 'https://cors-proxy.clubinfoproxy.workers.dev/proxy?url=' );
    }

    public static function base_url( array $creds ): string {
        return "https://app-{$creds['apiUrl']}-production.sportlink.com";
    }

    /**
     * Username/password to log in with, from the saved options unless overridden.
     *
     * @param array $override Optional ['username' => …, 'password' => …, 'fake' => bool].
     */
    public static function account( array $override = [] ): array {
        $fake = array_key_exists( 'fake', $override ) ? (bool) $override['fake'] : (bool) get_option( 'scv_fake_credentials', 0 );
        if ( $fake ) {
            return self::get_fake_credentials();
        }
        return [
            'username' => isset( $override['username'] ) && $override['username'] !== '' ? (string) $override['username'] : (string) get_option( 'scv_username', '' ),
            'password' => isset( $override['password'] ) && $override['password'] !== '' ? (string) $override['password'] : (string) get_option( 'scv_password', '' ),
        ];
    }

    // ── OAuth ────────────────────────────────────────────────────────────────

    private static function token_key( array $creds, string $username ): string {
        return 'scv_token_' . md5( $creds['instance'] . '|' . $username );
    }

    /**
     * Returns a cached, still-valid token; refreshes or logs in when needed.
     *
     * @return array|WP_Error ['access_token', 'refresh_token', 'expires_at', 'cookies']
     */
    public static function get_token( array $creds, array $account, bool $force = false ) {
        if ( $account['username'] === '' || $account['password'] === '' ) {
            return new WP_Error( 'scv_no_credentials', __( 'Gebruikersnaam en wachtwoord ontbreken.', 'sportlink-club-viewer' ) );
        }

        $key    = self::token_key( $creds, $account['username'] );
        $cached = get_transient( $key );

        if ( ! $force && is_array( $cached ) && ! empty( $cached['access_token'] ) && $cached['expires_at'] - time() > self::TOKEN_MARGIN ) {
            return $cached;
        }

        if ( is_array( $cached ) && ! empty( $cached['refresh_token'] ) ) {
            $token = self::token_request( $creds, [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $cached['refresh_token'],
            ] );
            if ( ! is_wp_error( $token ) ) {
                self::store_token( $key, $token );
                return $token;
            }
        }

        $token = self::token_request( $creds, [
            'grant_type' => 'password',
            'username'   => $account['username'],
            'password'   => $account['password'],
        ] );
        if ( is_wp_error( $token ) ) {
            delete_transient( $key );
            return $token;
        }

        self::store_token( $key, $token );
        return $token;
    }

    private static function store_token( string $key, array $token ) {
        // Keep the record a day past expiry so the refresh token can still be used.
        set_transient( $key, $token, max( 60, $token['expires_at'] - time() + DAY_IN_SECONDS ) );
    }

    private static function token_request( array $creds, array $body ) {
        $body['client_id'] = $creds['client_id'];
        $body['secret']    = $creds['secret'];

        $response = wp_remote_post( self::base_url( $creds ) . '/oauth/token', [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent'   => 'okhttp/4.12.0',
            ],
            'body'    => $body,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 401 || empty( $data['access_token'] ) ) {
            return new WP_Error( 'scv_login_failed', __( 'Inloggen bij Sportlink mislukt. Controleer gebruikersnaam en wachtwoord.', 'sportlink-club-viewer' ), [ 'status' => $code ] );
        }

        // The token endpoint sets session cookies that the data endpoints expect back.
        $pairs = [];
        foreach ( (array) wp_remote_retrieve_cookies( $response ) as $cookie ) {
            $pairs[] = $cookie->name . '=' . $cookie->value;
        }

        return [
            'access_token'  => (string) $data['access_token'],
            'refresh_token' => isset( $data['refresh_token'] ) ? (string) $data['refresh_token'] : '',
            'expires_at'    => time() + (int) ( isset( $data['expires_in'] ) ? $data['expires_in'] : 3600 ),
            'cookies'       => implode( '; ', $pairs ),
        ];
    }

    // ── Requests ─────────────────────────────────────────────────────────────

    private static function headers( array $creds, array $token, string $version ): array {
        return array_filter( [
            'Authorization'     => 'Bearer ' . $token['access_token'],
            'X-Real-User-Agent' => 'sportlink-app-' . $creds['userAgent'] . self::APP_SUFFIX,
            'X-Navajo-Instance' => $creds['instance'],
            'X-Navajo-Locale'   => 'nl',
            'X-Navajo-Version'  => $version,
            'Accept'            => '*/*',
            'Cookie'            => $token['cookies'] ?: null,
        ] );
    }

    /**
     * Authenticated GET on the Sportlink app API (through the proxy), with one
     * re-login attempt on 401. Returns the raw response or WP_Error.
     */
    private static function authed_request( string $url, array $creds, array $account, string $version ) {
        $token = self::get_token( $creds, $account );
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $args = [ 'timeout' => 20, 'user-agent' => 'okhttp/4.12.0' ];

        for ( $attempt = 1; $attempt <= 2; $attempt++ ) {
            $args['headers'] = self::headers( $creds, $token, $version );
            $response        = wp_remote_get( self::cors_proxy() . rawurlencode( $url ), $args );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );
            if ( $code === 401 && $attempt === 1 ) {
                $token = self::get_token( $creds, $account, true );
                if ( is_wp_error( $token ) ) {
                    return $token;
                }
                continue;
            }

            if ( $code >= 400 ) {
                return new WP_Error( 'scv_upstream_error', sprintf( __( 'Sportlink antwoordde met HTTP %d.', 'sportlink-club-viewer' ), $code ), [ 'status' => 502 ] );
            }

            return $response;
        }

        return new WP_Error( 'scv_upstream_error', __( 'Sportlink is niet bereikbaar.', 'sportlink-club-viewer' ), [ 'status' => 502 ] );
    }

    /**
     * Authenticated JSON GET. Returns the decoded array or WP_Error.
     */
    public static function sportlink_get( string $url, array $creds, string $version = '2', ?array $account = null ) {
        $response = self::authed_request( $url, $creds, $account === null ? self::account() : $account, $version );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            return new WP_Error( 'scv_bad_json', __( 'Ongeldig antwoord van Sportlink.', 'sportlink-club-viewer' ), [ 'status' => 502 ] );
        }
        return $data;
    }

    /**
     * Authenticated binary GET (team logos). Returns ['body' => …, 'type' => mime] or WP_Error.
     */
    public static function sportlink_get_binary( string $url, array $creds, ?array $account = null ) {
        $response = self::authed_request( $url, $creds, $account === null ? self::account() : $account, '1' );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        return [
            'body' => wp_remote_retrieve_body( $response ),
            'type' => (string) wp_remote_retrieve_header( $response, 'content-type' ),
        ];
    }

    /**
     * Plain JSON GET for the public endpoints (data.sportlink.com, api.nevobo.nl).
     */
    public static function get_json( string $url, int $timeout = 15 ) {
        $response = wp_remote_get( $url, [ 'timeout' => $timeout, 'headers' => [ 'Accept' => 'application/json' ] ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code >= 400 ) {
            return new WP_Error( 'scv_upstream_error', sprintf( __( 'De databron antwoordde met HTTP %d.', 'sportlink-club-viewer' ), $code ), [ 'status' => $code === 401 ? 401 : 502 ] );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            return new WP_Error( 'scv_bad_json', __( 'Ongeldig antwoord van de databron.', 'sportlink-club-viewer' ), [ 'status' => 502 ] );
        }
        return $data;
    }

    /**
     * All Nevobo clubs (59 pages), cached for a week.
     */
    public static function nevobo_clubs() {
        $cached = get_transient( 'scv_nevobo_clubs' );
        if ( is_array( $cached ) && ! empty( $cached ) ) {
            return $cached;
        }

        $clubs    = [];
        $next_url = 'https://api.nevobo.nl/relatiebeheer/verenigingen?page=1';
        $page     = 0;

        while ( $next_url && $page < 100 ) {
            $page++;
            $body = self::get_json( $next_url );
            if ( is_wp_error( $body ) ) {
                return $body;
            }

            foreach ( (array) ( isset( $body['hydra:member'] ) ? $body['hydra:member'] : [] ) as $club ) {
                $clubs[] = [
                    'id'   => isset( $club['organisatiecode'] ) ? $club['organisatiecode'] : '',
                    'name' => isset( $club['naam'] ) ? $club['naam'] : '',
                    'city' => isset( $club['vestigingsplaats'] ) ? $club['vestigingsplaats'] : '',
                ];
            }

            $next_path = isset( $body['hydra:view']['hydra:next'] ) ? $body['hydra:view']['hydra:next'] : null;
            $next_url  = $next_path ? 'https://api.nevobo.nl' . $next_path : null;
        }

        if ( ! empty( $clubs ) ) {
            set_transient( 'scv_nevobo_clubs', $clubs, WEEK_IN_SECONDS );
        }
        return $clubs;
    }
}

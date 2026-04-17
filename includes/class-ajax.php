<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCV_Ajax {

    public static function init() {
        add_action( 'wp_ajax_scv_fetch_clubs',        [ __CLASS__, 'fetch_clubs' ] );
        add_action( 'wp_ajax_scv_fetch_teams',        [ __CLASS__, 'fetch_teams' ] );
        add_action( 'wp_ajax_scv_fetch_competitions', [ __CLASS__, 'fetch_competitions' ] );
        add_action( 'wp_ajax_scv_verify_client',      [ __CLASS__, 'verify_client' ] );
    }

    // ── Fetch club list ───────────────────────────────────────────────────────

    public static function fetch_clubs() {
        check_ajax_referer( 'scv_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Geen toegang.', 'sportlink-club-viewer' ) ], 403 );
        }

        $connection_type  = sanitize_text_field( $_POST['connection_type'] ?? '' );
        $game_type_label  = sanitize_text_field( $_POST['game_type_label'] ?? '' );
        $username         = sanitize_text_field( $_POST['username'] ?? '' );
        $password         = sanitize_text_field( $_POST['password'] ?? '' );

        // Use stored password if none provided in the AJAX call
        if ( empty( $password ) ) {
            $password = get_option( 'scv_password', '' );
        }

        switch ( $connection_type ) {
            case 'Sportlink Proxy':
                self::fetch_sportlink_clubs( $game_type_label, $username, $password );
                break;
            case 'Nevobo Proxy':
                self::fetch_nevobo_clubs();
                break;
            default:
                wp_send_json_error( [ 'message' => __( 'Onbekend verbindingstype.', 'sportlink-club-viewer' ) ] );
        }
    }

    private static function fetch_sportlink_clubs( string $game_type_label, string $username, string $password ) {
        $app_creds = self::get_app_credentials( $game_type_label );
        if ( ! $app_creds ) {
            wp_send_json_error( [ 'message' => __( 'Geen API-gegevens gevonden voor dit sporttype.', 'sportlink-club-viewer' ) ] );
        }

        // PHP runs server-side — no CORS proxy needed; call Sportlink directly.
        $base_url   = "https://app-{$app_creds['apiUrl']}-production.sportlink.com";
        $token_url  = "{$base_url}/oauth/token";

        $token_response = wp_remote_post(
            $token_url,
            [
                'timeout' => 15,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'User-Agent'   => 'okhttp/4.12.0',
                ],
                'body' => [
                    'grant_type' => 'password',
                    'username'   => $username,
                    'password'   => $password,
                    'client_id'  => $app_creds['client_id'],
                    'secret'     => $app_creds['secret'],
                ],
            ]
        );

        if ( is_wp_error( $token_response ) ) {
            wp_send_json_error( [ 'message' => $token_response->get_error_message() ] );
        }

        $token_body = json_decode( wp_remote_retrieve_body( $token_response ), true );
        if ( empty( $token_body['access_token'] ) ) {
            $debug_token = get_option( 'scv_debug_mode', 0 )
                ? ' Token-respons: ' . mb_substr( wp_remote_retrieve_body( $token_response ), 0, 500 )
                : '';
            wp_send_json_error( [ 'message' => __( 'Inloggen mislukt. Controleer gebruikersnaam en wachtwoord.', 'sportlink-club-viewer' ) . $debug_token ] );
        }

        $access_token  = $token_body['access_token'];
        $refresh_token = $token_body['refresh_token'] ?? '';

        // Forward any session cookies set by the token endpoint
        $token_cookies = wp_remote_retrieve_cookies( $token_response );
        $cookie_header = '';
        if ( ! empty( $token_cookies ) ) {
            $pairs = [];
            foreach ( $token_cookies as $cookie ) {
                $pairs[] = $cookie->name . '=' . $cookie->value;
            }
            $cookie_header = implode( '; ', $pairs );
        }

        // Step 2: get clubs — route through the CORS proxy so the request
        // originates from a Cloudflare IP (direct server-to-server calls return 500)
        $proxy_base = 'https://cors-proxy.clubinfoproxy.workers.dev/proxy?url=';
        $clubs_url  = $proxy_base . rawurlencode( "{$base_url}/entity/common/memberportal/app/club/Clubs?v=1" );
        $clubs_args = [
            'timeout'    => 15,
            'user-agent' => 'okhttp/4.12.0',
            'headers'    => array_filter( [
                'Authorization'    => "Bearer {$access_token}",
                'X-Navajo-Instance'=> $app_creds['instance'],
                'X-Navajo-Locale'  => 'nl',
                'X-Navajo-Version' => '1',
                'X-Real-User-Agent'=> "sportlink-app-{$app_creds['userAgent']}/6.26.0-2025017636 android SM-N976N/samsung/25 (6.26.0)",
                'Accept'           => '*/*',
                'Cookie'           => $cookie_header ?: null,
            ] ),
        ];

        $clubs_response = wp_remote_get( $clubs_url, $clubs_args );

        if ( is_wp_error( $clubs_response ) ) {
            wp_send_json_error( [ 'message' => $clubs_response->get_error_message() ] );
        }

        $clubs_body = json_decode( wp_remote_retrieve_body( $clubs_response ), true );
        $clubs = [];

        // API returns { "Club": [ { "ClubId": ..., "ClubName": ..., "City": ... }, ... ] }
        $items = $clubs_body['Club'] ?? null;

        if ( is_array( $items ) ) {
            foreach ( $items as $club ) {
                if ( ! is_array( $club ) ) continue;
                $id   = $club['ClubId']   ?? $club['clubId']   ?? $club['Id']    ?? '';
                $name = $club['ClubName'] ?? $club['clubName'] ?? $club['Name']  ?? '';
                $city = $club['City']     ?? $club['city']     ?? $club['Place'] ?? '';
                if ( $id || $name ) {
                    $clubs[] = [ 'id' => (string) $id, 'name' => (string) $name, 'city' => (string) $city ];
                }
            }
        }

        // Include debug info so field names can be diagnosed if clubs still appear blank
        $debug_keys = ( $items && ! empty( $items[0] ) && is_array( $items[0] ) )
            ? array_keys( $items[0] )
            : [];

        $result = [ 'clubs' => $clubs, 'debug_keys' => $debug_keys ];

        if ( get_option( 'scv_debug_mode', 0 ) ) {
            $result['debug_token'] = [
                'response_code'    => wp_remote_retrieve_response_code( $token_response ),
                'response_headers' => wp_remote_retrieve_headers( $token_response )->getAll(),
                'cookies_forwarded'=> $cookie_header ?: '(geen)',
                'has_refresh_token'=> ! empty( $refresh_token ),
            ];
            $result['debug_request'] = [
                'url'              => urldecode( $clubs_url ),
                'headers_sent'     => $clubs_args['headers'],
                'user_agent_arg'   => $clubs_args['user-agent'],
                'response_code'    => wp_remote_retrieve_response_code( $clubs_response ),
                'response_headers' => wp_remote_retrieve_headers( $clubs_response )->getAll(),
            ];
            $result['debug_raw'] = mb_substr( wp_remote_retrieve_body( $clubs_response ), 0, 4000 );
        }

        wp_send_json_success( $result );
    }

    // ── Fetch teams for a club (Sportlink Proxy) ─────────────────────────────

    public static function fetch_teams() {
        check_ajax_referer( 'scv_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Geen toegang.', 'sportlink-club-viewer' ) ], 403 );
        }

        $club_id         = sanitize_text_field( $_POST['club_id']         ?? '' ) ?: get_option( 'scv_club_id', '' );
        $game_type_label = sanitize_text_field( $_POST['game_type_label'] ?? '' ) ?: get_option( 'scv_game_type_label', '' );
        $username        = sanitize_text_field( $_POST['username']        ?? '' ) ?: get_option( 'scv_username', '' );
        $password        = get_option( 'scv_password', '' );

        if ( empty( $club_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Geen club geselecteerd. Sla eerst de club-instellingen op.', 'sportlink-club-viewer' ) ] );
        }

        $app_creds = self::get_app_credentials( $game_type_label );
        if ( ! $app_creds ) {
            wp_send_json_error( [ 'message' => __( 'Geen API-gegevens voor dit sporttype.', 'sportlink-club-viewer' ) ] );
        }

        $base_url = "https://app-{$app_creds['apiUrl']}-production.sportlink.com";

        // Get OAuth token
        $token_response = wp_remote_post( "{$base_url}/oauth/token", [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent'   => 'okhttp/4.12.0',
            ],
            'body' => [
                'grant_type' => 'password',
                'username'   => $username,
                'password'   => $password,
                'client_id'  => $app_creds['client_id'],
                'secret'     => $app_creds['secret'],
            ],
        ] );

        if ( is_wp_error( $token_response ) ) {
            wp_send_json_error( [ 'message' => $token_response->get_error_message() ] );
        }

        $token_body = json_decode( wp_remote_retrieve_body( $token_response ), true );
        if ( empty( $token_body['access_token'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Inloggen mislukt. Controleer gebruikersnaam en wachtwoord.', 'sportlink-club-viewer' ) ] );
        }

        $access_token = $token_body['access_token'];

        $token_cookies = wp_remote_retrieve_cookies( $token_response );
        $cookie_header = '';
        if ( ! empty( $token_cookies ) ) {
            $pairs = [];
            foreach ( $token_cookies as $cookie ) {
                $pairs[] = $cookie->name . '=' . $cookie->value;
            }
            $cookie_header = implode( '; ', $pairs );
        }

        // Fetch teams via CORS proxy
        $proxy_base  = 'https://cors-proxy.clubinfoproxy.workers.dev/proxy?url=';
        $teams_url   = $proxy_base . rawurlencode( "{$base_url}/entity/common/memberportal/app/club/ClubTeams?v=1&ClubId={$club_id}" );
        $teams_args  = [
            'timeout'    => 15,
            'user-agent' => 'okhttp/4.12.0',
            'headers'    => array_filter( [
                'Authorization'     => "Bearer {$access_token}",
                'X-Navajo-Instance' => $app_creds['instance'],
                'X-Navajo-Locale'   => 'nl',
                'X-Navajo-Version'  => '1',
                'X-Real-User-Agent' => "sportlink-app-{$app_creds['userAgent']}/6.26.0-2025017636 android SM-N976N/samsung/25 (6.26.0)",
                'Accept'            => '*/*',
                'Cookie'            => $cookie_header ?: null,
            ] ),
        ];

        $teams_response = wp_remote_get( $teams_url, $teams_args );

        if ( is_wp_error( $teams_response ) ) {
            wp_send_json_error( [ 'message' => $teams_response->get_error_message() ] );
        }

        $teams_body = json_decode( wp_remote_retrieve_body( $teams_response ), true );
        $teams = [];

        // API may wrap teams under 'Team' or 'ClubTeam'
        $items = $teams_body['Team'] ?? $teams_body['ClubTeam'] ?? null;

        if ( is_array( $items ) ) {
            foreach ( $items as $team ) {
                $id   = $team['PublicTeamId'] ?? $team['TeamId'] ?? '';
                $name = $team['TeamName']     ?? $team['Name']   ?? '';
                if ( $id && $name ) {
                    $teams[] = [ 'id' => (string) $id, 'name' => (string) $name ];
                }
            }
        }

        if ( empty( $teams ) ) {
            $extra = get_option( 'scv_debug_mode', 0 )
                ? ' Raw: ' . mb_substr( wp_remote_retrieve_body( $teams_response ), 0, 2000 )
                : '';
            wp_send_json_error( [ 'message' => __( 'Geen teams gevonden voor deze club.', 'sportlink-club-viewer' ) . $extra ] );
        }

        wp_send_json_success( [ 'teams' => $teams ] );
    }

    // ── Fetch competitions for a team (step 4) ────────────────────────────────

    public static function fetch_competitions() {
        check_ajax_referer( 'scv_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Geen toegang.', 'sportlink-club-viewer' ) ], 403 );
        }

        $team_id         = sanitize_text_field( $_POST['team_id']         ?? '' ) ?: get_option( 'scv_standing_team_id', '' );
        $game_type_label = sanitize_text_field( $_POST['game_type_label'] ?? '' ) ?: get_option( 'scv_game_type_label', '' );
        $username        = sanitize_text_field( $_POST['username']        ?? '' ) ?: get_option( 'scv_username', '' );
        $password        = get_option( 'scv_password', '' );

        if ( empty( $team_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Geen team geselecteerd.', 'sportlink-club-viewer' ) ] );
        }

        $app_creds = self::get_app_credentials( $game_type_label );
        if ( ! $app_creds ) {
            wp_send_json_error( [ 'message' => __( 'Geen API-gegevens voor dit sporttype.', 'sportlink-club-viewer' ) ] );
        }

        $base_url = "https://app-{$app_creds['apiUrl']}-production.sportlink.com";

        // Get OAuth token
        $token_response = wp_remote_post( "{$base_url}/oauth/token", [
            'timeout' => 15,
            'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded', 'User-Agent' => 'okhttp/4.12.0' ],
            'body'    => [
                'grant_type' => 'password', 'username' => $username, 'password' => $password,
                'client_id'  => $app_creds['client_id'], 'secret' => $app_creds['secret'],
            ],
        ] );

        if ( is_wp_error( $token_response ) ) {
            wp_send_json_error( [ 'message' => $token_response->get_error_message() ] );
        }

        $token_body = json_decode( wp_remote_retrieve_body( $token_response ), true );
        if ( empty( $token_body['access_token'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Inloggen mislukt. Controleer de inloggegevens.', 'sportlink-club-viewer' ) ] );
        }

        $access_token  = $token_body['access_token'];
        $token_cookies = wp_remote_retrieve_cookies( $token_response );
        $cookie_header = '';
        if ( ! empty( $token_cookies ) ) {
            $pairs = [];
            foreach ( $token_cookies as $c ) { $pairs[] = $c->name . '=' . $c->value; }
            $cookie_header = implode( '; ', $pairs );
        }

        // Fetch TeamCompetitionData via CORS proxy
        $proxy_base = 'https://cors-proxy.clubinfoproxy.workers.dev/proxy?url=';
        $comp_url   = $proxy_base . rawurlencode( "{$base_url}/entity/common/memberportal/app/team/TeamCompetitionData?v=2&PublicTeamId={$team_id}" );
        $comp_args  = [
            'timeout'    => 15,
            'user-agent' => 'okhttp/4.12.0',
            'headers'    => array_filter( [
                'Authorization'     => "Bearer {$access_token}",
                'X-Navajo-Instance' => $app_creds['instance'],
                'X-Navajo-Locale'   => 'nl',
                'X-Navajo-Version'  => '2',
                'X-Real-User-Agent' => "sportlink-app-{$app_creds['userAgent']}/6.26.0-2025017636 android SM-N976N/samsung/25 (6.26.0)",
                'Accept'            => '*/*',
                'Cookie'            => $cookie_header ?: null,
            ] ),
        ];

        $comp_response = wp_remote_get( $comp_url, $comp_args );

        if ( is_wp_error( $comp_response ) ) {
            wp_send_json_error( [ 'message' => $comp_response->get_error_message() ] );
        }

        $comp_body = json_decode( wp_remote_retrieve_body( $comp_response ), true );

        if ( get_option( 'scv_debug_mode', 0 ) ) {
            error_log( 'SCV fetch_competitions raw: ' . mb_substr( wp_remote_retrieve_body( $comp_response ), 0, 4000 ) );
        }

        // The array may live under different keys depending on sport/API version
        $raw = $comp_body['TeamCompetition'] ?? $comp_body['Competition'] ?? $comp_body['Pool'] ?? ( is_array( $comp_body ) ? $comp_body : [] );

        $competitions = [];
        foreach ( (array) $raw as $entry ) {
            $pool_id = $entry['PoolId'] ?? '';
            $kind    = $entry['CompetitionKind'] ?? '';
            $class   = $entry['ClassName'] ?? $entry['CompetitionClass'] ?? $entry['Class'] ?? '';

            if ( empty( $pool_id ) ) continue;

            if ( $kind === 'DEFAULT_COMPETITION' ) {
                $label = 'Competitie' . ( $class ? ' ' . $class : '' );
            } else {
                // TROPHY_COMPETITION or anything else: just use ClassName
                $label = $class ?: 'Beker / Toernooi';
            }

            $competitions[] = [
                'id'    => (string) $pool_id,
                'name'  => $label,
                'kind'  => $kind,
                'class' => $class,
            ];
        }

        if ( empty( $competitions ) ) {
            $extra = get_option( 'scv_debug_mode', 0 )
                ? ' Raw: ' . mb_substr( wp_remote_retrieve_body( $comp_response ), 0, 2000 )
                : '';
            wp_send_json_error( [ 'message' => __( 'Geen competities gevonden voor dit team.', 'sportlink-club-viewer' ) . $extra ] );
        }

        wp_send_json_success( [ 'competitions' => $competitions ] );
    }

    private static function fetch_nevobo_clubs() {
        $all_clubs = [];
        $next_url  = 'https://api.nevobo.nl/relatiebeheer/verenigingen?page=1';
        $page      = 0;

        while ( $next_url && $page < 20 ) { // safety cap
            $page++;
            $response = wp_remote_get( $next_url, [ 'timeout' => 15 ] );

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( [ 'message' => $response->get_error_message() ] );
            }

            $body  = json_decode( wp_remote_retrieve_body( $response ), true );
            $items = $body['hydra:member'] ?? [];

            foreach ( $items as $club ) {
                $all_clubs[] = [
                    'id'   => $club['organisatiecode']   ?? '',
                    'name' => $club['naam']               ?? '',
                    'city' => $club['vestigingsplaats']   ?? '',
                ];
            }

            // Follow hydra:next for pagination
            $next_path = $body['hydra:view']['hydra:next'] ?? null;
            $next_url  = $next_path ? 'https://api.nevobo.nl' . $next_path : null;
        }

        wp_send_json_success( [ 'clubs' => $all_clubs ] );
    }

    // ── Verify Sportlink API client ID ────────────────────────────────────────

    public static function verify_client() {
        check_ajax_referer( 'scv_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        $client_id = sanitize_text_field( $_POST['client_id'] ?? '' );
        if ( empty( $client_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Geen client ID opgegeven.', 'sportlink-club-viewer' ) ] );
        }

        $url      = "https://data.sportlink.com/vereniging?client_id={$client_id}";
        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body[0]['naam'] ) ) {
            $name = sanitize_text_field( $body[0]['naam'] );
            $city = sanitize_text_field( $body[0]['plaats'] ?? '' );
            wp_send_json_success( [
                'name'     => $name,
                'city'     => $city,
                'locatie'  => $city ?: $name,
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Client ID niet gevonden of ongeldig.', 'sportlink-club-viewer' ) ] );
        }
    }

    // ── App credentials (mirrors Vue config.js APP_CREDENTIALS) ──────────────

    public static function get_app_credentials( string $label ): ?array {
        $map = [
            'Voetbal'         => [ 'client_id' => 'oCuV9oozaaz8zee', 'secret' => 'eep7Shoo7i',       'instance' => 'KNVB',  'userAgent' => 'voetbalnl',  'apiUrl' => 'vnl' ],
            'Waterpolo'       => [ 'client_id' => '4BtKnhojt4MSnRScVak5', 'secret' => 'vLD8uPHOgIHJjAj9', 'instance' => 'KNZB', 'userAgent' => 'knzb', 'apiUrl' => 'sportlinked' ],
            'Hockey België'   => [ 'client_id' => 'YqTh94xQBASRCtTmpa0b', 'secret' => '15T74iIa011VVoAm', 'instance' => 'KBHB', 'userAgent' => 'kbhb', 'apiUrl' => 'sportlinked' ],
            'Soft- en Honkbal'=> [ 'client_id' => '0SaoFKzAVgn3cTzxUsk8', 'secret' => 'H1LRQnWYxm10YA87', 'instance' => 'KNBSB','userAgent' => 'knbsb','apiUrl' => 'sportlinked' ],
            'Basketbal'       => [ 'client_id' => '4boXZaODcf1A5ffb7zMl', 'secret' => 'netkEQKiWAsFEwl3', 'instance' => 'NBB',  'userAgent' => 'nbb',  'apiUrl' => 'sportlinked' ],
            'Handbal'         => [ 'client_id' => 'JUian2haoKqIripvaios', 'secret' => '9BdMs5h9jvr9Agte', 'instance' => 'NHV',  'userAgent' => 'nhv',  'apiUrl' => 'sportlinked' ],
            'Korfbal'         => [ 'client_id' => 'SdJSHVPuWzK066Mu28ki', 'secret' => 'j2OInPPCmWJ0VA2W', 'instance' => 'KNKV', 'userAgent' => 'knkv', 'apiUrl' => 'sportlinked' ],
        ];

        return $map[ $label ] ?? null;
    }
}

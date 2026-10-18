<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin-only AJAX actions used by the settings screen (club/team/competition
 * pickers, connection test, cache flush). All upstream traffic goes through
 * SCV_Sportlink_Client so tokens are cached and credentials stay server-side.
 */
class SCV_Ajax {

    public static function init() {
        add_action( 'wp_ajax_scv_fetch_clubs',        [ __CLASS__, 'fetch_clubs' ] );
        add_action( 'wp_ajax_scv_fetch_teams',        [ __CLASS__, 'fetch_teams' ] );
        add_action( 'wp_ajax_scv_fetch_competitions', [ __CLASS__, 'fetch_competitions' ] );
        add_action( 'wp_ajax_scv_verify_client',      [ __CLASS__, 'verify_client' ] );
        add_action( 'wp_ajax_scv_test_connection',    [ __CLASS__, 'test_connection' ] );
        add_action( 'wp_ajax_scv_clear_cache',        [ __CLASS__, 'clear_cache' ] );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Kept for backwards compatibility; the client owns the credential table now. */
    public static function get_app_credentials( string $label ) {
        return SCV_Sportlink_Client::get_app_credentials( $label );
    }

    private static function guard() {
        check_ajax_referer( 'scv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Geen toegang.', 'sportlink-club-viewer' ) ], 403 );
        }
    }

    private static function post( string $key, string $option = '' ): string {
        $value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
        if ( $value === '' && $option !== '' ) {
            $value = (string) get_option( $option, '' );
        }
        return $value;
    }

    /** Password straight from the form (not yet saved) or the stored one. */
    private static function account_from_request(): array {
        $raw_password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
        return SCV_Sportlink_Client::account( [
            'username' => self::post( 'username' ),
            'password' => $raw_password,
        ] );
    }

    private static function creds_or_fail( string $label ): array {
        $creds = SCV_Sportlink_Client::get_app_credentials( $label );
        if ( ! $creds ) {
            wp_send_json_error( [ 'message' => __( 'Geen API-gegevens gevonden voor dit sporttype.', 'sportlink-club-viewer' ) ] );
        }
        return $creds;
    }

    private static function fail_if_error( $result ) {
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        return $result;
    }

    // ── Clubs ────────────────────────────────────────────────────────────────

    public static function fetch_clubs() {
        self::guard();

        switch ( self::post( 'connection_type', 'scv_connection_type' ) ) {
            case 'Sportlink Proxy':
                $creds = self::creds_or_fail( self::post( 'game_type_label', 'scv_game_type_label' ) );
                $url   = SCV_Sportlink_Client::base_url( $creds ) . '/entity/common/memberportal/app/club/Clubs?v=1';
                $data  = self::fail_if_error( SCV_Sportlink_Client::sportlink_get( $url, $creds, '1', self::account_from_request() ) );

                $clubs = [];
                foreach ( (array) ( isset( $data['Club'] ) ? $data['Club'] : [] ) as $club ) {
                    if ( ! is_array( $club ) ) continue;
                    $id   = (string) ( $club['ClubId']   ?? '' );
                    $name = (string) ( $club['ClubName'] ?? '' );
                    if ( $id === '' && $name === '' ) continue;
                    $clubs[] = [ 'id' => $id, 'name' => $name, 'city' => (string) ( $club['City'] ?? '' ) ];
                }
                wp_send_json_success( [ 'clubs' => $clubs ] );
                break;

            case 'Nevobo Proxy':
                $clubs = self::fail_if_error( SCV_Sportlink_Client::nevobo_clubs() );
                wp_send_json_success( [ 'clubs' => $clubs ] );
                break;

            default:
                wp_send_json_error( [ 'message' => __( 'Onbekend verbindingstype.', 'sportlink-club-viewer' ) ] );
        }
    }

    // ── Teams ────────────────────────────────────────────────────────────────

    public static function fetch_teams() {
        self::guard();

        $connection = self::post( 'connection_type', 'scv_connection_type' );

        if ( $connection === 'Sportlink API' ) {
            $client_id = self::post( 'client_id', 'scv_client_id' );
            if ( $client_id === '' ) {
                wp_send_json_error( [ 'message' => __( 'Geen client ID geconfigureerd.', 'sportlink-club-viewer' ) ] );
            }
            $body = self::fail_if_error( SCV_Sportlink_Client::get_json( 'https://data.sportlink.com/teams?gebruiklokaleteamgegevens=NEE&client_id=' . rawurlencode( $client_id ) ) );

            $seen  = [];
            $teams = [];
            foreach ( $body as $team ) {
                $code = (string) ( $team['teamcode'] ?? '' );
                if ( $code !== '' && ! isset( $seen[ $code ] ) ) {
                    $seen[ $code ] = true;
                    $teams[] = [ 'id' => $code, 'name' => (string) ( $team['teamnaam'] ?? '' ) ];
                }
            }
            self::send_teams( $teams );
        }

        $club_id = self::post( 'club_id', 'scv_club_id' );
        if ( $club_id === '' ) {
            wp_send_json_error( [ 'message' => __( 'Geen club geselecteerd. Sla eerst de club-instellingen op.', 'sportlink-club-viewer' ) ] );
        }

        $creds = self::creds_or_fail( self::post( 'game_type_label', 'scv_game_type_label' ) );
        $url   = SCV_Sportlink_Client::base_url( $creds ) . '/entity/common/memberportal/app/club/ClubTeams?v=1&ClubId=' . rawurlencode( $club_id );
        $data  = self::fail_if_error( SCV_Sportlink_Client::sportlink_get( $url, $creds, '1', self::account_from_request() ) );

        // One row per (team, competition): merge ids per team name so a team that
        // plays beker and competitie is listed once. Downstream accepts "id1,id2".
        $by_name = [];
        foreach ( (array) ( $data['Team'] ?? $data['ClubTeam'] ?? [] ) as $team ) {
            $id   = (string) ( $team['PublicTeamId'] ?? $team['TeamId'] ?? '' );
            $name = (string) ( $team['TeamName'] ?? $team['Name'] ?? '' );
            if ( $id === '' || $name === '' ) continue;
            $key = mb_strtolower( trim( $name ) );
            if ( ! isset( $by_name[ $key ] ) ) {
                $by_name[ $key ] = [ 'name' => $name, 'ids' => [] ];
            }
            if ( ! in_array( $id, $by_name[ $key ]['ids'], true ) ) {
                $by_name[ $key ]['ids'][] = $id;
            }
        }

        $teams = [];
        foreach ( $by_name as $entry ) {
            $teams[] = [ 'id' => implode( ',', $entry['ids'] ), 'name' => $entry['name'] ];
        }
        self::send_teams( $teams );
    }

    private static function send_teams( array $teams ) {
        if ( empty( $teams ) ) {
            wp_send_json_error( [ 'message' => __( 'Geen teams gevonden voor deze configuratie.', 'sportlink-club-viewer' ) ] );
        }
        wp_send_json_success( [ 'teams' => $teams ] );
    }

    // ── Competitions ─────────────────────────────────────────────────────────

    public static function fetch_competitions() {
        self::guard();

        $connection = self::post( 'connection_type', 'scv_connection_type' );
        $team_id    = self::post( 'team_id', 'scv_standing_team_id' );
        if ( $team_id === '' ) {
            wp_send_json_error( [ 'message' => __( 'Geen team geselecteerd.', 'sportlink-club-viewer' ) ] );
        }

        $competitions = [];

        if ( $connection === 'Sportlink API' ) {
            $client_id = self::post( 'client_id', 'scv_client_id' );
            if ( $client_id === '' ) {
                wp_send_json_error( [ 'message' => __( 'Geen client ID geconfigureerd.', 'sportlink-club-viewer' ) ] );
            }
            $body = self::fail_if_error( SCV_Sportlink_Client::get_json( 'https://data.sportlink.com/teampoulelijst?teamcode=' . rawurlencode( $team_id ) . '&lokaleteamcode=-1&client_id=' . rawurlencode( $client_id ) ) );
            foreach ( $body as $pool ) {
                $id = (string) ( $pool['poulecode'] ?? '' );
                if ( $id !== '' ) {
                    $competitions[] = [ 'id' => $id, 'name' => (string) ( $pool['teamnaam'] ?? $id ) ];
                }
            }
        } else {
            $creds   = self::creds_or_fail( self::post( 'game_type_label', 'scv_game_type_label' ) );
            $account = self::account_from_request();
            $seen    = [];

            foreach ( array_filter( array_map( 'trim', explode( ',', $team_id ) ) ) as $tid ) {
                $url  = SCV_Sportlink_Client::base_url( $creds ) . '/entity/common/memberportal/app/team/TeamCompetitionData?v=2&PublicTeamId=' . rawurlencode( $tid );
                $data = SCV_Sportlink_Client::sportlink_get( $url, $creds, '2', $account );
                if ( is_wp_error( $data ) ) continue;

                $raw = $data['TeamCompetition'] ?? $data['Competition'] ?? $data['Pool'] ?? $data;
                foreach ( (array) $raw as $entry ) {
                    if ( ! is_array( $entry ) ) continue;
                    $pool_id = (string) ( $entry['PoolId'] ?? '' );
                    if ( $pool_id === '' || isset( $seen[ $pool_id ] ) ) continue;
                    $seen[ $pool_id ] = true;

                    $kind  = (string) ( $entry['CompetitionKind'] ?? '' );
                    $class = (string) ( $entry['ClassName'] ?? $entry['CompetitionClass'] ?? $entry['Class'] ?? '' );
                    $label = $kind === 'DEFAULT_COMPETITION'
                        ? 'Competitie' . ( $class !== '' ? ' ' . $class : '' )
                        : ( $class !== '' ? $class : 'Beker / Toernooi' );

                    $competitions[] = [ 'id' => $pool_id, 'name' => $label, 'kind' => $kind, 'class' => $class ];
                }
            }
        }

        if ( empty( $competitions ) ) {
            wp_send_json_error( [ 'message' => __( 'Geen competities gevonden voor dit team.', 'sportlink-club-viewer' ) ] );
        }
        wp_send_json_success( [ 'competitions' => $competitions ] );
    }

    // ── Sportlink API client id ──────────────────────────────────────────────

    public static function verify_client() {
        self::guard();

        $client_id = self::post( 'client_id' );
        if ( $client_id === '' ) {
            wp_send_json_error( [ 'message' => __( 'Geen client ID opgegeven.', 'sportlink-club-viewer' ) ] );
        }

        $body = SCV_Sportlink_Client::get_json( 'https://data.sportlink.com/clubgegevens?client_id=' . rawurlencode( $client_id ), 10 );
        if ( is_wp_error( $body ) || empty( $body[0]['naam'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Client ID niet gevonden of ongeldig.', 'sportlink-club-viewer' ) ] );
        }

        $name = sanitize_text_field( $body[0]['naam'] );
        $city = sanitize_text_field( $body[0]['plaats'] ?? '' );
        wp_send_json_success( [ 'name' => $name, 'city' => $city, 'locatie' => $city !== '' ? $city : $name ] );
    }

    // ── Connection test ──────────────────────────────────────────────────────

    public static function test_connection() {
        self::guard();

        $connection = self::post( 'connection_type', 'scv_connection_type' );
        $status     = [ 'status' => 'unknown', 'time' => time(), 'message' => '' ];

        $fail = function ( string $message ) use ( &$status ) {
            $status['status']  = 'error';
            $status['message'] = $message;
            update_option( 'scv_connection_status', $status );
            wp_send_json_error( [ 'message' => $message ] );
        };

        switch ( $connection ) {
            case 'Sportlink API':
                $client_id = self::post( 'client_id', 'scv_client_id' );
                if ( $client_id === '' ) $fail( __( 'Geen client ID ingevuld.', 'sportlink-club-viewer' ) );

                $body = SCV_Sportlink_Client::get_json( 'https://data.sportlink.com/clubgegevens?client_id=' . rawurlencode( $client_id ), 10 );
                if ( is_wp_error( $body ) || empty( $body[0]['naam'] ) ) $fail( __( 'Client ID niet gevonden of ongeldig.', 'sportlink-club-viewer' ) );

                $status['message'] = sprintf( __( 'Verbonden met %s', 'sportlink-club-viewer' ), sanitize_text_field( $body[0]['naam'] ) );
                break;

            case 'Sportlink Proxy':
                $creds   = self::creds_or_fail( self::post( 'game_type_label', 'scv_game_type_label' ) );
                $account = self::account_from_request();
                if ( $account['username'] === '' || $account['password'] === '' ) $fail( __( 'Gebruikersnaam en wachtwoord vereist.', 'sportlink-club-viewer' ) );

                $token = SCV_Sportlink_Client::get_token( $creds, $account, true );
                if ( is_wp_error( $token ) ) $fail( $token->get_error_message() );

                $status['message'] = __( 'Inloggen gelukt. Verbinding actief.', 'sportlink-club-viewer' );
                break;

            case 'Nevobo Proxy':
                $body = SCV_Sportlink_Client::get_json( 'https://api.nevobo.nl/relatiebeheer/verenigingen?page=1', 10 );
                if ( is_wp_error( $body ) ) $fail( __( 'Nevobo API niet bereikbaar.', 'sportlink-club-viewer' ) );

                $status['message'] = __( 'Nevobo API bereikbaar.', 'sportlink-club-viewer' );
                break;

            default:
                wp_send_json_error( [ 'message' => __( 'Geen verbindingstype geselecteerd.', 'sportlink-club-viewer' ) ] );
        }

        $status['status'] = 'ok';
        update_option( 'scv_connection_status', $status );
        wp_send_json_success( [ 'message' => $status['message'] ] );
    }

    // ── Cache ────────────────────────────────────────────────────────────────

    public static function clear_cache() {
        self::guard();
        SCV_Cache::bump();
        if ( ! empty( $_POST['logos'] ) ) {
            SCV_Cache::clear_logos();
        }
        delete_transient( 'scv_nevobo_clubs' );
        wp_send_json_success( [ 'message' => __( 'Cache geleegd. Bezoekers zien bij de volgende weergave verse gegevens.', 'sportlink-club-viewer' ) ] );
    }
}

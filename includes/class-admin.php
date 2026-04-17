<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SCV_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    // ── Menu ──────────────────────────────────────────────────────────────────

    public static function add_menu() {
        add_options_page(
            __( 'Sportlink Club Viewer', 'sportlink-club-viewer' ),
            __( 'Sportlink Viewer', 'sportlink-club-viewer' ),
            'manage_options',
            'sportlink-club-viewer',
            [ __CLASS__, 'render_page' ]
        );
    }

    // ── Settings registration ─────────────────────────────────────────────────

    public static function register_settings() {

        // ── General settings ──────────────────────────────────────────────────
        $general_fields = [
            'scv_game_type_label'      => 'sanitize_text_field',
            'scv_connection_type'      => 'sanitize_text_field',
            'scv_client_id'            => 'sanitize_text_field',
            'scv_club_id'              => 'sanitize_text_field',
            'scv_club_identifier'      => 'sanitize_text_field',
            'scv_sport_locatie'        => 'sanitize_text_field',
            'scv_username'             => 'sanitize_text_field',
            'scv_background_url'       => 'esc_url_raw',
            'scv_programma_dagen'      => 'absint',
            'scv_uitslag_dagen'        => 'absint',
            'scv_prematch_refresh'     => 'absint',
            'scv_enable_screen_switch' => 'absint',
            'scv_active_sponsors'      => 'absint',
            'scv_fake_credentials'     => 'absint',
            'scv_debug_mode'           => 'absint',
            'scv_display_height'       => 'absint',
            'scv_scroll_speed'         => 'absint',
        ];

        foreach ( $general_fields as $option => $sanitize ) {
            register_setting( 'scv_general', $option, [ 'sanitize_callback' => $sanitize ] );
        }

        register_setting( 'scv_general', 'scv_enable_standings',   [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'scv_general', 'scv_standing_team_id',  [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'scv_general', 'scv_standing_team_name',[ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'scv_general', 'scv_standing_pool_id',  [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'scv_general', 'scv_standing_pool_name',[ 'sanitize_callback' => 'sanitize_text_field' ] );

        // Password stored separately (no esc_url processing)
        register_setting( 'scv_general', 'scv_password', [
            'sanitize_callback' => function( $val ) {
                // Keep existing password if field left blank
                if ( empty( $val ) ) {
                    return get_option( 'scv_password', '' );
                }
                return sanitize_text_field( $val );
            }
        ] );

        // ── Style settings ────────────────────────────────────────────────────
        register_setting( 'scv_style', 'scv_colors', [
            'sanitize_callback' => [ __CLASS__, 'sanitize_colors' ],
        ] );
        register_setting( 'scv_style', 'scv_layout', [
            'sanitize_callback' => [ __CLASS__, 'sanitize_layout' ],
        ] );
        register_setting( 'scv_style', 'scv_standing_columns', [
            'sanitize_callback' => [ __CLASS__, 'sanitize_standing_columns' ],
        ] );
        register_setting( 'scv_style', 'scv_own_team_colors', [
            'sanitize_callback' => [ __CLASS__, 'sanitize_own_team_colors' ],
        ] );

        // ── Sponsors ──────────────────────────────────────────────────────────
        register_setting( 'scv_sponsors_group', 'scv_sponsors', [
            'sanitize_callback' => [ __CLASS__, 'sanitize_sponsors' ],
        ] );
    }

    public static function sanitize_colors( $input ) {
        $keys = [
            'leftBoxColor','leftBoxText','leftMidBoxColor','leftMidBoxText',
            'midBoxColor','midBoxText','rightMidBoxColor','rightMidBoxText',
            'rightBoxColor','rightBoxText',
        ];
        $clean = [];
        foreach ( $keys as $k ) {
            $clean[ $k ] = isset( $input[ $k ] ) ? sanitize_hex_color( $input[ $k ] ) : '#000000';
        }
        return $clean;
    }

    public static function sanitize_layout( $input ) {
        $input = is_array( $input ) ? $input : [];
        $width_keys   = [ 'leftWidth', 'leftMidWidth', 'midWidth', 'rightMidWidth', 'rightWidth' ];
        $visible_keys = [ 'leftVisible', 'leftMidVisible', 'midVisible', 'rightMidVisible', 'rightVisible', 'showLogos' ];
        $clean = [];
        foreach ( $width_keys as $k ) {
            $val       = isset( $input[ $k ] ) ? (int) $input[ $k ] : 1;
            $clean[ $k ] = max( 1, min( 20, $val ) );
        }
        foreach ( $visible_keys as $k ) {
            $clean[ $k ] = isset( $input[ $k ] ) ? 1 : 0;
        }
        return $clean;
    }

    public static function sanitize_standing_columns( $input ) {
        $input = is_array( $input ) ? $input : [];
        $keys  = [ 'totalMatches', 'won', 'draw', 'lost', 'goalsFor', 'goalsAgainst', 'goalsDiff', 'points' ];
        $clean = [];
        foreach ( $keys as $k ) {
            $clean[ $k ] = isset( $input[ $k ] ) ? 1 : 0;
        }
        return $clean;
    }

    public static function sanitize_own_team_colors( $input ) {
        $input = is_array( $input ) ? $input : [];
        return [
            'bg'   => sanitize_hex_color( $input['bg']   ?? '#1a5c1a' ) ?: '#1a5c1a',
            'text' => sanitize_hex_color( $input['text'] ?? '#ffffff' ) ?: '#ffffff',
        ];
    }

    public static function sanitize_sponsors( $input ) {
        if ( ! is_array( $input ) ) return [];
        return array_values( array_filter( array_map( 'esc_url_raw', $input ) ) );
    }

    // ── Page render ───────────────────────────────────────────────────────────

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        $tabs = [
            'general'  => __( 'Instellingen', 'sportlink-club-viewer' ),
            'style'    => __( 'Stijl', 'sportlink-club-viewer' ),
            'sponsors' => __( 'Sponsors', 'sportlink-club-viewer' ),
        ];
        $conn_status_opt = get_option( 'scv_connection_status', [] );
        $dot_status      = is_array( $conn_status_opt ) ? ( $conn_status_opt['status'] ?? 'unknown' ) : 'unknown';
        ?>
        <div class="wrap scv-wrap">
            <h1>
                <span class="scv-logo">⚽</span>
                <?php esc_html_e( 'Sportlink Club Viewer', 'sportlink-club-viewer' ); ?>
                <span id="scv-status-dot" data-status="<?php echo esc_attr( $dot_status ); ?>" title="<?php esc_attr_e( 'Verbindingsstatus', 'sportlink-club-viewer' ); ?>"></span>
            </h1>

            <nav class="nav-tab-wrapper scv-tabs">
                <?php foreach ( $tabs as $key => $label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'options-general.php?page=sportlink-club-viewer&tab=' . $key ) ); ?>"
                       class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="scv-tab-content">
                <?php
                switch ( $tab ) {
                    case 'style':    self::render_style_tab();    break;
                    case 'sponsors': self::render_sponsors_tab(); break;
                    default:         self::render_general_tab();  break;
                }
                ?>
            </div>

            <div class="scv-shortcode-info">
                <h3><?php esc_html_e( 'Shortcodes', 'sportlink-club-viewer' ); ?></h3>
                <p><?php esc_html_e( 'Gebruik deze shortcodes op elke pagina of post:', 'sportlink-club-viewer' ); ?></p>
                <ul>
                    <li><code>[sportlink_match_display]</code>
                        <button type="button" class="button button-small scv-copy-shortcode" data-shortcode="[sportlink_match_display]"><?php esc_html_e( 'Kopieer', 'sportlink-club-viewer' ); ?></button>
                        — <?php esc_html_e( 'Wedstrijdprogramma en uitslagen (wisselt automatisch als instelling actief is)', 'sportlink-club-viewer' ); ?></li>
                    <li><code>[sportlink_prematch_display]</code>
                        <button type="button" class="button button-small scv-copy-shortcode" data-shortcode="[sportlink_prematch_display]"><?php esc_html_e( 'Kopieer', 'sportlink-club-viewer' ); ?></button>
                        — <?php esc_html_e( 'Voorwedstrijdinformatie (kleedkamers & veld)', 'sportlink-club-viewer' ); ?></li>
                    <li><code>[sportlink_standing_display]</code>
                        <button type="button" class="button button-small scv-copy-shortcode" data-shortcode="[sportlink_standing_display]"><?php esc_html_e( 'Kopieer', 'sportlink-club-viewer' ); ?></button>
                        — <?php esc_html_e( 'Stand van het geconfigureerde team', 'sportlink-club-viewer' ); ?></li>
                </ul>
                <p class="description"><?php esc_html_e( 'Tip: gebruik een paginasjabloon zonder kop/voettekst voor het beste weergaveresultaat op een scherm.', 'sportlink-club-viewer' ); ?></p>
            </div>
        </div>
        <?php
    }

    // ── Tab: General ──────────────────────────────────────────────────────────

    private static function render_general_tab() {
        $game_type_label      = get_option( 'scv_game_type_label', '' );
        $connection_type      = get_option( 'scv_connection_type', '' );
        $client_id            = get_option( 'scv_client_id', '' );
        $club_id              = get_option( 'scv_club_id', '' );
        $club_identifier      = get_option( 'scv_club_identifier', '' );
        $sport_locatie        = get_option( 'scv_sport_locatie', '' );
        $username             = get_option( 'scv_username', '' );
        $programma_dagen      = get_option( 'scv_programma_dagen', 7 );
        $uitslag_dagen        = get_option( 'scv_uitslag_dagen', 7 );
        $prematch_refresh     = get_option( 'scv_prematch_refresh', 15 );
        $enable_screen_switch = get_option( 'scv_enable_screen_switch', 1 );
        $active_sponsors      = get_option( 'scv_active_sponsors', 0 );
        $fake_credentials     = get_option( 'scv_fake_credentials', 0 );
        $background_url       = get_option( 'scv_background_url', '' );
        $debug_mode           = get_option( 'scv_debug_mode', 0 );
        $display_height       = get_option( 'scv_display_height', 0 );
        $scroll_speed         = get_option( 'scv_scroll_speed', 2 );

        $game_types = self::get_game_types();
        ?>
        <form method="post" action="options.php" id="scv-general-form">
            <?php settings_fields( 'scv_general' ); ?>

            <!-- Sport -->
            <div class="scv-section">
                <h2><?php esc_html_e( 'Verbinding', 'sportlink-club-viewer' ); ?></h2>
                <table class="form-table" role="presentation">

                    <tr>
                        <th scope="row"><label for="scv_game_type_label"><?php esc_html_e( 'Sport', 'sportlink-club-viewer' ); ?></label></th>
                        <td>
                            <select name="scv_game_type_label" id="scv_game_type_label">
                                <option value=""><?php esc_html_e( '— Kies een sport —', 'sportlink-club-viewer' ); ?></option>
                                <?php foreach ( $game_types as $gt ) : ?>
                                    <option value="<?php echo esc_attr( $gt['label'] ); ?>"
                                        <?php selected( $game_type_label, $gt['label'] ); ?>
                                        data-types="<?php echo esc_attr( wp_json_encode( array_column( $gt['types'], 'type' ) ) ); ?>">
                                        <?php echo esc_html( $gt['label'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="scv_connection_type"><?php esc_html_e( 'Verbindingstype', 'sportlink-club-viewer' ); ?></label></th>
                        <td>
                            <select name="scv_connection_type" id="scv_connection_type">
                                <option value=""><?php esc_html_e( '— Kies een type —', 'sportlink-club-viewer' ); ?></option>
                                <option value="Sportlink API"   <?php selected( $connection_type, 'Sportlink API' ); ?>><?php esc_html_e( 'Sportlink API', 'sportlink-club-viewer' ); ?></option>
                                <option value="Sportlink Proxy" <?php selected( $connection_type, 'Sportlink Proxy' ); ?>><?php esc_html_e( 'Sportlink Proxy', 'sportlink-club-viewer' ); ?></option>
                                <option value="Nevobo Proxy"    <?php selected( $connection_type, 'Nevobo Proxy' ); ?>><?php esc_html_e( 'Nevobo Proxy', 'sportlink-club-viewer' ); ?></option>
                            </select>
                            <p class="description" id="scv-connection-desc"></p>
                        </td>
                    </tr>

                    <!-- Sportlink API fields -->
                    <tr class="scv-field-sportlink-api scv-field-sportlink-proxy scv-field-hide">
                        <th scope="row"><label for="scv_client_id"><?php esc_html_e( 'Client ID', 'sportlink-club-viewer' ); ?></label></th>
                        <td>
                            <input type="text" name="scv_client_id" id="scv_client_id"
                                   value="<?php echo esc_attr( $client_id ); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'Je Sportlink client_id (alleen voor Sportlink API).', 'sportlink-club-viewer' ); ?></p>
                        </td>
                    </tr>

                    <!-- Sportlink Proxy fields -->
                    <tr class="scv-field-sportlink-proxy scv-field-hide">
                        <th scope="row"><label for="scv_username"><?php esc_html_e( 'Gebruikersnaam', 'sportlink-club-viewer' ); ?></label></th>
                        <td>
                            <input type="text" name="scv_username" id="scv_username"
                                   value="<?php echo esc_attr( $username ); ?>" class="regular-text" autocomplete="off">
                        </td>
                    </tr>
                    <tr class="scv-field-sportlink-proxy scv-field-hide">
                        <th scope="row"><label for="scv_password"><?php esc_html_e( 'Wachtwoord', 'sportlink-club-viewer' ); ?></label></th>
                        <td>
                            <input type="password" name="scv_password" id="scv_password"
                                   value="" class="regular-text" autocomplete="new-password"
                                   placeholder="<?php echo $club_id ? esc_attr__( '(ongewijzigd)', 'sportlink-club-viewer' ) : ''; ?>">
                            <p class="description"><?php esc_html_e( 'Laat leeg om het huidige wachtwoord te behouden.', 'sportlink-club-viewer' ); ?></p>
                        </td>
                    </tr>

                    <!-- Nevobo fields -->
                    <tr class="scv-field-nevobo scv-field-hide">
                        <th scope="row"><label for="scv_club_identifier"><?php esc_html_e( 'Club identifier', 'sportlink-club-viewer' ); ?></label></th>
                        <td>
                            <input type="text" name="scv_club_identifier" id="scv_club_identifier"
                                   value="<?php echo esc_attr( $club_identifier ); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'Je Nevobo-clubcode (bijv. NELO123).', 'sportlink-club-viewer' ); ?></p>
                        </td>
                    </tr>

                    <!-- Club fetch (Proxy types) -->
                    <tr class="scv-field-sportlink-proxy scv-field-nevobo scv-field-hide">
                        <th scope="row"><?php esc_html_e( 'Club ophalen', 'sportlink-club-viewer' ); ?></th>
                        <td>
                            <button type="button" id="scv-fetch-clubs" class="button button-secondary">
                                <?php esc_html_e( 'Clubs ophalen', 'sportlink-club-viewer' ); ?>
                            </button>
                            <span id="scv-fetch-spinner" class="spinner" style="float:none;vertical-align:middle;margin:0 5px;display:none;"></span>
                            <p class="description" id="scv-fetch-status"></p>

                            <div id="scv-club-select-wrap" style="margin-top:8px;<?php echo empty( $club_id ) ? 'display:none;' : ''; ?>">
                                <select name="scv_club_id" id="scv_club_id">
                                    <?php if ( $club_id ) : ?>
                                        <option value="<?php echo esc_attr( $club_id ); ?>" selected>
                                            <?php echo esc_html( $sport_locatie ?: $club_id ); ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </td>
                    </tr>

                    <!-- Sportlink API: location auto-filled -->
                    <tr class="scv-field-sportlink-api scv-field-hide">
                        <th scope="row"><label for="scv_sport_locatie_api"><?php esc_html_e( 'Locatienaam', 'sportlink-club-viewer' ); ?></label></th>
                        <td>
                            <input type="text" id="scv_sport_locatie_api" class="regular-text"
                                   value="<?php echo esc_attr( $sport_locatie ); ?>" readonly>
                            <input type="hidden" name="scv_sport_locatie" id="scv_sport_locatie" value="<?php echo esc_attr( $sport_locatie ); ?>">
                            <button type="button" id="scv-verify-client" class="button button-secondary" style="margin-left:8px;">
                                <?php esc_html_e( 'Verifiëren', 'sportlink-club-viewer' ); ?>
                            </button>
                            <span id="scv-verify-spinner" class="spinner" style="float:none;vertical-align:middle;margin:0 5px;display:none;"></span>
                            <p class="description" id="scv-verify-status"></p>
                        </td>
                    </tr>

                    <!-- Test / clear connection -->
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Verbinding testen', 'sportlink-club-viewer' ); ?></th>
                        <td>
                            <button type="button" id="scv-test-connection" class="button button-secondary">
                                <?php esc_html_e( 'Test verbinding', 'sportlink-club-viewer' ); ?>
                            </button>
                            <button type="button" id="scv-clear-connection" class="button button-secondary" style="margin-left:8px;">
                                <?php esc_html_e( 'Velden wissen', 'sportlink-club-viewer' ); ?>
                            </button>
                            <span id="scv-test-spinner" class="spinner" style="float:none;vertical-align:middle;margin:0 5px;display:none;"></span>
                            <p class="description" id="scv-test-status"></p>
                        </td>
                    </tr>

                </table>
            </div>

            <!-- Display settings -->
            <div class="scv-section">
                <h2><?php esc_html_e( 'Weergave', 'sportlink-club-viewer' ); ?></h2>
                <table class="form-table" role="presentation">

                    <tr>
                        <th scope="row"><label for="scv_programma_dagen"><?php esc_html_e( 'Programma (dagen vooruit)', 'sportlink-club-viewer' ); ?></label></th>
                        <td>
                            <input type="number" name="scv_programma_dagen" id="scv_programma_dagen"
                                   value="<?php echo esc_attr( $programma_dagen ); ?>" min="1" max="365" class="small-text">
                            <p class="description"><?php esc_html_e( 'Hoeveel dagen vooruit worden wedstrijden getoond.', 'sportlink-club-viewer' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="scv_uitslag_dagen"><?php esc_html_e( 'Uitslagen (dagen terug)', 'sportlink-club-viewer' ); ?></label></th>
                        <td>
                            <input type="number" name="scv_uitslag_dagen" id="scv_uitslag_dagen"
                                   value="<?php echo esc_attr( $uitslag_dagen ); ?>" min="1" max="365" class="small-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="scv_prematch_refresh"><?php esc_html_e( 'Voorwedstrijd verversing (seconden)', 'sportlink-club-viewer' ); ?></label></th>
                        <td>
                            <input type="number" name="scv_prematch_refresh" id="scv_prematch_refresh"
                                   value="<?php echo esc_attr( $prematch_refresh ); ?>" min="5" max="3600" class="small-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Scherm automatisch wisselen', 'sportlink-club-viewer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="scv_enable_screen_switch" value="1"
                                    <?php checked( 1, $enable_screen_switch ); ?>>
                                <?php esc_html_e( 'Wissel automatisch tussen programma en uitslagen na 2 scroll-rondes', 'sportlink-club-viewer' ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Sponsors weergeven', 'sportlink-club-viewer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="scv_active_sponsors" value="1"
                                    <?php checked( 1, $active_sponsors ); ?>>
                                <?php esc_html_e( 'Toon de sponsorbalk onderaan het scherm', 'sportlink-club-viewer' ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="scv_background_url"><?php esc_html_e( 'Achtergrondafbeelding (URL)', 'sportlink-club-viewer' ); ?></label></th>
                        <td>
                            <input type="url" name="scv_background_url" id="scv_background_url"
                                   value="<?php echo esc_attr( $background_url ); ?>" class="large-text">
                            <button type="button" id="scv-select-bg" class="button button-secondary" style="margin-top:5px;">
                                <?php esc_html_e( 'Kies uit mediabibliotheek', 'sportlink-club-viewer' ); ?>
                            </button>
                            <p class="description"><?php esc_html_e( 'Directe URL naar een afbeelding. Laat leeg voor standaard achtergrond.', 'sportlink-club-viewer' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="scv_display_height"><?php esc_html_e( 'Weergavehoogte (px)', 'sportlink-club-viewer' ); ?></label></th>
                        <td>
                            <input type="number" name="scv_display_height" id="scv_display_height"
                                   value="<?php echo esc_attr( $display_height ); ?>" min="0" max="9999" class="small-text">
                            <p class="description"><?php esc_html_e( '0 = automatisch (vult de schermhoogte). Stel een pixelwaarde in om de hoogte vast te zetten (bijv. 800).', 'sportlink-club-viewer' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="scv_scroll_speed"><?php esc_html_e( 'Scrollsnelheid (px per stap)', 'sportlink-club-viewer' ); ?></label></th>
                        <td>
                            <input type="number" name="scv_scroll_speed" id="scv_scroll_speed"
                                   value="<?php echo esc_attr( $scroll_speed ); ?>" min="1" max="20" class="small-text">
                            <p class="description"><?php esc_html_e( 'Pixels per stap (elke 50ms). Standaard: 2. Hoger = sneller scrollen.', 'sportlink-club-viewer' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Debug modus', 'sportlink-club-viewer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="scv_debug_mode" value="1"
                                    <?php checked( 1, $debug_mode ); ?>>
                                <?php esc_html_e( 'Toon ruwe API-antwoorden in de browserconsole (F12)', 'sportlink-club-viewer' ); ?>
                            </label>
                        </td>
                    </tr>

                </table>
            </div>

            <!-- Standings settings -->
            <?php
            $enable_standings   = get_option( 'scv_enable_standings', 0 );
            $standing_team_id   = get_option( 'scv_standing_team_id', '' );
            $standing_team_name = get_option( 'scv_standing_team_name', '' );
            $standing_pool_id   = get_option( 'scv_standing_pool_id', '' );
            $standing_pool_name = get_option( 'scv_standing_pool_name', '' );
            $conn               = get_option( 'scv_connection_type', '' );
            $is_proxy           = $conn === 'Sportlink Proxy';
            $is_api             = $conn === 'Sportlink API';
            $show_team_row      = ( $is_proxy && ! empty( $club_id ) ) || ( $is_api && ! empty( $client_id ) );
            $show_comp_row      = $show_team_row && ! empty( $standing_team_id );
            ?>
            <div class="scv-section">
                <h2><?php esc_html_e( 'Team standen', 'sportlink-club-viewer' ); ?></h2>
                <table class="form-table" role="presentation">

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Standen weergeven', 'sportlink-club-viewer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="scv_enable_standings" value="1"
                                    <?php checked( 1, $enable_standings ); ?>>
                                <?php esc_html_e( 'Toon de stand van het geselecteerde team', 'sportlink-club-viewer' ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr id="scv-standings-team-row" <?php echo $show_team_row ? '' : 'style="display:none;"'; ?>>
                        <th scope="row"><?php esc_html_e( 'Team', 'sportlink-club-viewer' ); ?></th>
                        <td>
                            <button type="button" id="scv-fetch-teams" class="button button-secondary">
                                <?php esc_html_e( 'Teams ophalen', 'sportlink-club-viewer' ); ?>
                            </button>
                            <span id="scv-fetch-teams-spinner" class="spinner" style="float:none;vertical-align:middle;margin:0 5px;display:none;"></span>
                            <p class="description" id="scv-fetch-teams-status"></p>

                            <div id="scv-team-select-wrap" style="margin-top:8px;<?php echo empty( $standing_team_id ) ? 'display:none;' : ''; ?>">
                                <select name="scv_standing_team_id" id="scv_standing_team_id">
                                    <?php if ( $standing_team_id ) : ?>
                                        <option value="<?php echo esc_attr( $standing_team_id ); ?>" selected>
                                            <?php echo esc_html( $standing_team_name ?: $standing_team_id ); ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                                <input type="hidden" name="scv_standing_team_name" id="scv_standing_team_name"
                                       value="<?php echo esc_attr( $standing_team_name ); ?>">
                            </div>
                        </td>
                    </tr>

                    <tr id="scv-standings-comp-row" <?php echo $show_comp_row ? '' : 'style="display:none;"'; ?>>
                        <th scope="row"><?php esc_html_e( 'Competitie', 'sportlink-club-viewer' ); ?></th>
                        <td>
                            <button type="button" id="scv-fetch-competitions" class="button button-secondary">
                                <?php esc_html_e( 'Competities ophalen', 'sportlink-club-viewer' ); ?>
                            </button>
                            <span id="scv-fetch-comp-spinner" class="spinner" style="float:none;vertical-align:middle;margin:0 5px;display:none;"></span>
                            <p class="description" id="scv-fetch-comp-status"></p>

                            <div id="scv-comp-select-wrap" style="margin-top:8px;<?php echo empty( $standing_pool_id ) ? 'display:none;' : ''; ?>">
                                <select name="scv_standing_pool_id" id="scv_standing_pool_id">
                                    <?php if ( $standing_pool_id ) : ?>
                                        <option value="<?php echo esc_attr( $standing_pool_id ); ?>" selected>
                                            <?php echo esc_html( $standing_pool_name ?: $standing_pool_id ); ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                                <input type="hidden" name="scv_standing_pool_name" id="scv_standing_pool_name"
                                       value="<?php echo esc_attr( $standing_pool_name ); ?>">
                            </div>
                        </td>
                    </tr>

                    <tr id="scv-standings-no-proxy" <?php echo ( $is_proxy || $is_api ) ? 'style="display:none;"' : ''; ?>>
                        <th></th>
                        <td>
                            <p class="description"><?php esc_html_e( 'Team standen zijn alleen beschikbaar bij verbindingstype Sportlink API of Sportlink Proxy.', 'sportlink-club-viewer' ); ?></p>
                        </td>
                    </tr>

                </table>
                <p class="description"><?php esc_html_e( 'Gebruik shortcode [sportlink_standing_display] om de stand op een pagina te tonen.', 'sportlink-club-viewer' ); ?></p>
            </div>

            <?php submit_button( __( 'Instellingen opslaan', 'sportlink-club-viewer' ) ); ?>
        </form>
        <?php
    }

    // ── Tab: Style ────────────────────────────────────────────────────────────

    private static function render_style_tab() {
        $colors = get_option( 'scv_colors', [] );
        $color_defaults = [
            'leftBoxColor'     => '#b40808', 'leftBoxText'      => '#ffffff',
            'leftMidBoxColor'  => '#000000', 'leftMidBoxText'   => '#ffffff',
            'midBoxColor'      => '#de0b0b', 'midBoxText'       => '#ffffff',
            'rightMidBoxColor' => '#000000', 'rightMidBoxText'  => '#ffffff',
            'rightBoxColor'    => '#b40808', 'rightBoxText'     => '#ffffff',
        ];
        $c = array_merge( $color_defaults, (array) $colors );

        $layout_defaults = [
            'leftWidth' => 2, 'leftMidWidth' => 9, 'midWidth' => 4, 'rightMidWidth' => 9, 'rightWidth' => 3,
            'leftVisible' => 1, 'leftMidVisible' => 1, 'midVisible' => 1, 'rightMidVisible' => 1, 'rightVisible' => 1,
            'showLogos' => 1,
        ];
        $l = array_merge( $layout_defaults, (array) get_option( 'scv_layout', [] ) );

        $sc_defaults = [
            'totalMatches' => 1, 'won' => 1, 'draw' => 1, 'lost' => 1,
            'goalsFor' => 1, 'goalsAgainst' => 1, 'goalsDiff' => 1, 'points' => 1,
        ];
        $sc = array_merge( $sc_defaults, (array) get_option( 'scv_standing_columns', [] ) );

        $ot_defaults = [ 'bg' => '#1a5c1a', 'text' => '#ffffff' ];
        $ot = array_merge( $ot_defaults, (array) get_option( 'scv_own_team_colors', [] ) );

        $sections = [
            'left'     => [ 'label' => __( 'Links — Datum', 'sportlink-club-viewer' ),              'bg' => 'leftBoxColor',     'text' => 'leftBoxText',     'widthKey' => 'leftWidth',     'visKey' => 'leftVisible' ],
            'leftMid'  => [ 'label' => __( 'Links midden — Thuisteam', 'sportlink-club-viewer' ),   'bg' => 'leftMidBoxColor',  'text' => 'leftMidBoxText',  'widthKey' => 'leftMidWidth',  'visKey' => 'leftMidVisible' ],
            'mid'      => [ 'label' => __( 'Midden — Aanvang / Uitslag', 'sportlink-club-viewer' ), 'bg' => 'midBoxColor',      'text' => 'midBoxText',      'widthKey' => 'midWidth',      'visKey' => 'midVisible' ],
            'rightMid' => [ 'label' => __( 'Rechts midden — Gasten', 'sportlink-club-viewer' ),     'bg' => 'rightMidBoxColor', 'text' => 'rightMidBoxText', 'widthKey' => 'rightMidWidth', 'visKey' => 'rightMidVisible' ],
            'right'    => [ 'label' => __( 'Rechts — Competitie', 'sportlink-club-viewer' ),        'bg' => 'rightBoxColor',    'text' => 'rightBoxText',    'widthKey' => 'rightWidth',    'visKey' => 'rightVisible' ],
        ];
        ?>
        <form method="post" action="options.php" id="scv-style-form">
            <?php settings_fields( 'scv_style' ); ?>

            <div class="scv-section">
                <h2><?php esc_html_e( 'Kolommen', 'sportlink-club-viewer' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Stel kleur, breedte en zichtbaarheid in per kolom. Breedte is een verhouding: hogere waarde = bredere kolom.', 'sportlink-club-viewer' ); ?></p>

                <!-- Live preview -->
                <div class="scv-color-preview" id="scv-color-preview">
                    <?php foreach ( $sections as $key => $sec ) : ?>
                        <div class="scv-preview-col" id="preview-<?php echo esc_attr( $key ); ?>"
                             style="background:<?php echo esc_attr( $c[ $sec['bg'] ] ); ?>;color:<?php echo esc_attr( $c[ $sec['text'] ] ); ?>;flex:<?php echo esc_attr( $l[ $sec['widthKey'] ] ); ?>;<?php echo $l[ $sec['visKey'] ] ? '' : 'display:none;'; ?>">
                            <?php echo esc_html( $sec['label'] ); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <table class="form-table" role="presentation">
                    <?php foreach ( $sections as $key => $sec ) :
                        $bg_key   = $sec['bg'];
                        $text_key = $sec['text'];
                    ?>
                    <tr>
                        <th scope="row"><?php echo esc_html( $sec['label'] ); ?></th>
                        <td class="scv-color-row">
                            <span class="scv-color-label"><?php esc_html_e( 'Achtergrond', 'sportlink-club-viewer' ); ?></span>
                            <input type="text" name="scv_colors[<?php echo esc_attr( $bg_key ); ?>]"
                                   value="<?php echo esc_attr( $c[ $bg_key ] ); ?>"
                                   class="scv-color-picker"
                                   data-preview-col="<?php echo esc_attr( $key ); ?>"
                                   data-preview-prop="background">
                            &nbsp;&nbsp;
                            <span class="scv-color-label"><?php esc_html_e( 'Tekst', 'sportlink-club-viewer' ); ?></span>
                            <input type="text" name="scv_colors[<?php echo esc_attr( $text_key ); ?>]"
                                   value="<?php echo esc_attr( $c[ $text_key ] ); ?>"
                                   class="scv-color-picker"
                                   data-preview-col="<?php echo esc_attr( $key ); ?>"
                                   data-preview-prop="color">
                            <span class="scv-color-label" style="margin-left:16px;"><?php esc_html_e( 'Breedte', 'sportlink-club-viewer' ); ?></span>
                            <input type="number"
                                   name="scv_layout[<?php echo esc_attr( $sec['widthKey'] ); ?>]"
                                   value="<?php echo esc_attr( $l[ $sec['widthKey'] ] ); ?>"
                                   min="1" max="20" step="1"
                                   class="small-text scv-layout-width"
                                   data-preview-col="<?php echo esc_attr( $key ); ?>">
                            <label style="margin-left:12px;">
                                <input type="checkbox"
                                       name="scv_layout[<?php echo esc_attr( $sec['visKey'] ); ?>]"
                                       value="1"
                                       <?php checked( 1, $l[ $sec['visKey'] ] ); ?>
                                       class="scv-layout-visible"
                                       data-preview-col="<?php echo esc_attr( $key ); ?>">
                                <?php esc_html_e( 'Zichtbaar', 'sportlink-club-viewer' ); ?>
                            </label>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Logo\'s', 'sportlink-club-viewer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="scv_layout[showLogos]" value="1"
                                    <?php checked( 1, $l['showLogos'] ); ?>>
                                <?php esc_html_e( 'Logo\'s weergeven (programma & uitslagen)', 'sportlink-club-viewer' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Standing columns -->
            <div class="scv-section">
                <h2><?php esc_html_e( 'Standen kolommen', 'sportlink-club-viewer' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Kies welke statistiekkolommen worden getoond. Positie en teamnaam zijn altijd zichtbaar.', 'sportlink-club-viewer' ); ?></p>
                <table class="form-table" role="presentation">
                    <?php
                    $col_defs = [
                        'totalMatches' => __( 'M — Gespeeld',           'sportlink-club-viewer' ),
                        'won'          => __( 'W — Gewonnen',           'sportlink-club-viewer' ),
                        'draw'         => __( 'G — Gelijk',             'sportlink-club-viewer' ),
                        'lost'         => __( 'V — Verloren',           'sportlink-club-viewer' ),
                        'goalsFor'     => __( '+ — Goals voor',         'sportlink-club-viewer' ),
                        'goalsAgainst' => __( '- — Goals tegen',        'sportlink-club-viewer' ),
                        'goalsDiff'    => __( '+/- — Doelpuntensaldo',  'sportlink-club-viewer' ),
                        'points'       => __( 'Pts — Punten',           'sportlink-club-viewer' ),
                    ];
                    foreach ( $col_defs as $key => $label ) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html( $label ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="scv_standing_columns[<?php echo esc_attr( $key ); ?>]"
                                       value="1"
                                    <?php checked( 1, $sc[ $key ] ); ?>>
                                <?php esc_html_e( 'Weergeven', 'sportlink-club-viewer' ); ?>
                            </label>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <!-- Own team highlight colours -->
            <div class="scv-section">
                <h2><?php esc_html_e( 'Eigen team markering', 'sportlink-club-viewer' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Achtergrond- en tekstkleur voor de eigen teamrij in de stand.', 'sportlink-club-viewer' ); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Achtergrond / tekst', 'sportlink-club-viewer' ); ?></th>
                        <td class="scv-color-row">
                            <span class="scv-color-label"><?php esc_html_e( 'Achtergrond', 'sportlink-club-viewer' ); ?></span>
                            <input type="text" name="scv_own_team_colors[bg]"
                                   value="<?php echo esc_attr( $ot['bg'] ); ?>"
                                   class="scv-color-picker">
                            &nbsp;&nbsp;
                            <span class="scv-color-label"><?php esc_html_e( 'Tekst', 'sportlink-club-viewer' ); ?></span>
                            <input type="text" name="scv_own_team_colors[text]"
                                   value="<?php echo esc_attr( $ot['text'] ); ?>"
                                   class="scv-color-picker">
                        </td>
                    </tr>
                </table>
            </div>

            <div class="scv-reset-row">
                <button type="button" id="scv-reset-defaults" class="button button-secondary">
                    <?php esc_html_e( 'Terugzetten naar standaard', 'sportlink-club-viewer' ); ?>
                </button>
            </div>

            <?php submit_button( __( 'Stijl opslaan', 'sportlink-club-viewer' ) ); ?>
        </form>
        <?php
    }

    // ── Tab: Sponsors ─────────────────────────────────────────────────────────

    private static function render_sponsors_tab() {
        $sponsors = (array) get_option( 'scv_sponsors', [] );
        $max = 13;
        ?>
        <form method="post" action="options.php" id="scv-sponsors-form">
            <?php settings_fields( 'scv_sponsors_group' ); ?>

            <div class="scv-section">
                <h2><?php esc_html_e( 'Sponsorafbeeldingen', 'sportlink-club-viewer' ); ?></h2>
                <p class="description">
                    <?php printf(
                        esc_html__( 'Voeg maximaal %d sponsorafbeeldingen toe. Aanbevolen formaat: 220×52 pixels.', 'sportlink-club-viewer' ),
                        $max
                    ); ?>
                </p>

                <div id="scv-sponsors-list" class="scv-sponsors-grid">
                    <?php foreach ( $sponsors as $i => $url ) : ?>
                        <div class="scv-sponsor-item" data-index="<?php echo esc_attr( $i ); ?>">
                            <img src="<?php echo esc_url( $url ); ?>" alt="">
                            <input type="hidden" name="scv_sponsors[]" value="<?php echo esc_attr( $url ); ?>">
                            <button type="button" class="scv-remove-sponsor button button-small button-link-delete">
                                <?php esc_html_e( 'Verwijderen', 'sportlink-club-viewer' ); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="scv-sponsor-add" style="margin-top:16px;">
                    <?php if ( count( $sponsors ) < $max ) : ?>
                        <button type="button" id="scv-add-sponsor-media" class="button button-secondary">
                            <?php esc_html_e( 'Afbeelding toevoegen uit mediabibliotheek', 'sportlink-club-viewer' ); ?>
                        </button>
                        <span style="margin:0 8px;"><?php esc_html_e( 'of', 'sportlink-club-viewer' ); ?></span>
                        <input type="url" id="scv-sponsor-url-input" class="regular-text"
                               placeholder="<?php esc_attr_e( 'Directe afbeelding-URL…', 'sportlink-club-viewer' ); ?>">
                        <button type="button" id="scv-add-sponsor-url" class="button button-secondary">
                            <?php esc_html_e( 'URL toevoegen', 'sportlink-club-viewer' ); ?>
                        </button>
                    <?php else : ?>
                        <p class="description"><?php esc_html_e( 'Maximum aantal sponsors bereikt (13).', 'sportlink-club-viewer' ); ?></p>
                    <?php endif; ?>
                    <p id="scv-sponsor-count" class="description" style="margin-top:8px;">
                        <?php printf(
                            esc_html__( '%d van %d sponsorplaatsen in gebruik.', 'sportlink-club-viewer' ),
                            count( $sponsors ),
                            $max
                        ); ?>
                    </p>
                </div>
            </div>

            <?php submit_button( __( 'Sponsors opslaan', 'sportlink-club-viewer' ) ); ?>
        </form>
        <?php
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function get_game_types() {
        return [
            [ 'label' => 'Voetbal',         'types' => [ 'Sportlink API', 'Sportlink Proxy' ] ],
            [ 'label' => 'Basketbal',        'types' => [ 'Sportlink API', 'Sportlink Proxy' ] ],
            [ 'label' => 'Korfbal',          'types' => [ 'Sportlink API', 'Sportlink Proxy' ] ],
            [ 'label' => 'Soft- en Honkbal', 'types' => [ 'Sportlink API', 'Sportlink Proxy' ] ],
            [ 'label' => 'Volleybal',        'types' => [ 'Sportlink API', 'Nevobo Proxy'    ] ],
            [ 'label' => 'Waterpolo',        'types' => [ 'Sportlink API', 'Sportlink Proxy' ] ],
            [ 'label' => 'Hockey België',    'types' => [ 'Sportlink API', 'Sportlink Proxy' ] ],
            [ 'label' => 'Handbal',          'types' => [ 'Sportlink API', 'Sportlink Proxy' ] ],
        ];
    }

    /**
     * Build the scvConfig object passed to the Vue app via wp_localize_script.
     */
    public static function build_vue_config( string $app_type ): array {
        $colors   = (array) get_option( 'scv_colors', [] );
        $sponsors = (array) get_option( 'scv_sponsors', [] );

        $ot_defaults = [ 'bg' => '#1a5c1a', 'text' => '#ffffff' ];
        $ot = array_merge( $ot_defaults, (array) get_option( 'scv_own_team_colors', [] ) );

        $color_defaults = [
            'leftBoxColor'     => '#b40808', 'leftBoxText'      => '#ffffff',
            'leftMidBoxColor'  => '#000000', 'leftMidBoxText'   => '#ffffff',
            'midBoxColor'      => '#de0b0b', 'midBoxText'       => '#ffffff',
            'rightMidBoxColor' => '#000000', 'rightMidBoxText'  => '#ffffff',
            'rightBoxColor'    => '#b40808', 'rightBoxText'     => '#ffffff',
        ];
        $c = array_merge( $color_defaults, $colors );

        $layout_defaults = [
            'leftWidth' => 2, 'leftMidWidth' => 9, 'midWidth' => 4, 'rightMidWidth' => 9, 'rightWidth' => 3,
            'leftVisible' => 1, 'leftMidVisible' => 1, 'midVisible' => 1, 'rightMidVisible' => 1, 'rightVisible' => 1,
            'showLogos' => 1,
        ];
        $l = array_merge( $layout_defaults, (array) get_option( 'scv_layout', [] ) );

        $sc_defaults = [
            'totalMatches' => 1, 'won' => 1, 'draw' => 1, 'lost' => 1,
            'goalsFor' => 1, 'goalsAgainst' => 1, 'goalsDiff' => 1, 'points' => 1,
        ];
        $sc = array_merge( $sc_defaults, (array) get_option( 'scv_standing_columns', [] ) );

        return [
            'appType'            => $app_type,
            'gameTypeLabel'      => get_option( 'scv_game_type_label', '' ),
            'connectionType'     => get_option( 'scv_connection_type', '' ),
            'clientId'           => get_option( 'scv_client_id', '' ),
            'clubId'             => get_option( 'scv_club_id', '' ),
            'clubIdentifer'      => get_option( 'scv_club_identifier', '' ), // typo preserved for compat
            'sportLocatie'       => get_option( 'scv_sport_locatie', '' ),
            'username'           => get_option( 'scv_username', '' ),
            'password'           => get_option( 'scv_password', '' ),
            'fakeCredentials'    => (bool) get_option( 'scv_fake_credentials', 0 ),
            'programmaDagen'     => (int)  get_option( 'scv_programma_dagen', 7 ),
            'uitslagDagen'       => (int)  get_option( 'scv_uitslag_dagen', 7 ),
            'prematchRefresh'    => (int)  get_option( 'scv_prematch_refresh', 15 ),
            'enableScreenSwitch' => (bool) get_option( 'scv_enable_screen_switch', 1 ),
            'activeSponsors'     => (bool) get_option( 'scv_active_sponsors', 0 ),
            'debug'              => (bool) get_option( 'scv_debug_mode', 0 ),
            'selectedBackground' => get_option( 'scv_background_url', '' ),
            'displayHeight'      => (int)  get_option( 'scv_display_height', 0 ),
            'scrollSpeed'        => max( 1, (int) get_option( 'scv_scroll_speed', 2 ) ),
            'sponsorImages'      => array_values( $sponsors ),
            // Own team highlight
            'ownTeamBg'        => $ot['bg'],
            'ownTeamText'      => $ot['text'],
            // Standings
            'enableStandings'  => (bool) get_option( 'scv_enable_standings', 0 ),
            'standingTeamId'   => get_option( 'scv_standing_team_id', '' ),
            'standingPoolId'   => get_option( 'scv_standing_pool_id', '' ),
            'standingColumns'  => [
                'totalMatches' => (bool) $sc['totalMatches'],
                'won'          => (bool) $sc['won'],
                'draw'         => (bool) $sc['draw'],
                'lost'         => (bool) $sc['lost'],
                'goalsFor'     => (bool) $sc['goalsFor'],
                'goalsAgainst' => (bool) $sc['goalsAgainst'],
                'goalsDiff'    => (bool) $sc['goalsDiff'],
                'points'       => (bool) $sc['points'],
            ],
            // Layout
            'columnWidths'  => [
                'left'     => (int) $l['leftWidth'],
                'leftMid'  => (int) $l['leftMidWidth'],
                'mid'      => (int) $l['midWidth'],
                'rightMid' => (int) $l['rightMidWidth'],
                'right'    => (int) $l['rightWidth'],
            ],
            'columnVisible' => [
                'left'     => (bool) $l['leftVisible'],
                'leftMid'  => (bool) $l['leftMidVisible'],
                'mid'      => (bool) $l['midVisible'],
                'rightMid' => (bool) $l['rightMidVisible'],
                'right'    => (bool) $l['rightVisible'],
            ],
            'showLogos'     => (bool) $l['showLogos'],
            // Colors
            'leftBoxColor'       => $c['leftBoxColor'],
            'leftBoxText'        => $c['leftBoxText'],
            'leftMidBoxColor'    => $c['leftMidBoxColor'],
            'leftMidBoxText'     => $c['leftMidBoxText'],
            'midBoxColor'        => $c['midBoxColor'],
            'midBoxText'         => $c['midBoxText'],
            'rightMidBoxColor'   => $c['rightMidBoxColor'],
            'rightMidBoxText'    => $c['rightMidBoxText'],
            'rightBoxColor'      => $c['rightBoxColor'],
            'rightBoxText'       => $c['rightBoxText'],
        ];
    }
}

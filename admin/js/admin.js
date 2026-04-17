/* global jQuery, scvAdmin, wp */
( function ( $ ) {
    'use strict';

    // ── Tab memory ────────────────────────────────────────────────────────────
    // (WP handles tab switching via URL; this file handles in-page behaviour)

    // ── Connection type visibility ─────────────────────────────────────────────

    const CONNECTION_DESCRIPTIONS = {
        'Sportlink API':   'Directe verbinding met de Sportlink data-API. Vul je club\'s client ID in.',
        'Sportlink Proxy': 'Verbinding via proxy met de Sportlink-app. Vul gebruikersnaam en wachtwoord in.',
        'Nevobo Proxy':    'Verbinding via proxy met de Nevobo-API (volleybal). Vul je Nevobo club-identifier in.',
    };

    function updateStandingsVisibility() {
        const connType  = $( '#scv_connection_type' ).val();
        const isProxy   = connType === 'Sportlink Proxy';
        const isApi     = connType === 'Sportlink API';
        const hasClub   = !! $( '#scv_club_id' ).val();
        const hasClient = !! $( '#scv_client_id' ).val();
        const hasTeam   = !! $( '#scv_standing_team_id' ).val();
        const canFetch  = ( isProxy && hasClub ) || ( isApi && hasClient );
        $( '#scv-standings-team-row' ).toggle( canFetch );
        $( '#scv-standings-comp-row' ).toggle( canFetch && hasTeam );
        $( '#scv-standings-no-proxy' ).toggle( ! isProxy && ! isApi );
    }

    function updateFieldVisibility() {
        const connType  = $( '#scv_connection_type' ).val();
        const gameLabel = $( '#scv_game_type_label' ).val();

        // Hide all conditional rows first
        $( '.scv-field-sportlink-api, .scv-field-sportlink-proxy, .scv-field-nevobo' ).addClass( 'scv-field-hide' );

        if ( connType === 'Sportlink API' ) {
            $( '.scv-field-sportlink-api' ).removeClass( 'scv-field-hide' );
        } else if ( connType === 'Sportlink Proxy' ) {
            $( '.scv-field-sportlink-proxy' ).removeClass( 'scv-field-hide' );
        } else if ( connType === 'Nevobo Proxy' ) {
            $( '.scv-field-nevobo' ).removeClass( 'scv-field-hide' );
        }

        $( '#scv-connection-desc' ).text( CONNECTION_DESCRIPTIONS[ connType ] || '' );

        // Update connection type options based on selected sport
        const $sportSelect = $( '#scv_game_type_label option:selected' );
        if ( $sportSelect.length && gameLabel ) {
            try {
                const types = JSON.parse( $sportSelect.data( 'types' ) || '[]' );
                $( '#scv_connection_type option' ).each( function () {
                    const val = $( this ).val();
                    if ( val && ! types.includes( val ) ) {
                        $( this ).prop( 'disabled', true ).hide();
                    } else {
                        $( this ).prop( 'disabled', false ).show();
                    }
                } );
            } catch ( e ) { /* ignore */ }
        }
    }

    $( '#scv_connection_type, #scv_game_type_label' ).on( 'change', updateFieldVisibility );
    $( '#scv_connection_type' ).on( 'change', updateStandingsVisibility );
    $( '#scv_club_id' ).on( 'change', updateStandingsVisibility );
    $( '#scv_client_id' ).on( 'input change', updateStandingsVisibility );
    updateFieldVisibility();
    updateStandingsVisibility();

    // ── Fetch clubs (Sportlink Proxy / Nevobo) ────────────────────────────────

    $( '#scv-fetch-clubs' ).on( 'click', function () {
        const $btn     = $( this );
        const $spinner = $( '#scv-fetch-spinner' );
        const $status  = $( '#scv-fetch-status' );

        $btn.prop( 'disabled', true );
        $spinner.show();
        $status.text( 'Clubs ophalen…' ).removeClass( 'scv-error scv-success' );

        const requestData = {
            action:          'scv_fetch_clubs',
            nonce:           scvAdmin.nonce,
            connection_type: $( '#scv_connection_type' ).val(),
            game_type_label: $( '#scv_game_type_label' ).val(),
            username:        $( '#scv_username' ).val(),
            password:        $( '#scv_password' ).val() ? '(ingevuld)' : '(leeg)',
        };

        if ( scvAdmin.debugMode ) {
            console.group( 'SCV debug — Clubs ophalen: verzoek' );
            console.log( 'Verbindingstype:', requestData.connection_type );
            console.log( 'Sporttype:', requestData.game_type_label );
            console.log( 'Gebruikersnaam:', requestData.username );
            console.log( 'Wachtwoord:', requestData.password );
            console.groupEnd();
        }

        $.ajax( {
            url:    scvAdmin.ajaxUrl,
            method: 'POST',
            data:   {
                action:          'scv_fetch_clubs',
                nonce:           scvAdmin.nonce,
                connection_type: $( '#scv_connection_type' ).val(),
                game_type_label: $( '#scv_game_type_label' ).val(),
                username:        $( '#scv_username' ).val(),
                password:        $( '#scv_password' ).val(),
            },
        } )
        .done( function ( response ) {
            if ( scvAdmin.debugMode ) {
                console.group( 'SCV debug — Clubs ophalen: antwoord' );

                if ( response.data?.debug_token ) {
                    const tok = response.data.debug_token;
                    console.group( 'Stap 1 — Token ophalen' );
                    console.log( 'HTTP-statuscode:', tok.response_code );
                    console.log( 'Antwoord-headers:', tok.response_headers );
                    console.log( 'Cookies doorgestuurd naar clubs-verzoek:', tok.cookies_forwarded );
                    console.log( 'Refresh token aanwezig:', tok.has_refresh_token );
                    console.groupEnd();
                }

                if ( response.data?.debug_request ) {
                    const req = response.data.debug_request;
                    console.group( 'Stap 2 — Clubs ophalen (wat WordPress stuurde)' );
                    console.log( 'URL:', req.url );
                    console.log( 'User-Agent arg:', req.user_agent_arg );
                    console.log( 'Headers:', req.headers_sent );
                    console.log( 'HTTP-statuscode terug:', req.response_code );
                    console.log( 'Antwoord-headers:', req.response_headers );
                    console.groupEnd();
                }

                if ( response.data?.debug_keys?.length ) {
                    console.log( 'API-veldnamen (eerste item):', response.data.debug_keys );
                } else {
                    console.warn( 'Geen API-veldnamen — response structuur niet herkend' );
                }
                if ( response.data?.debug_raw ) {
                    console.log( 'Ruwe API-respons (eerste 4 KB):', response.data.debug_raw );
                    try {
                        console.log( 'Geparseerde ruwe respons:', JSON.parse( response.data.debug_raw ) );
                    } catch ( e ) {
                        console.warn( 'Ruwe respons is geen geldige JSON' );
                    }
                }
                console.groupEnd();
            }

            if ( response.success && response.data.clubs && response.data.clubs.length ) {
                const hasNames = response.data.clubs.some( c => c.name );
                if ( hasNames ) {
                    populateClubSelect( response.data.clubs );
                    $status.text( response.data.clubs.length + ' clubs gevonden.' ).addClass( 'scv-success' );
                } else {
                    // Clubs returned but names are empty — show available API fields for diagnosis
                    const keys = response.data.debug_keys ? response.data.debug_keys.join( ', ' ) : '(onbekend)';
                    $status.text( 'Clubs gevonden maar veldnamen komen niet overeen. Beschikbare API-velden: ' + keys + ' — meld dit zodat het opgelost kan worden.' ).addClass( 'scv-error' );
                }
            } else {
                $status.text( response.data?.message || 'Geen clubs gevonden.' ).addClass( 'scv-error' );
            }
        } )
        .fail( function ( jqXHR, textStatus, errorThrown ) {
            if ( scvAdmin.debugMode ) {
                console.group( 'SCV debug — Clubs ophalen: verbindingsfout' );
                console.log( 'Status:', textStatus );
                console.log( 'HTTP-fout:', errorThrown );
                console.log( 'Respons:', jqXHR.responseText );
                console.groupEnd();
            }
            $status.text( 'Verbindingsfout. Probeer opnieuw.' ).addClass( 'scv-error' );
        } )
        .always( function () {
            $btn.prop( 'disabled', false );
            $spinner.hide();
        } );
    } );

    function populateClubSelect( clubs ) {
        const $wrap   = $( '#scv-club-select-wrap' );
        const $select = $( '#scv_club_id' );
        const current = $select.val();

        $select.empty().append( '<option value="">' + '— Kies een club —' + '</option>' );
        clubs.forEach( function ( club ) {
            const label = club.name + ( club.city ? ' (' + club.city + ')' : '' );
            $select.append( $( '<option>', { value: club.id, text: label } ) );
        } );

        if ( current ) $select.val( current );
        $wrap.show();

        // When a club is selected, update the hidden location field
        $select.on( 'change', function () {
            const selected = clubs.find( c => c.id === $( this ).val() );
            if ( selected ) {
                $( '#scv_sport_locatie' ).val( selected.city || selected.name );
            }
        } );
    }

    // ── Fetch teams for standings ─────────────────────────────────────────────

    $( '#scv-fetch-teams' ).on( 'click', function () {
        const $btn     = $( this );
        const $spinner = $( '#scv-fetch-teams-spinner' );
        const $status  = $( '#scv-fetch-teams-status' );

        $btn.prop( 'disabled', true );
        $spinner.show();
        $status.text( 'Teams ophalen…' ).removeClass( 'scv-error scv-success' );

        $.ajax( {
            url:    scvAdmin.ajaxUrl,
            method: 'POST',
            data:   {
                action:          'scv_fetch_teams',
                nonce:           scvAdmin.nonce,
                connection_type: $( '#scv_connection_type' ).val(),
                client_id:       $( '#scv_client_id' ).val(),
                club_id:         $( '#scv_club_id' ).val(),
                game_type_label: $( '#scv_game_type_label' ).val(),
                username:        $( '#scv_username' ).val(),
            },
        } )
        .done( function ( response ) {
            if ( response.success && response.data.teams && response.data.teams.length ) {
                populateTeamSelect( response.data.teams );
                $status.text( response.data.teams.length + ' teams gevonden.' ).addClass( 'scv-success' );
            } else {
                $status.text( response.data?.message || 'Geen teams gevonden.' ).addClass( 'scv-error' );
            }
        } )
        .fail( function () {
            $status.text( 'Verbindingsfout. Probeer opnieuw.' ).addClass( 'scv-error' );
        } )
        .always( function () {
            $btn.prop( 'disabled', false );
            $spinner.hide();
        } );
    } );

    function populateTeamSelect( teams ) {
        const $wrap   = $( '#scv-team-select-wrap' );
        const $select = $( '#scv_standing_team_id' );
        const current = $select.val();

        $select.empty().append( '<option value="">' + '— Kies een team —' + '</option>' );
        teams.forEach( function ( team ) {
            $select.append( $( '<option>', { value: team.id, text: team.name } ) );
        } );

        if ( current ) $select.val( current );
        $wrap.show();

        $select.on( 'change', function () {
            const selected = teams.find( t => t.id === $( this ).val() );
            if ( selected ) {
                $( '#scv_standing_team_name' ).val( selected.name );
            }
        } );
    }

    // ── Fetch competitions for standings team ─────────────────────────────────

    $( '#scv-fetch-competitions' ).on( 'click', function () {
        const $btn     = $( this );
        const $spinner = $( '#scv-fetch-comp-spinner' );
        const $status  = $( '#scv-fetch-comp-status' );

        $btn.prop( 'disabled', true );
        $spinner.show();
        $status.text( 'Competities ophalen…' ).removeClass( 'scv-error scv-success' );

        $.ajax( {
            url:    scvAdmin.ajaxUrl,
            method: 'POST',
            data:   {
                action:          'scv_fetch_competitions',
                nonce:           scvAdmin.nonce,
                connection_type: $( '#scv_connection_type' ).val(),
                client_id:       $( '#scv_client_id' ).val(),
                team_id:         $( '#scv_standing_team_id' ).val(),
                game_type_label: $( '#scv_game_type_label' ).val(),
                username:        $( '#scv_username' ).val(),
            },
        } )
        .done( function ( response ) {
            if ( response.success && response.data.competitions && response.data.competitions.length ) {
                populateCompetitionSelect( response.data.competitions );
                $status.text( response.data.competitions.length + ' competities gevonden.' ).addClass( 'scv-success' );
            } else {
                $status.text( response.data?.message || 'Geen competities gevonden.' ).addClass( 'scv-error' );
            }
        } )
        .fail( function () {
            $status.text( 'Verbindingsfout. Probeer opnieuw.' ).addClass( 'scv-error' );
        } )
        .always( function () {
            $btn.prop( 'disabled', false );
            $spinner.hide();
        } );
    } );

    function populateCompetitionSelect( competitions ) {
        const $wrap   = $( '#scv-comp-select-wrap' );
        const $select = $( '#scv_standing_pool_id' );
        const current = $select.val();

        $select.empty().append( '<option value="">' + '— Kies een competitie —' + '</option>' );
        competitions.forEach( function ( comp ) {
            $select.append( $( '<option>', { value: comp.id, text: comp.name } ) );
        } );

        if ( current ) $select.val( current );
        $wrap.show();

        $select.on( 'change', function () {
            const selected = competitions.find( c => c.id === $( this ).val() );
            if ( selected ) {
                $( '#scv_standing_pool_name' ).val( selected.name );
            }
        } );
    }

    // ── Verify Sportlink API client ───────────────────────────────────────────

    $( '#scv-verify-client' ).on( 'click', function () {
        const $btn     = $( this );
        const $spinner = $( '#scv-verify-spinner' );
        const $status  = $( '#scv-verify-status' );

        $btn.prop( 'disabled', true );
        $spinner.show();
        $status.text( 'Verifiëren…' ).removeClass( 'scv-error scv-success' );

        $.ajax( {
            url:    scvAdmin.ajaxUrl,
            method: 'POST',
            data:   {
                action:    'scv_verify_client',
                nonce:     scvAdmin.nonce,
                client_id: $( '#scv_client_id' ).val(),
            },
        } )
        .done( function ( response ) {
            if ( response.success ) {
                $( '#scv_sport_locatie_api' ).val( response.data.locatie );
                $( '#scv_sport_locatie' ).val( response.data.locatie );
                $status.text( '✓ Gevonden: ' + response.data.name ).addClass( 'scv-success' );
            } else {
                $status.text( response.data?.message || 'Verificatie mislukt.' ).addClass( 'scv-error' );
            }
        } )
        .fail( function () {
            $status.text( 'Verbindingsfout.' ).addClass( 'scv-error' );
        } )
        .always( function () {
            $btn.prop( 'disabled', false );
            $spinner.hide();
        } );
    } );

    // ── Background media picker ───────────────────────────────────────────────

    $( '#scv-select-bg' ).on( 'click', function ( e ) {
        e.preventDefault();
        const frame = wp.media( {
            title:    'Kies achtergrondafbeelding',
            button:   { text: 'Gebruik deze afbeelding' },
            multiple: false,
            library:  { type: 'image' },
        } );
        frame.on( 'select', function () {
            const url = frame.state().get( 'selection' ).first().toJSON().url;
            $( '#scv_background_url' ).val( url );
        } );
        frame.open();
    } );

    // ── Color pickers ─────────────────────────────────────────────────────────

    $( '.scv-color-picker' ).wpColorPicker( {
        change: function ( event, ui ) {
            const $input  = $( event.target );
            const col     = $input.data( 'preview-col' );
            const prop    = $input.data( 'preview-prop' );
            const color   = ui.color.toString();

            $( '#preview-' + col ).css( prop, color );
        },
        clear: function () {
            // handled by change
        },
    } );

    // ── Column width / visibility live preview ────────────────────────────────

    $( '.scv-layout-width' ).on( 'input change', function () {
        const col   = $( this ).data( 'preview-col' );
        const width = parseInt( $( this ).val(), 10 ) || 1;
        $( '#preview-' + col ).css( 'flex', width );
    } );

    $( '.scv-layout-visible' ).on( 'change', function () {
        const col = $( this ).data( 'preview-col' );
        $( '#preview-' + col ).toggle( $( this ).is( ':checked' ) );
    } );

    // ── Sponsors tab ──────────────────────────────────────────────────────────

    const MAX_SPONSORS = 13;

    function sponsorCount() {
        return $( '#scv-sponsors-list .scv-sponsor-item' ).length;
    }

    function updateSponsorCount() {
        const n = sponsorCount();
        $( '#scv-sponsor-count' ).text( n + ' van ' + MAX_SPONSORS + ' sponsorplaatsen in gebruik.' );
        if ( n >= MAX_SPONSORS ) {
            $( '#scv-add-sponsor-media, #scv-add-sponsor-url, #scv-sponsor-url-input' ).prop( 'disabled', true );
        } else {
            $( '#scv-add-sponsor-media, #scv-add-sponsor-url, #scv-sponsor-url-input' ).prop( 'disabled', false );
        }
    }

    $( '#scv-sponsors-list' ).on( 'click', '.scv-remove-sponsor', function () {
        $( this ).closest( '.scv-sponsor-item' ).remove();
        updateSponsorCount();
    } );

    function addSponsor( url ) {
        if ( ! url || sponsorCount() >= MAX_SPONSORS ) return;
        const index = sponsorCount();
        const html = `
            <div class="scv-sponsor-item" data-index="${index}">
                <img src="${url}" alt="">
                <input type="hidden" name="scv_sponsors[]" value="${url}">
                <button type="button" class="scv-remove-sponsor button button-small button-link-delete">
                    Verwijderen
                </button>
            </div>`;
        $( '#scv-sponsors-list' ).append( html );
        updateSponsorCount();
    }

    $( '#scv-add-sponsor-media' ).on( 'click', function ( e ) {
        e.preventDefault();
        const frame = wp.media( {
            title:    'Kies sponsorafbeelding',
            button:   { text: 'Sponsor toevoegen' },
            multiple: true,
            library:  { type: 'image' },
        } );
        frame.on( 'select', function () {
            frame.state().get( 'selection' ).each( function ( attachment ) {
                addSponsor( attachment.toJSON().url );
            } );
        } );
        frame.open();
    } );

    $( '#scv-add-sponsor-url' ).on( 'click', function () {
        const url = $( '#scv-sponsor-url-input' ).val().trim();
        if ( url ) {
            addSponsor( url );
            $( '#scv-sponsor-url-input' ).val( '' );
        }
    } );

    updateSponsorCount();

    // ── Shortcode copy buttons ────────────────────────────────────────────────

    $( document ).on( 'click', '.scv-copy-shortcode', function () {
        const code = $( this ).data( 'shortcode' );
        const $btn = $( this );
        const doCopy = function () {
            $btn.text( 'Gekopieerd!' ).prop( 'disabled', true );
            setTimeout( function () {
                $btn.text( 'Kopieer' ).prop( 'disabled', false );
            }, 1500 );
        };
        if ( navigator.clipboard ) {
            navigator.clipboard.writeText( code ).then( doCopy ).catch( function () {
                const el = document.createElement( 'textarea' );
                el.value = code;
                document.body.appendChild( el );
                el.select();
                document.execCommand( 'copy' );
                document.body.removeChild( el );
                doCopy();
            } );
        } else {
            const el = document.createElement( 'textarea' );
            el.value = code;
            document.body.appendChild( el );
            el.select();
            document.execCommand( 'copy' );
            document.body.removeChild( el );
            doCopy();
        }
    } );

    // ── Test connection ───────────────────────────────────────────────────────

    if ( typeof scvAdmin.connectionStatus !== 'undefined' ) {
        $( '#scv-status-dot' ).attr( 'data-status', scvAdmin.connectionStatus );
    }

    $( '#scv-test-connection' ).on( 'click', function () {
        const $btn     = $( this );
        const $spinner = $( '#scv-test-spinner' );
        const $status  = $( '#scv-test-status' );
        const $dot     = $( '#scv-status-dot' );

        $btn.prop( 'disabled', true );
        $spinner.show();
        $status.text( 'Testen…' ).removeClass( 'scv-error scv-success' );

        $.ajax( {
            url:    scvAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action:          'scv_test_connection',
                nonce:           scvAdmin.nonce,
                connection_type: $( '#scv_connection_type' ).val(),
                client_id:       $( '#scv_client_id' ).val(),
                game_type_label: $( '#scv_game_type_label' ).val(),
                username:        $( '#scv_username' ).val(),
            },
        } )
        .done( function ( response ) {
            if ( response.success ) {
                $status.text( '✓ ' + response.data.message ).addClass( 'scv-success' );
                $dot.attr( 'data-status', 'ok' );
            } else {
                $status.text( '✗ ' + ( response.data?.message || 'Verbinding mislukt.' ) ).addClass( 'scv-error' );
                $dot.attr( 'data-status', 'error' );
            }
        } )
        .fail( function () {
            $status.text( 'Verbindingsfout. Probeer opnieuw.' ).addClass( 'scv-error' );
            $dot.attr( 'data-status', 'error' );
        } )
        .always( function () {
            $btn.prop( 'disabled', false );
            $spinner.hide();
        } );
    } );

    // ── Clear connection fields (general tab) ────────────────────────────────

    $( '#scv-clear-connection' ).on( 'click', function () {
        $( '#scv_client_id' ).val( '' );
        $( '#scv_username' ).val( '' );
        $( '#scv_password' ).val( '' );
        $( '#scv_club_identifier' ).val( '' );
        $( '#scv_club_id' ).val( '' );
        $( '#scv-club-select-wrap' ).hide();
        $( '#scv-test-status, #scv-verify-status, #scv-fetch-status' )
            .text( '' ).removeClass( 'scv-error scv-success' );
        $( '#scv-status-dot' ).attr( 'data-status', 'unknown' );
        updateStandingsVisibility();
    } );

    // ── Reset to defaults (style tab) ────────────────────────────────────────

    $( '#scv-reset-defaults' ).on( 'click', function () {
        if ( ! confirm( 'Stijl terugzetten naar standaardwaarden? Niet-opgeslagen wijzigingen gaan verloren.' ) ) return;

        const colorDefs = {
            'scv_colors[leftBoxColor]':     '#b40808',
            'scv_colors[leftBoxText]':      '#ffffff',
            'scv_colors[leftMidBoxColor]':  '#000000',
            'scv_colors[leftMidBoxText]':   '#ffffff',
            'scv_colors[midBoxColor]':      '#de0b0b',
            'scv_colors[midBoxText]':       '#ffffff',
            'scv_colors[rightMidBoxColor]': '#000000',
            'scv_colors[rightMidBoxText]':  '#ffffff',
            'scv_colors[rightBoxColor]':    '#b40808',
            'scv_colors[rightBoxText]':     '#ffffff',
            'scv_own_team_colors[bg]':      '#1a5c1a',
            'scv_own_team_colors[text]':    '#ffffff',
        };
        Object.entries( colorDefs ).forEach( function ( [ name, val ] ) {
            $( '[name="' + name + '"]' ).wpColorPicker( 'color', val );
        } );

        const widthDefs = { leftWidth: 2, leftMidWidth: 9, midWidth: 4, rightMidWidth: 9, rightWidth: 3 };
        Object.entries( widthDefs ).forEach( function ( [ key, val ] ) {
            $( '[name="scv_layout[' + key + ']"]' ).val( val ).trigger( 'input' );
        } );

        [ 'leftVisible', 'leftMidVisible', 'midVisible', 'rightMidVisible', 'rightVisible', 'showLogos' ].forEach( function ( key ) {
            const $el = $( '[name="scv_layout[' + key + ']"]' );
            if ( ! $el.is( ':checked' ) ) $el.prop( 'checked', true ).trigger( 'change' );
        } );

        [ 'totalMatches', 'won', 'draw', 'lost', 'goalsFor', 'goalsAgainst', 'goalsDiff', 'points' ].forEach( function ( key ) {
            $( '[name="scv_standing_columns[' + key + ']"]' ).prop( 'checked', true );
        } );
    } );

    // ── Unsaved changes warning ───────────────────────────────────────────────

    let scvFormDirty = false;

    $( '#scv-general-form, #scv-style-form, #scv-sponsors-form' ).on( 'change input', function () {
        scvFormDirty = true;
    } );

    $( '#scv-general-form, #scv-style-form, #scv-sponsors-form' ).on( 'submit', function () {
        scvFormDirty = false;
    } );

    window.addEventListener( 'beforeunload', function ( e ) {
        if ( scvFormDirty ) {
            e.preventDefault();
            e.returnValue = '';
        }
    } );

} )( jQuery );

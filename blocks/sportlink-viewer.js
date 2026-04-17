/* global wp */
( function () {
    var blocks            = wp.blocks;
    var el                = wp.element.createElement;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody         = wp.components.PanelBody;
    var SelectControl     = wp.components.SelectControl;

    blocks.registerBlockType( 'sportlink-club-viewer/display', {
        title:       'Sportlink Club Viewer',
        icon:        'calendar',
        category:    'widgets',
        description: 'Toon wedstrijdprogramma, uitslagen of stand van je team.',
        attributes: {
            displayType: {
                type:    'string',
                default: 'match',
            },
        },

        edit: function ( props ) {
            var displayType   = props.attributes.displayType;
            var setAttributes = props.setAttributes;

            var labels = {
                match:     'Wedstrijdprogramma / Uitslagen',
                prematch:  'Voorwedstrijdinformatie',
                standings: 'Team standen',
            };

            return [
                el( InspectorControls, { key: 'inspector' },
                    el( PanelBody, { title: 'Weergave-instelling', initialOpen: true },
                        el( SelectControl, {
                            label:   'Scherm',
                            value:   displayType,
                            options: [
                                { label: 'Wedstrijdprogramma / Uitslagen', value: 'match'     },
                                { label: 'Voorwedstrijdinformatie',         value: 'prematch'  },
                                { label: 'Team standen',                   value: 'standings' },
                            ],
                            onChange: function ( val ) {
                                setAttributes( { displayType: val } );
                            },
                        } )
                    )
                ),
                el(
                    'div',
                    {
                        key:       'preview',
                        className: 'scv-block-preview',
                        style: {
                            padding:      '16px',
                            background:   '#f6f7f7',
                            border:       '1px solid #dcdcde',
                            borderRadius: '4px',
                        },
                    },
                    el( 'strong', null, '\u26BD Sportlink Club Viewer' ),
                    el( 'p', { style: { margin: '4px 0 0', color: '#646970' } }, labels[ displayType ] || displayType )
                ),
            ];
        },

        save: function () {
            return null;
        },
    } );
} )();

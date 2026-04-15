import 'core-js/stable';
import 'regenerator-runtime/runtime';
import 'whatwg-fetch';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { applyPersistentBackground } from '@/utils/background';
import { initConfigFromWP } from '@/config';
import App from './App.vue';
import './style.css';
import { createAppRouter } from './router';
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';

const toastOptions = {
  transition: 'Vue-Toastification__bounce',
  maxToasts: 5,
  position: 'bottom-center',
  newestOnTop: true
};

const mountTargets = [
  { id: 'scv-match-display',    appType: 'match-display'    },
  { id: 'scv-prematch-display', appType: 'prematch-display' },
];

// Read config from the <script type="application/json" id="scv-config-..."> element
// that the shortcode embeds directly in the page HTML.  This is the same pattern
// WordPress uses for block-editor and emoji data — it is always present regardless
// of script-loading hook order.
//
// Fall back to window.scvConfig in case wp_localize_script happened to work.
( function initConfig() {
  for ( const { id } of mountTargets ) {
    const configId = id.replace( 'scv-', 'scv-config-' ); // scv-match-display → scv-config-match-display
    const cfgEl    = document.getElementById( configId );

    if ( cfgEl ) {
      try {
        const wpCfg = JSON.parse( cfgEl.textContent );
        // Expose on window so stores that read window.scvConfig directly (e.g. sponsorStore)
        // continue to work.
        window.scvConfig = wpCfg;
        initConfigFromWP( wpCfg );

        if ( wpCfg.debug ) {
          console.group( '[Sportlink Viewer] startup — config van JSON-element' );
          console.log( 'element-id:', configId );
          console.log( 'config:', wpCfg );
          console.groupEnd();
        }
        return; // both shortcodes share the same WP options; one pass is enough
      } catch ( e ) {
        console.warn( '[Sportlink Viewer] Config parse error in #' + configId + ':', e );
      }
    }
  }

  // If no JSON element found, try window.scvConfig (set by wp_localize_script if it worked)
  if ( window.scvConfig ) {
    initConfigFromWP( window.scvConfig );
    if ( window.scvConfig.debug ) {
      console.group( '[Sportlink Viewer] startup — config van window.scvConfig' );
      console.log( 'config:', window.scvConfig );
      console.groupEnd();
    }
  } else {
    console.warn( '[Sportlink Viewer] Geen config gevonden — plugin niet geconfigureerd?' );
  }
} )();

// Shared Pinia instance — both displays use the same club config
const pinia = createPinia();
let backgroundApplied = false;

for ( const { id, appType } of mountTargets ) {
  const el = document.getElementById( id );
  if ( ! el ) continue;

  if ( ! backgroundApplied ) {
    applyPersistentBackground();
    backgroundApplied = true;
  }

  const app = createApp( App );
  app.use( pinia );
  app.use( createAppRouter( appType ) );
  app.use( Toast, toastOptions );
  app.mount( el );
}

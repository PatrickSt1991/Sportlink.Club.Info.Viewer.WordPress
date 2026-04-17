import 'core-js/stable';
import 'regenerator-runtime/runtime';
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

function initialize() {
  // Read config from <script type="application/json" id="scv-config-..."> embedded by shortcode.
  // Falls back to window.scvConfig (wp_localize_script) if no JSON element is found.
  ( function initConfig() {
    for ( const { id } of mountTargets ) {
      const configId = id.replace( 'scv-', 'scv-config-' );
      const cfgEl    = document.getElementById( configId );

      if ( cfgEl ) {
        try {
          const wpCfg = JSON.parse( cfgEl.textContent );
          window.scvConfig = wpCfg;
          initConfigFromWP( wpCfg );

          if ( wpCfg.debug ) {
            console.group( '[Sportlink Viewer] startup — config van JSON-element' );
            console.log( 'element-id:', configId );
            console.log( 'config:', wpCfg );
            console.groupEnd();
          }
          return;
        } catch ( e ) {
          console.warn( '[Sportlink Viewer] Config parse error in #' + configId + ':', e );
        }
      }
    }

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
}

if ( document.readyState === 'loading' ) {
  document.addEventListener( 'DOMContentLoaded', initialize );
} else {
  initialize();
}

import { fetchWithConfig } from './fetchUtils';
import { useSportlinkAuth } from '@/composables/useSportlinkAuth';
import { APP_CREDENTIALS } from '@/config';

const CORS_PROXY = 'https://cors-proxy.clubinfoproxy.workers.dev/proxy?url=';

function proxied(url) {
  return CORS_PROXY + encodeURIComponent(url);
}

async function reAuth(config, auth, appCreds) {
  if (config.fakeCredentials) return auth.useFakeCredentials(appCreds);
  if (config.username && config.password) return auth.login(config.username, config.password, appCreds);
  return false;
}

async function entityGet(url, appCreds, config, auth) {
  let response;
  try {
    response = await fetchWithConfig(proxied(url), true, appCreds);
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
  } catch (err) {
    if (err.message === 'No valid token available') {
      if (!await reAuth(config, auth, appCreds)) throw new Error('Inloggen mislukt. Controleer de inloggegevens.');
      response = await fetchWithConfig(proxied(url), true, appCreds);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
    } else {
      throw err;
    }
  }
  return response.json();
}

export async function fetchStandings(config) {
  if (!config.standingPoolId) throw new Error('Geen competitie geconfigureerd voor standen.');

  const appCreds = APP_CREDENTIALS.find(
    c => c.type.toLowerCase() === (config.gameType?.label || '').toLowerCase()
  );
  if (!appCreds) throw new Error('Geen API-gegevens gevonden voor dit sporttype.');

  const auth = useSportlinkAuth();
  const base = `https://app-${appCreds.apiUrl}-production.sportlink.com`;

  const poolData = await entityGet(
    `${base}/entity/common/memberportal/app/pool/PoolCompetitionData?v=2&PoolId=${config.standingPoolId}&GetTopscorers=false`,
    appCreds, config, auth
  );

  if (window.scvConfig?.debug) {
    console.group('SCV debug — standings: PoolCompetitionData');
    console.log(poolData);
    console.groupEnd();
  }

  return (poolData.PoolStanding?.PoolStandingTeam || [])
    .slice()
    .sort((a, b) => (a.Position || 0) - (b.Position || 0));
}

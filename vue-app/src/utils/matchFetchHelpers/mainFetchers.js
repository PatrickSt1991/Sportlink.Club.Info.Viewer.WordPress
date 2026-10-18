import { debugLog, getJson } from '@/api/wpClient';
import { processSportlinkApiData } from './processors/sportlinkApi';
import { processSportlinkProxyData } from './processors/sportlinkProxy';
import { processNevoboProxyData } from './processors/nevoboProxy';
import {
  processPreMatchSportlinkApiData,
  processPreMatchSportlinkProxyData,
  processPreMatchNevoboProxyData,
} from './processors/prematchProcessors';
import { formatTime } from '@/utils/formatDateType.js';

export function getDateThreshold(fetchType, config, now = new Date()) {
  const threshold = new Date(now);
  threshold.setDate(now.getDate() + (fetchType === 'info' ? config.programmaDagen : -config.uitslagDagen));
  return { now, dateThreshold: threshold };
}

export function getPreMatchWindow(now = new Date()) {
  const from = new Date(now);
  from.setHours(from.getHours() - 3);
  const to = new Date(from.getTime() + 6 * 60 * 60 * 1000);
  return { from, to };
}

/** Programma ('info') or uitslagen ('results'). Returns processed rows; throws on failure. */
export async function fetchMatches(fetchType, config) {
  const data = await getJson(fetchType === 'info' ? 'program' : 'results');
  debugLog(`fetchMatches (${fetchType})`, data);

  const { now, dateThreshold } = getDateThreshold(fetchType, config);
  switch (config.connectionType) {
    case 'Sportlink API':
      return processSportlinkApiData(data, fetchType, dateThreshold, now);
    case 'Sportlink Proxy':
      return processSportlinkProxyData(data, fetchType, dateThreshold, now, null);
    case 'Nevobo Proxy':
      return processNevoboProxyData(data, fetchType, dateThreshold, now);
    default:
      return [];
  }
}

/** Kleedkamer/veld info for home matches around now. Returns `{ rows, from, to }`. */
export async function fetchPreMatchInfo(config) {
  const data = await getJson('prematch');
  debugLog('fetchPreMatchInfo', data);

  const { from, to } = getPreMatchWindow();
  let rows = [];
  switch (config.connectionType) {
    case 'Sportlink API':
      rows = processPreMatchSportlinkApiData(data, from, to, config);
      break;
    case 'Sportlink Proxy':
      rows = await processPreMatchSportlinkProxyData(data, from, to, config);
      break;
    case 'Nevobo Proxy':
      rows = processPreMatchNevoboProxyData(data, from, to, config);
      break;
  }

  return { rows, from: formatTime(from), to: formatTime(to) };
}

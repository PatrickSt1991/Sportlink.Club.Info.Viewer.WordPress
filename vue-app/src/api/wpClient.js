/**
 * The front-end only talks to the plugin's own REST API (wp-json/scv/v1).
 * WordPress fetches and caches the upstream Sportlink/Nevobo data server-side,
 * so no credentials, tokens or API secrets exist in the browser.
 */
import { USER_CONFIG } from '@/config';

export class HttpError extends Error {
  constructor(status, message, path) {
    super(message || `HTTP ${status}`);
    this.name = 'HttpError';
    this.status = status;
    this.path = path;
  }
}

export function restBase() {
  const base =
    USER_CONFIG.value?.restUrl ||
    (typeof window !== 'undefined' && window.scvConfig?.restUrl) ||
    '/wp-json/scv/v1/';
  return base.endsWith('/') ? base : `${base}/`;
}

export const restUrl = (path) => restBase() + String(path).replace(/^\/+/, '');

export async function getJson(path) {
  const response = await fetch(restUrl(path), { headers: { Accept: 'application/json' } });

  if (!response.ok) {
    let message = '';
    try {
      const body = await response.json();
      message = body?.message || '';
    } catch {
      /* body was not JSON */
    }
    throw new HttpError(response.status, message, path);
  }

  return response.json();
}

/** Team logo: the endpoint redirects to the cached file (or a placeholder). */
export const logoUrl = (bucket, hash) => restUrl(`logo/${encodeURIComponent(bucket)}/${encodeURIComponent(hash)}`);

/** Dressing rooms + field for one match (Sportlink Proxy only). */
export const fetchFacility = (publicMatchId) => getJson(`facility/${encodeURIComponent(publicMatchId)}`);

export function debugLog(label, data) {
  if (!USER_CONFIG.value?.debug) return;
  console.group(`SCV debug — ${label}`);
  console.log(data);
  console.groupEnd();
}

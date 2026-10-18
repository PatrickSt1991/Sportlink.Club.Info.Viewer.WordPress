import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { USER_CONFIG, buildConfigFromWP } from '@/config';
import { HttpError, getJson, logoUrl, restUrl } from '@/api/wpClient';

beforeEach(() => {
  USER_CONFIG.value = buildConfigFromWP({ restUrl: 'https://club.example/wp-json/scv/v1/' });
  globalThis.fetch = vi.fn();
});

afterEach(() => vi.restoreAllMocks());

describe('wpClient', () => {
  it('builds urls on the REST base from the embedded config', () => {
    expect(restUrl('program')).toBe('https://club.example/wp-json/scv/v1/program');
    expect(logoUrl('b', 'h/1')).toBe('https://club.example/wp-json/scv/v1/logo/b/h%2F1');
  });

  it('works with plain permalinks (rest_route query style)', () => {
    USER_CONFIG.value = buildConfigFromWP({ restUrl: 'https://club.example/?rest_route=/scv/v1/' });
    expect(restUrl('results')).toBe('https://club.example/?rest_route=/scv/v1/results');
  });

  it('returns parsed JSON on success', async () => {
    fetch.mockResolvedValue({ ok: true, status: 200, json: () => Promise.resolve([{ a: 1 }]) });
    expect(await getJson('program')).toEqual([{ a: 1 }]);
  });

  it('throws an HttpError carrying the WordPress error message', async () => {
    fetch.mockResolvedValue({ ok: false, status: 409, json: () => Promise.resolve({ code: 'scv_not_configured', message: 'De plugin is nog niet geconfigureerd.' }) });
    await expect(getJson('program')).rejects.toMatchObject({ name: 'HttpError', status: 409, message: 'De plugin is nog niet geconfigureerd.' });
  });

  it('falls back to a generic message when the body is not JSON', async () => {
    fetch.mockResolvedValue({ ok: false, status: 500, json: () => Promise.reject(new Error('nope')) });
    await expect(getJson('program')).rejects.toBeInstanceOf(HttpError);
  });
});

describe('buildConfigFromWP', () => {
  it('never carries credentials and resolves the game type', () => {
    const cfg = buildConfigFromWP({ gameTypeLabel: 'voetbal', connectionType: 'Sportlink Proxy', clubId: 'C1', username: 'x', password: 'y' });
    expect(cfg.gameType?.label).toBe('Voetbal');
    expect(cfg).not.toHaveProperty('username');
    expect(cfg).not.toHaveProperty('password');
    expect(cfg.columnVisible.left).toBe(true);
  });
});

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { USER_CONFIG, buildConfigFromWP } from '@/config';
import { fetchMatches, fetchPreMatchInfo, fetchStandings } from '@/utils/matchFetchHelpers';
import program from './fixtures/sportlinkApiProgram.json';

const json = (body, status = 200) => Promise.resolve({ ok: status < 400, status, json: () => Promise.resolve(body) });

beforeEach(() => {
  vi.useFakeTimers();
  vi.setSystemTime(new Date('2026-10-18T13:00:00+02:00'));
  USER_CONFIG.value = buildConfigFromWP({ restUrl: '/wp-json/scv/v1/', gameTypeLabel: 'Voetbal', connectionType: 'Sportlink API', clientId: 'abc', sportLocatie: 'Sportpark Noord', standingPoolId: 'P1' });
  globalThis.fetch = vi.fn((url) => {
    const u = String(url);
    if (u.endsWith('/program')) return json(program);
    if (u.endsWith('/results')) return json(program);
    if (u.endsWith('/prematch')) return json(program);
    if (u.endsWith('/standings/P1')) return json([{ positie: 2, teamnaam: 'B', punten: 3 }, { positie: 1, teamnaam: 'A', punten: 6, eigenteam: 'true' }]);
    return json({ message: 'not found' }, 404);
  });
});

afterEach(() => {
  vi.useRealTimers();
  vi.restoreAllMocks();
});

describe('fetchers against the plugin REST API', () => {
  it('fetches the program from /program and processes it', async () => {
    const rows = await fetchMatches('info', USER_CONFIG.value);
    expect(fetch).toHaveBeenCalledWith('/wp-json/scv/v1/program', expect.anything());
    expect(rows.map((r) => r.id)).toEqual(['A1', 'A2', 'A5']);
  });

  it('fetches pre-match info from /prematch', async () => {
    const result = await fetchPreMatchInfo(USER_CONFIG.value);
    expect(fetch).toHaveBeenCalledWith('/wp-json/scv/v1/prematch', expect.anything());
    expect(result.rows).toHaveLength(1);
    expect(result.from).toMatch(/\d{2}:\d{2}/);
  });

  it('fetches and sorts standings from /standings/{pool}', async () => {
    const rows = await fetchStandings(USER_CONFIG.value);
    expect(rows.map((r) => r.TeamName)).toEqual(['A', 'B']);
    expect(rows[0].LocalTeam).toBe(true);
  });

  it('refuses standings for Nevobo', async () => {
    await expect(fetchStandings({ ...USER_CONFIG.value, connectionType: 'Nevobo Proxy' })).rejects.toThrow(/alleen beschikbaar/);
  });
});

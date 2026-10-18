import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/api/wpClient', async (importOriginal) => {
  const actual = await importOriginal();
  return { ...actual, fetchFacility: vi.fn() };
});

import { fetchFacility } from '@/api/wpClient';
import { processSportlinkProxyData } from '@/utils/matchFetchHelpers/processors/sportlinkProxy';
import { processPreMatchSportlinkProxyData } from '@/utils/matchFetchHelpers/processors/prematchProcessors';
import { getDateThreshold, getPreMatchWindow } from '@/utils/matchFetchHelpers/mainFetchers';
import program from '../fixtures/sportlinkProxyProgram.json';
import results from '../fixtures/sportlinkProxyResults.json';

const NOW = new Date('2026-10-18T13:00:00+02:00');

beforeEach(() => vi.clearAllMocks());

describe('processSportlinkProxyData', () => {
  it('maps program items with logo urls served by the plugin REST API', async () => {
    const { now, dateThreshold } = getDateThreshold('info', { programmaDagen: 7 }, NOW);
    const rows = await processSportlinkProxyData(program, 'info', dateThreshold, now, null);

    expect(rows.map((r) => r.id)).toEqual(['PM-1', 'PM-2', 'PM-3']);
    expect(rows[0].thuisteamlogo).toBe('/wp-json/scv/v1/logo/b/h1');
    expect(rows[1].thuisteamlogo).toBeNull();
  });

  it('formats results with a score or a placeholder', async () => {
    const { now, dateThreshold } = getDateThreshold('results', { uitslagDagen: 7 }, NOW);
    const rows = await processSportlinkProxyData(results, 'results', dateThreshold, now, null);

    expect(rows[0].uitslag).toBe('3-1');
    expect(rows[1].uitslag).toBe('--');
    expect(rows[1].competitiesoort).toBe('Nacompetitie');
  });
});

describe('processPreMatchSportlinkProxyData', () => {
  it('loads facility details through the plugin REST API', async () => {
    fetchFacility.mockResolvedValue({ HomeDressingRoom: 'K1', AwayDressingRoom: 'K2', SubFacilityName: 'veld 3' });
    const { from, to } = getPreMatchWindow(NOW);

    const rows = await processPreMatchSportlinkProxyData(program, from, to, { clubId: 'CLUB-1' });

    expect(rows).toHaveLength(1);
    expect(rows[0]).toMatchObject({ id: 'PM-2', kleedkamerthuisteam: 'K1', kleedkameruitteam: 'K2', veld: 'Veld 3' });
    expect(fetchFacility).toHaveBeenCalledWith('PM-2');
  });

  it('still returns the match when facility details fail', async () => {
    fetchFacility.mockRejectedValue(new Error('boom'));
    const { from, to } = getPreMatchWindow(NOW);

    const rows = await processPreMatchSportlinkProxyData(program, from, to, { clubId: 'CLUB-1' });
    expect(rows[0]).toMatchObject({ kleedkamerthuisteam: '---', veld: '-' });
  });
});

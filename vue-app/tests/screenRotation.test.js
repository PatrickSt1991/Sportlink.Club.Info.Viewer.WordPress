import { describe, expect, it } from 'vitest';
import { nextScreen } from '@/utils/screenRotation';

describe('screen rotation (WordPress)', () => {
  it('toggles between programma and uitslagen when enabled', () => {
    expect(nextScreen('/match-info', { enableScreenSwitch: true })).toBe('/match-results');
    expect(nextScreen('/match-results', { enableScreenSwitch: true })).toBe('/match-info');
  });

  it('never switches away from standings or pre-match screens', () => {
    expect(nextScreen('/standing-list', { enableScreenSwitch: true })).toBeNull();
    expect(nextScreen('/prematch-info', { enableScreenSwitch: true })).toBeNull();
  });

  it('returns null when switching is disabled', () => {
    expect(nextScreen('/match-info', { enableScreenSwitch: false })).toBeNull();
  });
});

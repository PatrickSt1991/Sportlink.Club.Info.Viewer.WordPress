/**
 * In WordPress the programma/uitslagen screen rotates between the two; the
 * standings and pre-match screens are separate shortcodes and never switch.
 */
export const ROTATION = ['/match-info', '/match-results'];

export function nextScreen(currentPath, config) {
  if (!config?.enableScreenSwitch) return null;
  const index = ROTATION.indexOf(currentPath);
  if (index === -1) return null;
  return ROTATION[(index + 1) % ROTATION.length];
}

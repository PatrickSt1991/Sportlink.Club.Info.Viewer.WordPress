export function applyPersistentBackground() {
  const backgroundUrl =
    typeof window !== 'undefined' && window.scvConfig?.selectedBackground
      ? window.scvConfig.selectedBackground
      : '';

  const html = document.documentElement;

  if (backgroundUrl) {
    html.style.background = `linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url(${backgroundUrl}) no-repeat center center`;
  } else {
    // Default gradient — no image required
    html.style.background = 'linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%)';
  }

  html.style.backgroundSize = 'cover';
  html.style.minHeight      = '100vh';
}

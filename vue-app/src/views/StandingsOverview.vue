<template>
  <main role="main" class="container-fluid scv-overview-main" :class="{ 'scv-overview-full-width': fullWidth }">
    <div v-if="!entries.length" class="scv-overview-empty">
      <h1>Geen elftallen geconfigureerd</h1>
      <p>Voeg in de WordPress-instellingen één of meer elftallen toe onder “Standen overzicht”.</p>
    </div>
    <div v-else class="scv-overview-grid" :style="gridStyle">
      <StandingsTable
        v-for="entry in entries"
        :key="entry.teamId + ':' + entry.poolId"
        :entry="entry"
      />
    </div>
  </main>
</template>

<script setup>
import { computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { USER_CONFIG } from '@/config';
import StandingsTable from '@/components/StandingsTable.vue';

const router = useRouter();

const entries = computed(() => {
  const list = USER_CONFIG.value?.standingsList;
  return Array.isArray(list) ? list.filter(e => e?.teamId && e?.poolId) : [];
});

const fullWidth = computed(() => USER_CONFIG.value?.overviewFullWidth !== false);

const gridStyle = computed(() => {
  const setting = USER_CONFIG.value?.overviewColumns || 'auto';
  if (setting === 'auto') {
    return { gridTemplateColumns: 'repeat(auto-fit, minmax(420px, 1fr))' };
  }
  const n = parseInt(setting, 10);
  if (Number.isFinite(n) && n >= 1 && n <= 6) {
    return { gridTemplateColumns: `repeat(${n}, minmax(0, 1fr))` };
  }
  return { gridTemplateColumns: 'repeat(auto-fit, minmax(420px, 1fr))' };
});

watch(
  () => USER_CONFIG.value,
  (cfg) => {
    if (!cfg) return;
    const hasIdentifier = cfg.clientId || cfg.clubIdentifer || cfg.clubId;
    if (!hasIdentifier) router.push('/no-config');
  },
  { immediate: true, deep: true }
);

// ── Full-page-width mode ───────────────────────────────────────────────────
//
// WP themes wrap post content in a container with a max-width AND often set
// overflow-x:hidden somewhere up the tree, which clips any CSS break-out
// trick. To reliably escape that, we physically move the mount-target div
// to be a direct child of <body>, leaving a placeholder behind so the
// surrounding page flow stays correct.

let placeholder = null;
let resizeObserver = null;
let originalAnchor = null;   // { parent, nextSibling } to restore on unmount

function teleportToBody() {
  if (typeof document === 'undefined') return;
  const wrap = document.getElementById('scv-standings-overview');
  if (!wrap || wrap.parentElement === document.body) return;

  originalAnchor = { parent: wrap.parentElement, nextSibling: wrap.nextSibling };

  placeholder = document.createElement('div');
  placeholder.className = 'scv-overview-placeholder';
  placeholder.style.cssText = 'margin:0;padding:0;';

  wrap.parentElement.insertBefore(placeholder, wrap);
  document.body.appendChild(wrap);

  Object.assign(wrap.style, {
    position: 'absolute',
    left: '0',
    right: '0',
    width: '100%',
    boxSizing: 'border-box',
  });

  const reposition = () => {
    if (!placeholder || !wrap) return;
    const rect = placeholder.getBoundingClientRect();
    wrap.style.top = `${rect.top + window.scrollY}px`;
    placeholder.style.height = `${wrap.offsetHeight}px`;
  };

  reposition();
  window.addEventListener('resize', reposition);
  window.addEventListener('scroll', reposition, { passive: true });

  if (typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(reposition);
    resizeObserver.observe(wrap);
  }

  // expose for cleanup
  wrap.__scvReposition = reposition;
}

function restoreFromBody() {
  if (typeof document === 'undefined') return;
  const wrap = document.getElementById('scv-standings-overview');
  if (!wrap || !originalAnchor) return;

  if (wrap.__scvReposition) {
    window.removeEventListener('resize', wrap.__scvReposition);
    window.removeEventListener('scroll', wrap.__scvReposition);
    delete wrap.__scvReposition;
  }
  if (resizeObserver) { resizeObserver.disconnect(); resizeObserver = null; }

  Object.assign(wrap.style, { position: '', top: '', left: '', right: '', width: '', boxSizing: '' });

  if (placeholder && placeholder.parentElement) {
    placeholder.parentElement.insertBefore(wrap, placeholder);
    placeholder.parentElement.removeChild(placeholder);
  }
  placeholder = null;
  originalAnchor = null;
}

onMounted(() => {
  if (fullWidth.value) {
    // Defer so Vue has a chance to render content before we measure heights.
    requestAnimationFrame(() => requestAnimationFrame(teleportToBody));
  }
});

onBeforeUnmount(() => {
  restoreFromBody();
});
</script>

<style scoped>
.scv-overview-main {
  width: 100%;
  padding: 12px clamp(12px, 3vw, 32px);
  box-sizing: border-box;
}
.scv-overview-grid {
  display: grid;
  gap: 16px;
  align-items: start;
}
.scv-overview-empty {
  text-align: center;
  color: #fff;
  padding: 48px 16px;
}
.scv-overview-empty h1 {
  margin-bottom: 8px;
}
</style>

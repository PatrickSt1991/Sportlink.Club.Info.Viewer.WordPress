<template>
  <div class="scv-standings-box">
    <div v-if="entry.teamName || entry.poolName" class="scv-standings-box-title">
      <span class="scv-standings-box-team">{{ entry.teamName }}</span>
      <span v-if="entry.poolName" class="scv-standings-box-pool">{{ entry.poolName }}</span>
    </div>

    <div v-if="loading" class="scv-standings-msg">
      <h2>Stand wordt geladen…</h2>
    </div>
    <div v-else-if="error" class="scv-standings-msg scv-standings-error">
      <h2>{{ error }}</h2>
    </div>
    <template v-else-if="teams.length">
      <div class="matchEntry match-header">
        <div :style="posStyle">#</div>
        <div :style="nameStyle">Team</div>
        <div v-if="cols.totalMatches" :style="statStyle">M</div>
        <div v-if="cols.won"          :style="statStyle">W</div>
        <div v-if="cols.draw"         :style="statStyle">G</div>
        <div v-if="cols.lost"         :style="statStyle">V</div>
        <div v-if="cols.goalsFor"     class="scv-goal-for" :style="goalStyle">+</div>
        <div v-if="cols.goalsAgainst" class="scv-goal-against" :style="goalStyle">-</div>
        <div v-if="cols.goalsDiff"    :style="goalStyle">+/-</div>
        <div v-if="cols.points"       :style="ptsStyle">Pts</div>
      </div>

      <div ref="scrollEl" :style="{ height: containerHeight, overflowY: 'hidden' }">
        <div
          v-for="team in teams"
          :key="team.PublicTeamId || team.Position"
          class="matchEntry"
        >
          <div :style="rowStyle(posStyle, team)">{{ team.Position }}</div>
          <div :style="rowStyle(nameStyle, team)">{{ team.TeamName }}</div>
          <div v-if="cols.totalMatches" :style="rowStyle(statStyle, team)">{{ team.TotalMatches }}</div>
          <div v-if="cols.won"          :style="rowStyle(statStyle, team)">{{ team.Won }}</div>
          <div v-if="cols.draw"         :style="rowStyle(statStyle, team)">{{ team.Draw }}</div>
          <div v-if="cols.lost"         :style="rowStyle(statStyle, team)">{{ team.Lost }}</div>
          <div v-if="cols.goalsFor"     class="scv-goal-for" :style="rowStyle(goalStyle, team)">{{ team.GoalsFor }}</div>
          <div v-if="cols.goalsAgainst" class="scv-goal-against" :style="rowStyle(goalStyle, team)">{{ team.GoalsAgainst }}</div>
          <div v-if="cols.goalsDiff"    :style="rowStyle(goalStyle, team)">{{ fmtDiff(team.GoalsDifference) }}</div>
          <div v-if="cols.points"       :style="rowStyle(ptsStyle, team)">{{ team.TotalPoints }}</div>
        </div>
      </div>
    </template>
    <NoMatchesDisplay
      v-else
      title="Geen standen beschikbaar"
      message="Er zijn momenteel geen standen voor dit team." />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { USER_CONFIG } from '@/config';
import { fetchStandings } from '@/utils/matchFetchHelpers/standingsFetcher';
import NoMatchesDisplay from '@/components/NoMatchesDisplay.vue';

const props = defineProps({
  entry: { type: Object, required: true },
});

const config  = ref({});
const loading = ref(false);
const error   = ref(null);
const teams   = ref([]);
const scrollEl = ref(null);
const containerHeight = ref('400px');

let scrollTimer  = null;
let refreshTimer = null;

const cols = computed(() => config.value.standingColumns ?? {
  totalMatches: true, won: true, draw: true, lost: true,
  goalsFor: true, goalsAgainst: true, goalsDiff: true, points: true,
});

// Compact column sizing tuned for the multi-box overview — the single-team
// StandingsList keeps its original wider layout.
const posStyle  = computed(() => ({
  background: config.value.leftBoxColor     || '#b40808',
  color:      config.value.leftBoxText      || '#ffffff',
  flex: '0 0 36px', textAlign: 'center', padding: '10px 4px', fontWeight: '700',
}));
const nameStyle = computed(() => ({
  background: config.value.leftMidBoxColor  || '#000000',
  color:      config.value.leftMidBoxText   || '#ffffff',
  flex: '1 1 auto', minWidth: '90px', padding: '10px 10px',
  whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
}));
const statStyle = computed(() => ({
  background: config.value.midBoxColor      || '#de0b0b',
  color:      config.value.midBoxText       || '#ffffff',
  flex: '0 0 32px', textAlign: 'center', padding: '10px 2px',
}));
const goalStyle = computed(() => ({
  background: config.value.rightMidBoxColor || '#000000',
  color:      config.value.rightMidBoxText  || '#ffffff',
  flex: '0 0 36px', textAlign: 'center', padding: '10px 2px',
}));
const ptsStyle  = computed(() => ({
  background: config.value.rightBoxColor    || '#b40808',
  color:      config.value.rightBoxText     || '#ffffff',
  flex: '0 0 40px', textAlign: 'center', padding: '10px 4px', fontWeight: '700',
}));

function isOwnTeam(team) {
  if (team.LocalTeam === true) return true;
  const ids = String(props.entry.teamId || '').split(',').map(s => s.trim()).filter(Boolean);
  return ids.includes(String(team.PublicTeamId));
}

function rowStyle(base, team) {
  if (!isOwnTeam(team)) return base;
  return {
    ...base,
    background: config.value.ownTeamBg   || '#1a5c1a',
    color:      config.value.ownTeamText || '#ffffff',
    fontWeight: '700',
  };
}

function fmtDiff(n) { return n > 0 ? '+' + n : n; }

function calculateHeight() {
  const el = scrollEl.value;
  if (!el) return;
  const fixed = config.value?.displayHeight;
  containerHeight.value = (fixed && fixed > 0)
    ? `${fixed}px`
    : `${Math.max(300, window.innerHeight - el.getBoundingClientRect().top - 20)}px`;
}

function startAutoScroll() {
  if (scrollTimer) clearInterval(scrollTimer);
  let pauseUntil = 0;
  scrollTimer = setInterval(() => {
    const el = scrollEl.value;
    if (!el || Date.now() < pauseUntil) return;
    const speed = config.value?.scrollSpeed || 2;
    el.scrollTop += speed;
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 4) {
      el.scrollTop = 0;
      pauseUntil = Date.now() + 3000;
    }
  }, 50);
}

function buildPerEntryConfig(base, entry) {
  return {
    ...base,
    standingPoolId: entry.poolId,
    standingTeamId: entry.teamId,
  };
}

async function loadStandings() {
  loading.value = true;
  error.value = null;
  try {
    teams.value = await fetchStandings(config.value);
    await nextTick();
    calculateHeight();
    if (teams.value.length) startAutoScroll();
  } catch (err) {
    error.value = err.message || 'Fout bij laden van standen.';
    console.error('StandingsTable:', props.entry, err);
  } finally {
    loading.value = false;
  }
}

watch(
  () => [USER_CONFIG.value, props.entry?.poolId, props.entry?.teamId],
  ([newConfig]) => {
    if (!newConfig) return;
    config.value = buildPerEntryConfig(newConfig, props.entry);
    if (props.entry.poolId) loadStandings();
  },
  { immediate: true, deep: true }
);

onMounted(() => {
  window.addEventListener('resize', calculateHeight);
  refreshTimer = setInterval(loadStandings, 30 * 60 * 1000);
});

onUnmounted(() => {
  clearInterval(scrollTimer);
  clearInterval(refreshTimer);
  window.removeEventListener('resize', calculateHeight);
});
</script>

<style scoped>
.scv-standings-box {
  display: flex;
  flex-direction: column;
  min-width: 0;
  width: 100%;
  font-size: 14px;
  font-weight: 600;
  position: relative;
  overflow: hidden;
}
.scv-standings-box :deep(.matchEntry) {
  min-width: 0;
}
.scv-standings-box-title {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 4px 12px;
  text-align: center;
  color: #fff;
}
.scv-standings-box-team {
  font-size: 1.3em;
  font-weight: 700;
  line-height: 1.1;
}
.scv-standings-box-pool {
  font-size: 0.85em;
  opacity: 0.8;
}
.scv-standings-msg {
  text-align: center;
  padding: 24px 12px;
  color: #fff;
}
.scv-standings-error {
  color: #ffb4b4;
}
</style>

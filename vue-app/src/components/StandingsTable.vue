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
    <template v-else-if="rows.length">
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

      <div ref="scrollEl" class="scrollingContainer" :style="{ height: containerHeight }">
        <div
          v-for="team in rows"
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
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import NoMatchesDisplay from '@/components/NoMatchesDisplay.vue';
import { useAutoScroll } from '@/composables/useAutoScroll';
import { USER_CONFIG } from '@/config';
import { fetchStandings } from '@/utils/matchFetchHelpers';

const REFRESH_MS = 30 * 60 * 1000;

const props = defineProps({
  entry: { type: Object, required: true },
});

const cfg = USER_CONFIG;
const rows = ref([]);
const loading = ref(false);
const error = ref(null);
const scrollEl = ref(null);

const cols = computed(() => cfg.value.standingColumns);

// Compact column sizing tuned for the multi-box overview.
const posStyle  = computed(() => ({ background: cfg.value.leftBoxColor,     color: cfg.value.leftBoxText,     flex: '0 0 36px', textAlign: 'center', padding: '10px 4px', fontWeight: '700' }));
const nameStyle = computed(() => ({ background: cfg.value.leftMidBoxColor,  color: cfg.value.leftMidBoxText,  flex: '1 1 auto', minWidth: '90px', padding: '10px 10px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }));
const statStyle = computed(() => ({ background: cfg.value.midBoxColor,      color: cfg.value.midBoxText,      flex: '0 0 32px', textAlign: 'center', padding: '10px 2px' }));
const goalStyle = computed(() => ({ background: cfg.value.rightMidBoxColor, color: cfg.value.rightMidBoxText, flex: '0 0 36px', textAlign: 'center', padding: '10px 2px' }));
const ptsStyle  = computed(() => ({ background: cfg.value.rightBoxColor,    color: cfg.value.rightBoxText,    flex: '0 0 40px', textAlign: 'center', padding: '10px 4px', fontWeight: '700' }));

function isOwnTeam(team) {
  if (team.LocalTeam === true) return true;
  const ids = String(props.entry.teamId || '').split(',').map((s) => s.trim()).filter(Boolean);
  return ids.includes(String(team.PublicTeamId));
}

function rowStyle(base, team) {
  if (!isOwnTeam(team)) return base;
  return { ...base, background: cfg.value.ownTeamBg, color: cfg.value.ownTeamText, fontWeight: '700' };
}

const fmtDiff = (n) => (n > 0 ? `+${n}` : n);

const { containerHeight, recalcHeight, start } = useAutoScroll(scrollEl, {
  stepPx: () => cfg.value.scrollSpeed || 2,
  intervalMs: 50,
  fixedHeight: () => cfg.value.displayHeight,
});

async function loadStandings() {
  loading.value = true;
  error.value = null;
  try {
    rows.value = await fetchStandings({ ...cfg.value, standingPoolId: props.entry.poolId, standingTeamId: props.entry.teamId });
    await nextTick();
    recalcHeight();
    start();
  } catch (err) {
    error.value = err.message || 'Fout bij laden van standen.';
    console.error('StandingsTable:', props.entry, err);
  } finally {
    loading.value = false;
  }
}

watch(() => [props.entry?.poolId, props.entry?.teamId, cfg.value.connectionType], () => {
  if (props.entry?.poolId) loadStandings();
}, { immediate: true });

let refreshTimer = null;
onMounted(() => {
  refreshTimer = setInterval(loadStandings, REFRESH_MS);
});
onUnmounted(() => clearInterval(refreshTimer));
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

<template>
  <main role="main" class="container-fluid" id="contentBox">
    <div id="rcorners_matchinfo_fixed">

      <div v-if="loading" id="noMatchMessage">
        <h1>Stand wordt geladen...</h1>
      </div>
      <div v-else-if="error" id="noMatchMessage">
        <h1>{{ error }}</h1>
      </div>
      <template v-else-if="rows.length">

        <!-- Fixed header -->
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

        <!-- Scrollable rows -->
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
  </main>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import NoMatchesDisplay from '@/components/NoMatchesDisplay.vue';
import { useAutoScroll } from '@/composables/useAutoScroll';
import { useMatchData } from '@/composables/useMatchData';
import { fetchStandings } from '@/utils/matchFetchHelpers';

const REFRESH_MS = 30 * 60 * 1000;
const scrollEl = ref(null);

const { config, rows, loading, error, reload } = useMatchData(fetchStandings, {
  isConfigured: (cfg) => !!cfg.standingPoolId,
  errorMessage: 'Fout bij laden van standen.',
});

const cfg = computed(() => config.value);
const cols = computed(() => cfg.value.standingColumns);

const posStyle  = computed(() => ({ background: cfg.value.leftBoxColor,     color: cfg.value.leftBoxText,     flex: '0 0 52px', textAlign: 'center', padding: '14px 8px', fontWeight: '700' }));
const nameStyle = computed(() => ({ background: cfg.value.leftMidBoxColor,  color: cfg.value.leftMidBoxText,  flex: 1, padding: '14px 16px' }));
const statStyle = computed(() => ({ background: cfg.value.midBoxColor,      color: cfg.value.midBoxText,      flex: '0 0 44px', textAlign: 'center', padding: '14px 6px' }));
const goalStyle = computed(() => ({ background: cfg.value.rightMidBoxColor, color: cfg.value.rightMidBoxText, flex: '0 0 44px', textAlign: 'center', padding: '14px 6px' }));
const ptsStyle  = computed(() => ({ background: cfg.value.rightBoxColor,    color: cfg.value.rightBoxText,    flex: '0 0 52px', textAlign: 'center', padding: '14px 8px', fontWeight: '700' }));

function isOwnTeam(team) {
  if (team.LocalTeam === true) return true;
  const ids = String(cfg.value.standingTeamId || '').split(',').map((s) => s.trim()).filter(Boolean);
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

async function restart() {
  await nextTick();
  recalcHeight();
  start();
}

watch(rows, restart);

let refreshTimer = null;
onMounted(() => {
  restart();
  refreshTimer = setInterval(reload, REFRESH_MS);
});
onUnmounted(() => clearInterval(refreshTimer));
</script>

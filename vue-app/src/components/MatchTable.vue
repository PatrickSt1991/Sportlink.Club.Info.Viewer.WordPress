<template>
  <main role="main" class="container-fluid" id="contentBox">
    <div id="rcorners_matchinfo_fixed">
      <div class="matchEntry match-header">
        <template v-for="col in visibleColumns" :key="col.key">
          <div v-if="col.type === 'logo'" class="clublogo-wrap" :class="logoClass(col)" :style="{ background: colors(col).background }"></div>
          <div v-else class="matchCol" :class="[col.cssClass, `col-${col.key}`]" :style="colStyle(col)">{{ col.header }}</div>
        </template>
      </div>

      <div v-if="loading" id="noMatchMessage">
        <h1>{{ loadingText }}</h1>
      </div>

      <div v-else-if="error" id="noMatchMessage">
        <h1>{{ error }}</h1>
      </div>

      <NoMatchesDisplay v-else-if="rows.length === 0" :title="emptyTitle" :message="emptyMessage" />

      <div v-else ref="scrollEl" class="scrollingContainer" :style="{ height: containerHeight }">
        <transition-group name="fade" tag="div">
          <div v-for="row in rows" :key="row.id" class="matchEntry">
            <template v-for="col in visibleColumns" :key="col.key">
              <div v-if="col.type === 'logo'" class="clublogo-wrap" :class="logoClass(col)" :style="{ background: colors(col).background }">
                <img class="clublogo" :src="row[col.field] || noImage" alt="" loading="lazy" />
              </div>
              <div v-else class="matchCol" :class="[col.cssClass, `col-${col.key}`]" :style="colStyle(col)">
                {{ col.format ? col.format(row) : row[col.field] }}
              </div>
            </template>
          </div>
        </transition-group>
      </div>
    </div>
  </main>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import noImage from '@/assets/no_image.svg';
import NoMatchesDisplay from '@/components/NoMatchesDisplay.vue';
import { useAutoScroll } from '@/composables/useAutoScroll';
import { USER_CONFIG } from '@/config';
import { nextScreen } from '@/utils/screenRotation';
import { DEFAULT_FLEX } from './matchColumns';

const props = defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  error: { type: String, default: null },
  loadingText: { type: String, default: 'Laden...' },
  emptyTitle: { type: String, default: 'Geen wedstrijden' },
  emptyMessage: { type: String, default: '' },
});

const config = USER_CONFIG;
const route = useRoute();
const router = useRouter();
const scrollEl = ref(null);

const visibleColumns = computed(() =>
  props.columns.filter((col) => {
    if (col.type === 'logo') return config.value.showLogos !== false;
    return col.alwaysVisible || config.value.columnVisible?.[col.section] !== false;
  })
);

const logoClass = (col) => (col.field === 'thuisteamlogo' ? 'clublogo-wrap--home' : 'clublogo-wrap--away');

function colors(col) {
  const section = col.colorSection ?? col.section;
  return { background: config.value[`${section}BoxColor`], color: config.value[`${section}BoxText`] };
}

function colStyle(col) {
  return { ...colors(col), flex: config.value.columnWidths?.[col.section] ?? col.flex ?? DEFAULT_FLEX[col.section] };
}

function switchScreen() {
  const next = nextScreen(route.path, config.value);
  if (!next) return false;
  router.push(next);
  return true;
}

const { containerHeight, recalcHeight, start } = useAutoScroll(scrollEl, {
  stepPx: () => config.value.scrollSpeed || 2,
  intervalMs: 50,
  fixedHeight: () => config.value.displayHeight,
  onCyclesComplete: switchScreen,
  onIdle: switchScreen,
});

async function restart() {
  await nextTick();
  recalcHeight();
  start();
}

watch(() => props.rows, restart);
onMounted(restart);
</script>

import { ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { USER_CONFIG } from '@/config';
import { HttpError } from '@/api/wpClient';

export const hasClubIdentifier = (config) => !!(config?.clientId || config?.clubIdentifer || config?.clubId);

/** The config fields that influence which data a screen shows. */
export const dataKey = (config) =>
  JSON.stringify([
    config.connectionType,
    config.clientId,
    config.clubIdentifer,
    config.clubId,
    config.gameType?.label ?? null,
    config.sportLocatie,
    config.programmaDagen,
    config.uitslagDagen,
    config.standingPoolId,
  ]);

/**
 * Shared loading/error/redirect logic for the display screens.
 *
 * @param {(config) => Promise<Array>} load
 * @param {{ isConfigured?: (config) => boolean, errorMessage?: string }} options
 */
export function useMatchData(load, { isConfigured, errorMessage = 'Error tijdens het laden...' } = {}) {
  const router = useRouter();
  const config = USER_CONFIG;

  const rows = ref([]);
  const loading = ref(false);
  const error = ref(null);

  async function reload() {
    loading.value = true;
    error.value = null;
    try {
      rows.value = await load(config.value);
    } catch (err) {
      // The REST layer returns a readable Dutch message (e.g. "nog niet geconfigureerd").
      error.value = err instanceof HttpError && err.message && !err.message.startsWith('HTTP ') ? err.message : errorMessage;
      console.error('Error loading screen data:', err);
    } finally {
      loading.value = false;
    }
  }

  watch(
    () => dataKey(config.value),
    () => {
      if (!hasClubIdentifier(config.value) || (isConfigured && !isConfigured(config.value))) {
        router.push('/no-config');
      } else {
        reload();
      }
    },
    { immediate: true }
  );

  return { config, rows, loading, error, reload };
}

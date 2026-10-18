// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import Toast from 'vue-toastification';
import App from '@/App.vue';
import { createAppRouter } from '@/router';
import { initConfigFromWP } from '@/config';
import program from './fixtures/sportlinkApiProgram.json';

const json = (body) => ({ ok: true, status: 200, json: () => Promise.resolve(body) });

async function boot(appType, wpConfig) {
  initConfigFromWP(wpConfig);
  const router = createAppRouter(appType);
  const wrapper = mount(App, { global: { plugins: [createPinia(), router, [Toast, {}]] } });
  await router.isReady();
  await flushPromises();
  await flushPromises();
  return { wrapper, router };
}

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'setInterval', 'clearTimeout', 'clearInterval', 'Date'], shouldAdvanceTime: true });
  vi.setSystemTime(new Date('2026-10-18T13:00:00+02:00'));
  globalThis.fetch = vi.fn((url) => {
    const u = String(url);
    if (u.endsWith('/program') || u.endsWith('/results')) return Promise.resolve(json(program));
    return Promise.resolve(json({}));
  });
});

afterEach(() => {
  vi.useRealTimers();
  document.body.innerHTML = '';
});

describe('WordPress front-end boot', () => {
  it('shows the not-configured screen without a data source', async () => {
    const { wrapper, router } = await boot('match-display', { restUrl: '/wp-json/scv/v1/' });
    expect(router.currentRoute.value.path).toBe('/no-config');
    expect(wrapper.text()).toContain('nog niet geconfigureerd');
    wrapper.unmount();
  });

  it('renders the programma from the REST API with class-based columns', async () => {
    const { wrapper, router } = await boot('match-display', {
      restUrl: '/wp-json/scv/v1/', gameTypeLabel: 'Voetbal', connectionType: 'Sportlink API', clientId: 'abc', programmaDagen: 7, uitslagDagen: 7,
    });
    expect(router.currentRoute.value.path).toBe('/match-info');
    expect(fetch).toHaveBeenCalledWith('/wp-json/scv/v1/program', expect.anything());

    const rows = wrapper.findAll('.matchEntry:not(.match-header)');
    expect(rows).toHaveLength(3);
    expect(rows[0].text()).toContain('SV Test 1');
    expect(wrapper.find('[id="thuisteam_fixed"]').exists()).toBe(false);
    expect(wrapper.html()).not.toContain('cors-proxy');
    wrapper.unmount();
  });

  it('switches from programma to uitslagen after two scroll passes', async () => {
    const isScroller = (el) => el.classList?.contains('scrollingContainer');
    Object.defineProperty(HTMLElement.prototype, 'scrollHeight', { configurable: true, get() { return isScroller(this) ? 300 : 0; } });
    Object.defineProperty(HTMLElement.prototype, 'clientHeight', { configurable: true, get() { return isScroller(this) ? 100 : 0; } });

    const { wrapper, router } = await boot('match-display', {
      restUrl: '/wp-json/scv/v1/', gameTypeLabel: 'Voetbal', connectionType: 'Sportlink API', clientId: 'abc', enableScreenSwitch: true, scrollSpeed: 2,
    });
    expect(router.currentRoute.value.path).toBe('/match-info');

    // 200px per pass at 2px / 50ms => two passes take 10s
    vi.advanceTimersByTime(12_000);
    await flushPromises();
    expect(router.currentRoute.value.path).toBe('/match-results');

    delete HTMLElement.prototype.scrollHeight;
    delete HTMLElement.prototype.clientHeight;
    wrapper.unmount();
  });
});

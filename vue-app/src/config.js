import { ref } from 'vue';

export const GAME_TYPES = [
  { label: 'Voetbal',          types: [{ type: 'Sportlink API', active: true }, { type: 'Sportlink Proxy', active: true }] },
  { label: 'Basketbal',        types: [{ type: 'Sportlink API', active: true }, { type: 'Sportlink Proxy', active: true }] },
  { label: 'Korfbal',          types: [{ type: 'Sportlink API', active: true }, { type: 'Sportlink Proxy', active: true }] },
  { label: 'Soft- en Honkbal', types: [{ type: 'Sportlink API', active: true }, { type: 'Sportlink Proxy', active: true }] },
  { label: 'Volleybal',        types: [{ type: 'Sportlink API', active: true }, { type: 'Nevobo Proxy',    active: true }] },
  { label: 'Waterpolo',        types: [{ type: 'Sportlink API', active: true }, { type: 'Sportlink Proxy', active: true }] },
  { label: 'Hockey België',    types: [{ type: 'Sportlink API', active: true }, { type: 'Sportlink Proxy', active: true }] },
  { label: 'Handbal',          types: [{ type: 'Sportlink API', active: true }, { type: 'Sportlink Proxy', active: true }] },
];

const DEFAULT_COLORS = {
  leftBoxColor: '#b40808', leftBoxText: '#ffffff',
  leftMidBoxColor: '#000000', leftMidBoxText: '#ffffff',
  midBoxColor: '#de0b0b', midBoxText: '#ffffff',
  rightMidBoxColor: '#000000', rightMidBoxText: '#ffffff',
  rightBoxColor: '#b40808', rightBoxText: '#ffffff',
};

export const defaultConfig = {
  restUrl: '/wp-json/scv/v1/',
  debug: false,
  clientId: null, clubId: null, clubIdentifer: null, connectionType: null, gameType: null,
  sportLocatie: null, programmaDagen: 7, uitslagDagen: 7,
  prematchRefresh: 15, enableScreenSwitch: true, activeSponsors: false, selectedBackground: '',
  sponsorImages: [],
  displayHeight: 0, scrollSpeed: 2,
  enableStandings: false, standingTeamId: null, standingPoolId: null,
  standingsList: [], overviewColumns: 'auto', overviewFullWidth: true,
  ownTeamBg: '#1a5c1a', ownTeamText: '#ffffff',
  standingColumns: { totalMatches: true, won: true, draw: true, lost: true, goalsFor: true, goalsAgainst: true, goalsDiff: true, points: true },
  columnWidths: { left: 2, leftMid: 9, mid: 4, rightMid: 9, right: 3 },
  columnVisible: { left: true, leftMid: true, mid: true, rightMid: true, right: true },
  showLogos: true,
  ...DEFAULT_COLORS,
};

const bool = (value, fallback) => (value === undefined || value === null ? fallback : value !== false && value !== 0 && value !== '0' && value !== '');

/** Build the runtime config from the JSON WordPress embeds next to the shortcode. */
export function buildConfigFromWP(wp = {}) {
  const label = wp.gameTypeLabel || '';
  const gameType = GAME_TYPES.find((t) => t.label.toLowerCase() === label.toLowerCase()) || null;

  const columns = {};
  for (const key of Object.keys(defaultConfig.standingColumns)) {
    columns[key] = wp.standingColumns?.[key] !== false;
  }

  const colors = {};
  for (const key of Object.keys(DEFAULT_COLORS)) {
    colors[key] = wp[key] || DEFAULT_COLORS[key];
  }

  return {
    ...defaultConfig,
    restUrl: wp.restUrl || defaultConfig.restUrl,
    debug: !!wp.debug,
    clientId: wp.clientId || null,
    clubId: wp.clubId || null,
    clubIdentifer: wp.clubIdentifer || null, // typo preserved: it is the persisted key name
    connectionType: wp.connectionType || null,
    gameType,
    sportLocatie: wp.sportLocatie || null,
    programmaDagen: Number(wp.programmaDagen) || 7,
    uitslagDagen: Number(wp.uitslagDagen) || 7,
    prematchRefresh: Number(wp.prematchRefresh) || 15,
    enableScreenSwitch: bool(wp.enableScreenSwitch, true),
    activeSponsors: bool(wp.activeSponsors, false),
    selectedBackground: wp.selectedBackground || '',
    sponsorImages: Array.isArray(wp.sponsorImages) ? wp.sponsorImages : [],
    displayHeight: Number(wp.displayHeight) || 0,
    scrollSpeed: Number(wp.scrollSpeed) || 2,
    enableStandings: bool(wp.enableStandings, false),
    standingTeamId: wp.standingTeamId || null,
    standingPoolId: wp.standingPoolId || null,
    standingsList: Array.isArray(wp.standingsList) ? wp.standingsList : [],
    overviewColumns: wp.overviewColumns || 'auto',
    overviewFullWidth: bool(wp.overviewFullWidth, true),
    ownTeamBg: wp.ownTeamBg || defaultConfig.ownTeamBg,
    ownTeamText: wp.ownTeamText || defaultConfig.ownTeamText,
    standingColumns: columns,
    columnWidths: {
      left: wp.columnWidths?.left ?? 2,
      leftMid: wp.columnWidths?.leftMid ?? 9,
      mid: wp.columnWidths?.mid ?? 4,
      rightMid: wp.columnWidths?.rightMid ?? 9,
      right: wp.columnWidths?.right ?? 3,
    },
    columnVisible: {
      left: wp.columnVisible?.left !== false,
      leftMid: wp.columnVisible?.leftMid !== false,
      mid: wp.columnVisible?.mid !== false,
      rightMid: wp.columnVisible?.rightMid !== false,
      right: wp.columnVisible?.right !== false,
    },
    showLogos: wp.showLogos !== false,
    ...colors,
  };
}

export const USER_CONFIG = ref(
  typeof window !== 'undefined' && window.scvConfig ? buildConfigFromWP(window.scvConfig) : { ...defaultConfig }
);

/** Re-initialise from a raw WordPress config object (called by main.js per mount). */
export function initConfigFromWP(wpConfig) {
  if (wpConfig && typeof wpConfig === 'object') {
    USER_CONFIG.value = buildConfigFromWP(wpConfig);
  }
}

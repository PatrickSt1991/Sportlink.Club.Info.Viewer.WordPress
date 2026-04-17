import { ref } from 'vue';

// ── Static app credentials (mirrors original config.js) ─────────────────────
export const FAKE_CREDENTIALS = [
  {
    username: 'rxxnrextolzwlqsspy@hthlm.com',
    password: 'test1234',
    sports: [
      { sport: 'voetbal' }, { sport: 'waterpolo' }, { sport: 'hockey belgië' },
      { sport: 'soft- en honkbal' }, { sport: 'basketbal' }, { sport: 'handbal' }, { sport: 'korfbal' }
    ]
  }
];

export const APP_CREDENTIALS = [
  { type: 'Voetbal',          client_id: 'oCuV9oozaaz8zee',      secret: 'eep7Shoo7i',       instance: 'KNVB',  userAgent: 'voetbalnl', apiUrl: 'vnl' },
  { type: 'Waterpolo',        client_id: '4BtKnhojt4MSnRScVak5', secret: 'vLD8uPHOgIHJjAj9', instance: 'KNZB',  userAgent: 'knzb',      apiUrl: 'sportlinked' },
  { type: 'hockey belgië',    client_id: 'YqTh94xQBASRCtTmpa0b', secret: '15T74iIa011VVoAm', instance: 'KBHB',  userAgent: 'kbhb',      apiUrl: 'sportlinked' },
  { type: 'soft- en honkbal', client_id: '0SaoFKzAVgn3cTzxUsk8', secret: 'H1LRQnWYxm10YA87', instance: 'KNBSB', userAgent: 'knbsb',     apiUrl: 'sportlinked' },
  { type: 'Basketbal',        client_id: '4boXZaODcf1A5ffb7zMl', secret: 'netkEQKiWAsFEwl3', instance: 'NBB',   userAgent: 'nbb',       apiUrl: 'sportlinked' },
  { type: 'Handbal',          client_id: 'JUian2haoKqIripvaios', secret: '9BdMs5h9jvr9Agte', instance: 'NHV',   userAgent: 'nhv',       apiUrl: 'sportlinked' },
  { type: 'Korfbal',          client_id: 'SdJSHVPuWzK066Mu28ki', secret: 'j2OInPPCmWJ0VA2W', instance: 'KNKV',  userAgent: 'knkv',      apiUrl: 'sportlinked' },
];

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

// ── Build config from WordPress-provided scvConfig ───────────────────────────

function buildConfigFromWP(wpConfig) {
  const gameTypeLabel = wpConfig.gameTypeLabel || '';
  const gameType = GAME_TYPES.find(
    t => t.label.toLowerCase() === gameTypeLabel.toLowerCase()
  ) || null;

  return {
    // Connection
    clientId:       wpConfig.clientId       || null,
    clubId:         wpConfig.clubId         || null,
    clubIdentifer:  wpConfig.clubIdentifer  || null, // typo preserved for API compatibility
    connectionType: wpConfig.connectionType || null,
    gameType,

    // Credentials
    username:         wpConfig.username         || '',
    password:         wpConfig.password         || '',
    fakeCredentials:  !!wpConfig.fakeCredentials,
    validUsername:    !!(wpConfig.username),
    validPassword:    !!(wpConfig.password),
    validClientId:    !!(wpConfig.clientId),

    // Display
    sportLocatie:       wpConfig.sportLocatie       || null,
    programmaDagen:     wpConfig.programmaDagen      || 7,
    uitslagDagen:       wpConfig.uitslagDagen        || 7,
    prematchRefresh:    wpConfig.prematchRefresh      || 15,
    enableScreenSwitch: wpConfig.enableScreenSwitch  !== false,
    activeSponsors:     !!wpConfig.activeSponsors,
    selectedBackground: wpConfig.selectedBackground  || '',
    displayHeight:      wpConfig.displayHeight       || 0,
    scrollSpeed:        wpConfig.scrollSpeed         || 2,

    // Standings
    enableStandings: !!wpConfig.enableStandings,
    standingTeamId:  wpConfig.standingTeamId  || null,
    standingPoolId:  wpConfig.standingPoolId  || null,
    standingColumns: {
      totalMatches: wpConfig.standingColumns?.totalMatches !== false,
      won:          wpConfig.standingColumns?.won          !== false,
      draw:         wpConfig.standingColumns?.draw         !== false,
      lost:         wpConfig.standingColumns?.lost         !== false,
      goalsFor:     wpConfig.standingColumns?.goalsFor     !== false,
      goalsAgainst: wpConfig.standingColumns?.goalsAgainst !== false,
      goalsDiff:    wpConfig.standingColumns?.goalsDiff    !== false,
      points:       wpConfig.standingColumns?.points       !== false,
    },

    // Layout
    columnWidths: {
      left:     wpConfig.columnWidths?.left     ?? 2,
      leftMid:  wpConfig.columnWidths?.leftMid  ?? 9,
      mid:      wpConfig.columnWidths?.mid      ?? 4,
      rightMid: wpConfig.columnWidths?.rightMid ?? 9,
      right:    wpConfig.columnWidths?.right    ?? 3,
    },
    columnVisible: {
      left:     wpConfig.columnVisible?.left     !== false,
      leftMid:  wpConfig.columnVisible?.leftMid  !== false,
      mid:      wpConfig.columnVisible?.mid      !== false,
      rightMid: wpConfig.columnVisible?.rightMid !== false,
      right:    wpConfig.columnVisible?.right    !== false,
    },
    showLogos: wpConfig.showLogos !== false,

    // Colors
    leftBoxColor:     wpConfig.leftBoxColor     || '#b40808',
    leftBoxText:      wpConfig.leftBoxText      || '#ffffff',
    leftMidBoxColor:  wpConfig.leftMidBoxColor  || '#000000',
    leftMidBoxText:   wpConfig.leftMidBoxText   || '#ffffff',
    midBoxColor:      wpConfig.midBoxColor      || '#de0b0b',
    midBoxText:       wpConfig.midBoxText       || '#ffffff',
    rightMidBoxColor: wpConfig.rightMidBoxColor || '#000000',
    rightMidBoxText:  wpConfig.rightMidBoxText  || '#ffffff',
    rightBoxColor:    wpConfig.rightBoxColor    || '#b40808',
    rightBoxText:     wpConfig.rightBoxText     || '#ffffff',
  };
}

const defaultConfig = {
  clientId: null, clubId: null, clubIdentifer: null, connectionType: null, gameType: null,
  username: '', password: '', fakeCredentials: false,
  validUsername: false, validPassword: false, validClientId: false,
  sportLocatie: null, programmaDagen: 7, uitslagDagen: 7,
  prematchRefresh: 15, enableScreenSwitch: true, activeSponsors: false, selectedBackground: '',
  displayHeight: 0, scrollSpeed: 2,
  enableStandings: false, standingTeamId: null, standingPoolId: null,
  standingColumns: { totalMatches: true, won: true, draw: true, lost: true, goalsFor: true, goalsAgainst: true, goalsDiff: true, points: true },
  columnWidths: { left: 2, leftMid: 9, mid: 4, rightMid: 9, right: 3 },
  columnVisible: { left: true, leftMid: true, mid: true, rightMid: true, right: true },
  showLogos: true,
  leftBoxColor: '#b40808', leftBoxText: '#ffffff',
  leftMidBoxColor: '#000000', leftMidBoxText: '#ffffff',
  midBoxColor: '#de0b0b', midBoxText: '#ffffff',
  rightMidBoxColor: '#000000', rightMidBoxText: '#ffffff',
  rightBoxColor: '#b40808', rightBoxText: '#ffffff',
};

const initialConfig = (typeof window !== 'undefined' && window.scvConfig)
  ? buildConfigFromWP(window.scvConfig)
  : defaultConfig;

export const USER_CONFIG = ref(initialConfig);

// No-op update (config is read-only in WP context; kept for composable compatibility)
export const updateUserConfig = (newConfig) => {
  Object.assign(USER_CONFIG.value, JSON.parse(JSON.stringify(newConfig)));
};

/**
 * Re-initialise USER_CONFIG from a raw WP config object.
 * Called by main.js after reading the per-shortcode JSON config element so the
 * reactive ref is always populated regardless of wp_localize_script behaviour.
 */
export function initConfigFromWP(wpConfig) {
  if (wpConfig && typeof wpConfig === 'object') {
    Object.assign(USER_CONFIG.value, buildConfigFromWP(wpConfig));
  }
}

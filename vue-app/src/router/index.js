import { createRouter, createMemoryHistory } from 'vue-router';
import PreMatchInfo from '@/views/PreMatchInfo.vue';
import MatchInfo from '@/views/MatchInfo.vue';
import MatchResults from '@/views/MatchResults.vue';
import StandingsList from '@/views/StandingsList.vue';
import NoConfig from '@/views/NoConfig.vue';

const routes = [
  { path: '/prematch-info',  name: 'PreMatchInfo',  component: PreMatchInfo  },
  { path: '/match-info',     name: 'MatchInfo',     component: MatchInfo     },
  { path: '/match-results',  name: 'MatchResults',  component: MatchResults  },
  { path: '/standing-list',  name: 'StandingsList', component: StandingsList },
  { path: '/no-config',      name: 'NoConfig',      component: NoConfig      },
];

export function createAppRouter( appType ) {
  const initialPath = appType === 'prematch-display'  ? '/prematch-info'
                    : appType === 'standing-display'   ? '/standing-list'
                    : '/match-info';
  return createRouter( {
    history: createMemoryHistory(),
    routes: [
      ...routes,
      { path: '/:pathMatch(.*)*', redirect: initialPath },
    ],
  } );
}

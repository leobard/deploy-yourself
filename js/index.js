/* global panel */

import DeployYourself from './components/DeployYourself.vue'

panel.plugin('leobard/deploy-yourself', {
  components: {
    'deploy-yourself': DeployYourself
  }
})

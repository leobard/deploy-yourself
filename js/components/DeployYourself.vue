<template>
  <k-inside>
    <k-view class="k-deploy-yourself-view">
      <k-header>Deploy yourself</k-header>
	  <section v-if="message" class="k-section">
		<k-box :text="message" />
	  </section>
      <section class="k-section">
          <header class="k-section-header">
            <k-headline>Logfiles ({{ logfiles.length }})</k-headline>
          </header>
          <k-collection :items="logfilesCollection" />
      </section>
	  <section v-if="selectedfilecontent" class="k-section">
	    <k-text>{{ selectedfilename }}</k-text>
		<k-text><pre>{{ selectedfilecontent }}</pre></k-text>
      </section>
    </k-view>
  </k-inside>
</template>
<script>

export default {
  name: 'DeployYourself',
  props: {
    message: {
		type: String
	},
    logfiles: {
		type: Array,
		default: []
    },
	selectedfilecontent: {
		type: String
	},
	selectedfilename: {
		type: String
	}
  },
  computed: {
    logfilesCollection () {
      const list = [];

      this.logfiles.forEach(logfile => {
        list.push({
          text: logfile,
          info: '', 
          link: 'deploy-yourself/' + logfile,
        })
      })

      return list;
    }
  }
}
</script>

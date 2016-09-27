<template>
  <section id="foldersWrapper">
    <h1 class="heading">
      <span>Folders</span>
      <view-mode-switch :mode="viewMode" for="folders"></view-mode-switch>
    </h1>

    <sound-bar v-if="loading" class="sbcenter"></sound-bar>
    <div v-else class="folders" :class="'as-' + viewMode">
      <ul><folder-item :folder="sharedState.root" :level="0"></ul>

      <to-top-button :showing="showBackToTop"></to-top-button>
    </div>
  </section>
</template>

<script>
import { filterBy, limitBy, event } from '../../../utils';
import { folderStore } from '../../../stores';
import folderItem from '../../shared/folder-item.vue';
import viewModeSwitch from '../../shared/view-mode-switch.vue';
import infiniteScroll from '../../../mixins/infinite-scroll';
import soundBar from '../../shared/sound-bar.vue';

export default {
  mixins: [infiniteScroll],
  components: { folderItem, viewModeSwitch, soundBar },

  data() {
    return {  
      q: '', // the filter text, currently ignored
      sharedState: folderStore.state,
      viewMode: null,
      loading: true,
    };
  },

  computed: {
  },

  methods: {
    changeViewMode(mode) {
      this.viewMode = mode;
    },
  },

  created() {
    event.on({
      'koel:teardown': () => {
        this.q = '';
      },

      'filter:changed': q => this.q = q,

      'koel:hierarchyready': () => {
         this.sharedState = folderStore.state;
         this.loading = false;
      },
    });
  },
};
</script>

<style lang="sass">
@import "../../../../sass/partials/_vars.scss";
@import "../../../../sass/partials/_mixins.scss";

#foldersWrapper {
  .folders {
    @include artist-album-wrapper();
    ul {
      width: 100%;
      padding: 0;
      margin: 0;
      list-style: none;
    }
    overflow: auto;
  }
  .sbcenter {
    margin: 10px auto;
  }
  .sbcenter::after {
    content: 'Loading folder hierarchy...';
    width: 200px;
    display: inline-block;
    margin: 0 30px;
    line-height: 13px;
    color: $color2ndText;
    vertical-align: top;
  }
}
</style>

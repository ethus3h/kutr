<template>
  <section id="foldersWrapper">
    <h1 class="heading">
      <span>Folders</span>
      <view-mode-switch :mode="viewMode" for="folders"></view-mode-switch>
    </h1>

    <div class="folders" :class="'as-' + viewMode">
      <ul><folder-item :folder="sharedState.root"></ul>

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

export default {
  mixins: [infiniteScroll],
  components: { folderItem, viewModeSwitch },

  data() {
    return {  
      q: '', // the filter text, currently ignored
      sharedState: folderStore.state,
      viewMode: null,
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
      padding-left: 15px; 
    }
    overflow: auto;
  }
}
</style>

<template>
  <section id="foldersWrapper">
    <h1 class="heading">
      <span>Folders
        <controls-toggler :showing-controls="showingControls" @toggleControls="toggleControls"/>
      </span>
      <song-list-controls
        v-show="displaySongControls"
        @shuffleAll="shuffleAll"
        @shuffleSelected="shuffleSelected"
        :config="songListControlConfig"
        :selectedSongs="selectedSongs"
      />
    </h1>

    <sound-bar v-if="loading" class="sbcenter"></sound-bar>
    <div v-else class="folders" id="foldersContainer">
      <ul><folder-item :folder="sharedState.root" :level="0"></ul>

      <to-top-button :showing="showBackToTop"></to-top-button>
    </div>
  </section>
</template>

<script>
import { filterBy, limitBy, event } from '../../../utils'
import { folderStore, sharedStore } from '../../../stores'
import folderItem from '../../shared/folder-item.vue'
import infiniteScroll from '../../../mixins/infinite-scroll'
import hasSongList from '../../../mixins/has-song-list'
import soundBar from '../../shared/sound-bar.vue'

export default {
  mixins: [infiniteScroll, hasSongList],
  components: { folderItem, soundBar },

  data() {
    return {  
      q: '', // the filter text, currently ignored
      sharedState: folderStore.state,
      songsSelection: sharedStore.state.songsSelection,
      hasSelection: false,
      loading: true
    }
  },

  computed: {
    displaySongControls() {
      return this.hasSelection && (!this.isPhone || this.showingControls)
    }
  },

  methods: {
  },

  created() {
    event.on({
      'koel:teardown': () => {
        this.q = ''
      },

      'filter:changed': q => this.q = q,

      'koel:hierarchyready': () => {
         this.sharedState = folderStore.state
         this.songsSelection = sharedStore.state.songsSelection
         this.loading = false
      },

      'folder:selection': () => {
         this.hasSelection = this.songsSelection.length > 0
         if (this.hasSelection) this.setSelectedSongs(this.songsSelection)
      }
    })
  }
}
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
    ul.menu {
      min-width: 144px;
      width: auto;
    }
    overflow: auto;
  }
  .sbcenter {
    margin: 10px auto;
  }
  .sbcenter::after {
    content: 'Loading folder hierarchy...';
    width: 250px;
    display: inline-block;
    margin: 0 30px 0 10px;
    line-height: 13px;
    color: $color2ndText;
    vertical-align: top;
    position: absolute;
  }
}
</style>

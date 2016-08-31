<template>
  <li>
    <div :class="{folder: isFolder}">
      <span v-if="isFolder" class="folder">
        <a class="control" @click.prevent="play">
          <i class="fa fa-play"></i>
        </a>
      </span>
      <span class="fa" :class="{open: isOpen, close: !isOpen}"></span>
      <span class="name" @click="toggle">{{name}}</span>
    </div>
    <ul v-if="isFolder && isOpen">
      <folder-item v-for="dir in subFolders" :folder="dir"></folder-item>
      <table v-if="hasSongs">
        <tbody>
           <tr is="song-item" v-for="item in subSongs" :song="item" ref="rows"></tr>
        </tbody>
      </table>
    </ul>
  </li>
</template>

<script>
import { find, map, filter, forEach } from 'lodash';
import $ from 'jquery';

import { pluralize, orderBy } from '../../utils';
import { queueStore, artistStore, sharedStore, folderStore, songStore } from '../../stores';
import { playback, download } from '../../services';
import songItem from './song-item.vue';

export default {
  name: 'folder-item',
  props: ['folder'],
  filters: { pluralize },
  components: { songItem },

  watch: {
    folder: function(val, oldVal) {
    //  debugger;
    }
  },

  data() {
    return {
      isOpen: false,
      sharedState: sharedStore.state,
      name: this.folder.name,
    };
  },

  computed: {
    isFolder() {
      return this.folder.children.length !== 0;
    },
    
    hasSongs() {
      return find(this.folder.children, o => { return o.songId !== 0; }) !== undefined;
    },

    subSongs() {
      return map(filter(this.folder.children, o => { return o.songId !== 0; }), o => songStore.byId(o.songId));
    },

    subFolders() {
      return filter(this.folder.children, o => { return o.songId === 0; });
    },
  },

  methods: {
    /**
     * Toggle this folder view
     */
    toggle(e) {
      // @todo: should close all other folders in the current sibling level to avoid cluttering the interface
      // Need to close all siblings here first
      forEach(this.$parent.$children, comp => { if ("isOpen" in comp && comp.isOpen && comp.name !== this.name) comp.isOpen = false; });
      this.isOpen = !this.isOpen;
    },


    /**
     * Play all songs in the current album in track order,
     * or queue them up if Ctrl/Cmd key is pressed.
     */
    play(e) {
      if (e.metaKey || e.ctrlKey) {
        queueStore.queue(this.subSongs);
      } else {
        playback.queueAndPlay(orderBy(this.subSongs, 'track'));
      }
    },

    /**
     * Clicked a row in the children song list
     */
    rowClick(songId, e) {
      // @todo: Wait until phanan change the selection code logic, as I'm wondering it'll break here anyway
    },
    /**
     * Clicked on a row with Ctrl pressed
     */
    openContextMenu(songId, e) {
      // @todo: Copy the context menu component code from the song list here, or ignore it ?
    },
     

    /**
     * Download all songs in folder.
     */
// @todo
//    download() {
//      download.fromAlbum(this.album);
//    },

    /**
     * Allow dragging the album (actually, its songs).
     */
    dragStart(e) {
      const songIds = map(this.subSongs, 'id');
      e.dataTransfer.setData('application/x-koel.text+plain', songIds);
      e.dataTransfer.effectAllowed = 'move';

      // Set a fancy drop image using our ghost element.
      const $ghost = $('#dragGhost').text(`All ${songIds.length} song${songIds.length === 1 ? '' : 's'} in ${this.folder.name}`);
      e.dataTransfer.setDragImage($ghost[0], 0, 0);
    },

  },
};
</script>

<style lang="sass">
@import "../../../sass/partials/_vars.scss";
@import "../../../sass/partials/_mixins.scss";

@include artist-album-card();

div.folder {
  > span.folder {
    display: inline-block;
    height: 2em;
    line-height: 2em;
    width: 2em;
    background: #444;
    border-radius: 50%;
    text-align: center;
  }
  > span.open::before {
    content: "\f07c";
  }
  > span.close::before {
    content: "\f07b";
  }

}


.sep {
  display: none;
  color: $color2ndText;

  .as-list & {
    display: inline;
  }
}
</style>

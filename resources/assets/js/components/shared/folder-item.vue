<template>
  <li>
    <div :class="{folder: isFolder}" :style="{marginLeft: (level ? level*20 : 4) + 'px'}">
      <span class="fa" :class="{open: isOpen, close: !isOpen}" @click="toggle"></span>
      <span class="name" @click="toggle">{{name}}</span>
      <span v-if="isFolder" class="folder">
        <a class="control" @click.prevent="play">
          <i class="fa fa-play-circle"></i>
          This
        </a><a class="control" @click.prevent="playRecursive">
          <i class="fa fa-play"></i>
          All
        </a>
      </span>
    </div>
    <ul v-if="isFolder && isOpen">
      <folder-item v-for="dir in subFolders" :folder="dir" :level="level+1"></folder-item>
      <table v-if="hasSongs">
        <thead>
          <tr>
            <th class="track-number">#</th>
            <th class="title">Title</th>
            <th class="artist">Artist</th>
            <th class="album">Album</th>
            <th class="time">Time</th>
            <th class="play"></th>
          </tr>
        </thead>
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
import Vue from 'vue';

import { pluralize, orderBy } from '../../utils';
import { queueStore, artistStore, sharedStore, folderStore, songStore } from '../../stores';
import { playback, download } from '../../services';
import songItem from './song-item.vue';

export default {
  name: 'folder-item',
  props: ['folder', 'level'],
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

    subSongsRecursive() {
      // Unfold the recursive array first (@todo make a nicer function here)
      var children = [];
      function unfold(o) {
          forEach(o.children, i => { if (i.songId === 0) return unfold(i); children.push(songStore.byId(i.songId)); });
      }
      unfold(this.folder);
      return children;
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
      // Need to close all siblings here first
      forEach(this.$parent.$children, comp => { if ("isOpen" in comp && comp.isOpen && comp.name !== this.name) comp.isOpen = false; });
      this.isOpen = !this.isOpen;
      if (this.isOpen) {
        Vue.nextTick(() => {
          // Scroll to ensure it's visible
          var $this = $(e.target);
          var container = $('#foldersContainer');
          var distance = $this.offset().top - container.offset().top;
          if (Math.abs(distance) < container.height()) {
            // If the element is visible animate scrolling to it
            container.animate({ scrollTop: distance + container.scrollTop() });
          } else {
            // Element is not visible, so don't wait time animating, it's distracting, just fix the scrolling position so it fits directly on the toggled element
            container.scrollTop(distance + container.scrollTop());
          }
        });
      }
    },


    /**
     * Play all songs in the current folder in track order,
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
     * Play all the songs found recursively in the current album in track order,
     * or queue them up if Ctrl/Cmd key is pressed.
     */
    playRecursive(e) {
      if (e.metaKey || e.ctrlKey) {
        queueStore.queue(this.subSongsRecursive);
      } else {
        playback.queueAndPlay(this.subSongsRecursive);
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

div.folders {
  ul > table {
    width: calc(100% - 20px);
    margin: 0 10px;
    background: $color2ndBgr;

    td, th {
      text-align: left;
      padding: 8px;
      vertical-align: middle;
      text-overflow: ellipsis;
      overflow: hidden;
      white-space: nowrap;

      &.time {
        width: 72px;
        text-align: right;
      }

      &.track-number {
        width: 42px;
      }

      &.artist {
        width: 23%;
      }

      &.album {
        width: 27%;
      }

      &.play {
        display: none;

        html.touchevents & {
          display: block;
          position: absolute;
          top: 8px;
          right: 4px;
        }
      }
    }
    th {
        color: $color2ndText;
        letter-spacing: 1px;
        text-transform: uppercase;
    }
  }
  ul {
    @media only screen and (max-width: 768px) {
      table, tbody, tr {
        display: block;
      }

      thead, tfoot {
        display: none;
      }

      tr {
        padding: 8px 32px 8px 4px;
        position: relative;
      }

      td {
        display: inline;
        padding: 0;
        vertical-align: bottom;
        white-space: normal;

        &.album, &.time, &.track-number {
          display: none;
        }

        &.artist {
          opacity: .5;
          font-size: .9rem;
          padding: 0 4px;
        }
      }
    }
  }
  div.folder {
    line-height: 2em;
    height: 2.2em;
    border-bottom: 1px solid $color2ndBgr;

    > span {
      cursor: pointer;
      display: inline-block;
    }  
    > span.folder {
      text-align: center;
      font-size: 1.3em;
      margin-left: 1em;

      a {
        color: #FFF;
        padding: 2px 5px;
        text-transform: uppercase;
        font-size: 0.7em;
      }     
      a:first-child {
        background: $colorOrange;
        border-radius: 5px 0 0 5px;
      }
      a:last-child {
        background: $colorGreen;
        border-radius: 0 5px 5px 0;
      }
    }
    > span.open::before {
      content: "\f0d7";
      width: 20px;
      height: 20px;
      line-height: 20px;
      text-align: center;
      color: $colorHighlight;
      margin-top: 2px;
      padding-top: 1px;
    }
    > span.close::before {
      content: "\f0da";
      width: 20px;
      height: 20px;
      line-height: 20px;
      text-align: center;
      padding-left: 2px;
    }
  }


  .sep {
    display: none;
    color: $color2ndText;

    .as-list & {
      display: inline;
    }
  }
}
</style>

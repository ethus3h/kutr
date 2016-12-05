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
           <tr is="song-item" v-for="item in subSongs" :song="item" ref="rows" @itemClicked="itemClicked" :key="item.id"></tr>
        </tbody>
      </table>
      <song-menu ref="contextMenu" :songs="selectedSongs"/>
    </ul>
  </li>
</template>

<script>
import { find, map, filter, forEach, invokeMap } from 'lodash'
import isMobile from 'ismobilejs'
import $ from 'jquery'
import Vue from 'vue'

import { pluralize, orderBy, event } from '../../utils'
import { queueStore, artistStore, sharedStore, folderStore, songStore } from '../../stores'
import { playback, download } from '../../services'
import songItem from './song-item.vue'
import songMenu from './song-menu.vue'

export default {
  name: 'folder-item',
  props: ['folder', 'level'],
  filters: { pluralize },
  components: { songItem, songMenu },

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
      selectedSongs: []
    }
  },

  computed: {
    isFolder() {
      return this.folder.children.length !== 0
    },
    
    hasSongs() {
      return find(this.folder.children, o => { return o.songId !== 0 }) !== undefined
    },

    subSongs() {
      return map(filter(this.folder.children, o => { return o.songId !== 0 }), o => songStore.byId(o.songId))
    },

    subSongsRecursive() {
      // Unfold the recursive array first (@todo make a nicer function here)
      var children = []
      function unfold(o) {
          forEach(o.children, i => { if (i.songId === 0) return unfold(i); children.push(songStore.byId(i.songId)) })
      }
      unfold(this.folder)
      return children
    },


    subFolders() {
      return filter(this.folder.children, o => { return o.songId === 0; })
    },
  },

  methods: {
    /**
     * Toggle this folder view
     */
    toggle(e) {
      // Need to close all siblings here first
      forEach(this.$parent.$children, comp => { if ("isOpen" in comp && comp.isOpen && comp.name !== this.name) comp.isOpen = false })
      this.isOpen = !this.isOpen
      if (this.isOpen) {
        Vue.nextTick(() => {
          // Scroll to ensure it's visible
          var $this = $(e.target)
          var container = $('#foldersContainer')
          var distance = $this.offset().top - container.offset().top
          if (Math.abs(distance) < container.height()) {
            // If the element is visible animate scrolling to it
            container.animate({ scrollTop: distance + container.scrollTop() })
          } else {
            // Element is not visible, so don't wait time animating, it's distracting, just fix the scrolling position so it fits directly on the toggled element
            container.scrollTop(distance + container.scrollTop())
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
        queueStore.queue(this.subSongs)
      } else {
        playback.queueAndPlay(orderBy(this.subSongs, 'track'))
      }
    },

    /**
     * Play all the songs found recursively in the current album in track order,
     * or queue them up if Ctrl/Cmd key is pressed.
     */
    playRecursive(e) {
      if (e.metaKey || e.ctrlKey) {
        queueStore.queue(this.subSongsRecursive)
      } else {
        playback.queueAndPlay(this.subSongsRecursive)
      }
    },

    /**
     * Get the song-item component that's associated with a song ID.
     *
     * @param  {String} id The song ID.
     *
     * @return {Object}  The Vue compoenent
     */
    getComponentBySongId (id) {
      return find(this.$refs.rows, { song: { id }})
    },

    /**
     * Capture A keydown event and select all if applicable.
     *
     * @param {Object} e The keydown event.
     */
    handleA (e) {
      if (!e.metaKey && !e.ctrlKey) {
        return
      }
      invokeMap(this.$refs.rows, 'select')
      this.gatherSelected()
    },

    /**
     * Gather all selected songs.
     *
     * @return {Array.<Object>} An array of Song objects
     */
    gatherSelected () {
      const selectedRows = filter(this.$refs.rows, { selected: true })
      const ids = map(selectedRows, row => row.song.id)
      this.selectedSongs = songStore.byIds(ids)
    },

    /**
     * Handle the click event on a row to perform selection.
     *
     * @param  {String} songId
     * @param  {Object} e
     */
    itemClicked (songId, e) {
      const row = this.getComponentBySongId(songId)
      // If we're on a touch device, or if Ctrl/Cmd key is pressed, just toggle selection.
      if (isMobile.any) {
        this.toggleRow(row)
        this.gatherSelected()
        return
      }
      if (e.ctrlKey || e.metaKey) {
        this.toggleRow(row)
      }
      if (e.button === 0) {
        if (!e.ctrlKey && !e.metaKey && !e.shiftKey) {
          this.clearSelection()
          this.toggleRow(row)
        }
        if (e.shiftKey && this.lastSelectedRow && this.lastSelectedRow.$el) {
          this.selectRowsBetweenIndexes([this.lastSelectedRow.$el.rowIndex, row.$el.rowIndex])
        }
      }
      this.gatherSelected()
    },

    /**
     * Toggle select/unslect a row.
     *
     * @param  {Object} row The song-item component
     */
    toggleRow (row) {
      row.toggleSelectedState()
      this.lastSelectedRow = row
    },

    selectRowsBetweenIndexes (indexes) {
      indexes.sort((a, b) => a - b)
      const rows = this.$refs.rows
      for (let i = indexes[0]; i <= indexes[1]; ++i) {
        this.getComponentBySongId(rows[i - 1].song.id).select()
      }
    },

    /**
     * Clear the current selection on this song list.
     */
    clearSelection () {
      invokeMap(this.$refs.rows, 'deselect')
      this.gatherSelected()
      this.$emit('folder-song:unselect');
    },

    /**
     * Clicked on a row with Ctrl pressed
     */
    openContextMenu(songId, e) {
      // If the user is right-clicking an unselected row,
      // clear the current selection and select it instead.
      const currentRow = this.getComponentBySongId(songId)
      if (!currentRow.selected) {
        this.clearSelection()
        currentRow.select()
        this.gatherSelected()
      }
      this.$nextTick(() => this.$refs.contextMenu.open(e.pageY, e.pageX))
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
    dragStart(songId, e) {
      // If the user is dragging an unselected row, clear the current selection.
      const currentRow = this.getComponentBySongId(songId)
      if (!currentRow.selected) {
        this.clearSelection()
        currentRow.select()
        this.gatherSelected()
      }
      this.$nextTick(() => {
        const songIds = map(this.selectedSongs, 'id')
        e.dataTransfer.setData('application/x-koel.text+plain', songIds)
        e.dataTransfer.effectAllowed = 'move'
        // Set a fancy drop image using our ghost element.
        const $ghost = $('#dragGhost').text(`${songIds.length} song${songIds.length === 1 ? '' : 's'}`)
        e.dataTransfer.setDragImage($ghost[0], 0, 0)
      })
    },

    /**
     * Add a "droppable" class and set the drop effect when other songs are dragged over a row.
     *
     * @param {String} songId
     * @param {Object} e The dragover event.
     */
    allowDrop (songId, e) {
        return false
    },

    /**
     * Remove the droppable state (and the styles) from a row.
     *
     * @param  {Object} e
     */
    removeDroppableState (e) {
    }

  },
  
  created () {
    event.on({
      /**
       * Listen to song:played event to do some logic.
       *
       * @param  {Object} song The current playing song.
       */
      'song:played': song => {
        // Scroll the item into view if it's lost into oblivion.
        const $wrapper = $(this.$refs.wrapper)
        const $row = $wrapper.find(`.song-item[data-song-id="${song.id}"]`)
        if (!$row.length) {
          return
        }
        if ($wrapper[0].getBoundingClientRect().top + $wrapper[0].getBoundingClientRect().height < $row[0].getBoundingClientRect().top) {
          $wrapper.scrollTop($wrapper.scrollTop() + $row.position().top)
        }
      },

      /**
       * Clears the current list's selection if the user has switched to another view.
       */
      'main-content-view:load': () => this.clearSelection(),

      /**
       * Listen to 'song:selection-clear' (often broadcasted from the direct parent)
       * to clear the selected songs.
       */
      'song:selection-clear': () => this.clearSelection()
    })
  }
}
</script>

<style lang="sass">
@import "../../../sass/partials/_vars.scss";
@import "../../../sass/partials/_mixins.scss";

@include artist-album-card();

div.folders {
  ul.menu {
    min-width: 144px;
    width: auto;
  }

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

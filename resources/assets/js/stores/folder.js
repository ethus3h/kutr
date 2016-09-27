import Vue from 'vue';
import { without, map, take, remove, orderBy, each, union } from 'lodash';

import { secondsToHis, event } from '../utils';
import { http, ls } from '../services';
import { sharedStore, favoriteStore, userStore, albumStore, artistStore, songStore } from '.';
import stub from '../stubs/song';

export const folderStore = {
  stub,
  albums: [],

  state: {
    /**
     * All folders in the store
     *
     * @type {Array}
     */
    root: { name: 'Media Library', songId: 0, children: [] },
    /**
     * Set to true if the current file hierarchy is loading
     * @type {Bool}
     */
    loading: true,
  },

  /**
   * Init the store.
   */
  init() {
    if (this.state.loading) {
      new Promise((resolve, reject) => {
        http.get('hierarchy', data => {
          // Server side sets up all the file hierarchy for this request
          this.state.root = data;
          this.state.loading = false;
      
          resolve(data);
          event.emit('koel:hierarchyready');    
        }, r => reject(r));
      });
    }
  },

  /**
   * Reset the store.
   */
  reset() {
    this.state = { root: { name: 'Media Library', songId: 0, children: [] }, loading: true };
  },
};

<template>
  <section id="settingsWrapper">
    <h1 class="heading">
      <span>Settings</span>
    </h1>

    <form @submit.prevent="confirmThenSave" class="main-scroll-wrap" @keydown="keyDown" @keyup="keyUp" @mousemove="checkAlt">
      <div class="form-row">
        <label for="inputSettingsPath">Media Path</label>
        <p class="help">
          The <em>absolute</em> path to the server directory containing your media.
          Koel will scan this directory for songs and extract any available information.<br>
          Scanning may take a while, especially if you have a lot of songs, so be patient.
        </p>

        <input type="text" v-model="state.settings.media_path" id="inputSettingsPath">
      </div>
    
      <div class="form-row">
        <label for="inputSettingsPublic">Media Path Sharing</label>
        <p class="help">
          If checked, the songs found in the path set above are visible to all users.
          Otherwise, only you will be able to see these songs.
        </p>

        <input type="checkbox" v-model="state.settings.media_path_public" id="inputSettingsPublic">
      </div>

      <div class="form-row">
        <button type="submit">Save settings</button>
        <button type="button" @click="scanLibrary" v-bind:class="{forced: isForced}" >Scan library {{forceSyncTxt}}</button>
      </div>
    </form>
    <sync-update ref="syncUpdate"></sync-update>
  </section>
</template>

<script>
import { settingStore, sharedStore } from '../../../stores'
import { parseValidationError, forceReloadWindow, showOverlay, hideOverlay, alerts } from '../../../utils'
import { http } from '../../../services';
import router from '../../../router';
import syncUpdate from '../../../components/modals/sync-update.vue';

export default {
  components: { syncUpdate },

  data() {
    return {
      forceSyncTxt: '',
      state: settingStore.state,
      sharedState: sharedStore.state
    }
  },

  computed: {
    /**
     * Check if the synchronization requires cleaning the library first
     *
     * @return {boolean}
    isForced() { 
      return this.forceSyncTxt != ''; 
    },

    /**
     * Determine if we should warn the user upon saving.
     *
     * @return {boolean}
     */
    shouldWarn () {
      // Warn the user if the media path is not empty and about to change.
      return this.sharedState.originalMediaPath &&
        this.sharedState.originalMediaPath !== this.state.settings.media_path.trim()
    }
  },

  methods: {
    confirmThenSave () {
      if (this.shouldWarn) {
        alerts.confirm('Warning: Changing the media path will essentially remove all existing data – songs, artists, \
          albums, favorites, everything – and empty your playlists! Sure you want to proceed?', this.save)
      } else {
        this.save()
      }
    },

    /**
     * Save the settings.
     */
    save () {
      showOverlay()

      settingStore.update().then(() => {
        // Make sure we're back to home first.
        router.go('home')
        forceReloadWindow()
      }).catch(r => {
        let msg = 'Unknown error.'

        if (r.status === 422) {
          msg = parseValidationError(r.responseJSON)[0]
        }

        hideOverlay()
        alerts.error(msg)
      })
    },

    /**
     * Scan the given path
     */
    scanLibrary() {
      this.$refs.syncUpdate.startSync(this.forceSyncTxt != '')
    },

    /**
     * Check if the user is pressing Alt key down (and react in that case)
     */
    checkAlt(e) {
      this.forceSyncTxt = e.altKey ? '(Clear library)' : ''
    },
    keyDown(e) {
      this.forceSyncTxt = e.keyCode == 18 ? '(Clear library)' : ''
    },
    keyUp(e) {
      if (e.keyCode == 18 && this.forceSyncTxt != '') 
        this.forceSyncTxt = ''
    }
  }
};
</script>

<style lang="sass">
@import "../../../../sass/partials/_vars.scss";
@import "../../../../sass/partials/_mixins.scss";

#settingsWrapper {
  input[type="text"] {
    width: 384px;
    margin-top: 12px;
  }
  button[type="button"].forced {
    background: $colorOrange;
  }

  @media only screen and (max-width : 667px) {
    input[type="text"] {
      width: 100%;
    }
  }
}
</style>

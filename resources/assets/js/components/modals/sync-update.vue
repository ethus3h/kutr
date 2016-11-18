<template>
  <div id="syncLibraryOverlay" v-if="shown">
    <div class="borderBox">
      <div id="progressBar"></div>
      <div class="info">
        <p class="title">Last parsed song<span v-if="failedSongs.length" @click="showErrors = !showErrors"></span></p>
        <p v-html="syncData.lastSong"></p>
        <ol class="error" v-show="showErrors"><li v-for="song in failedSongs">{{song}}</li></ol>
      </div>
      <button type="button" @click.prevent="stopSync">{{buttonText}}</a>
    <div>
  </div>
</template>

<script>
  import { every, filter } from 'lodash';
  import progressbar from 'progressbar.js';
  import { http } from '../../services';

  export default {

    data() {
      return {
        shown: false,
        loading: false,
        needsReload: false,
        showErrors: false,
        buttonText: 'Stop sync',
        failedSongs: [],
        progress: null,

        syncData: {
          lastSong: '<not started yet>',
          songsDone: 0,
          songsFailed: [],
          songsTotal: 1,
        },
      };
    },

    computed: {
      /**
       * Determine the total synchronization percent ratio.
       *
       * @return {Number}
       */
      syncRatio() {
        return this.syncData.songsDone / (this.syncData.songsTotal ? this.syncData.songsTotal : 0);
      },
    },

    methods: {
      stopSync() {
        this.shown = false;
        this.songsDone = 0;
        this.failedSongs = [];
        this.songsTotal = 1;
        if (this.buttonText == 'Done') {
          // Refresh the interface after syncing
          router.go('home');
          forceReloadWindow();          
        }
      },

      startSync(forceClean) {
        this.shown = true;
        this.$nextTick(function() {
                  this.progress = new progressbar.Circle("#progressBar", {
                                color: '#000',
                                // This has to be the same size as the maximum width to
                                // prevent clipping
                                strokeWidth: 4,
                                trailWidth: 1,
                                easing: 'easeInOut',
                                duration: 1400,
                                text: {
                                    value: 'Loading...',
                                    style: null,
                                    autoStyleContainer: false,
                                },
                                from: { color: '#840', width: 1 },
                                to: { color: '#ff8', width: 4 },
                                attachment: this,
                           
                                // Set default step function for all animate calls
                                step: function(state, circle, that) {
                                  // Let the stroke width increase with the completeness
                                  circle.path.setAttribute('stroke', state.color);
                                  circle.path.setAttribute('stroke-width', state.width);

                                  circle.setText(that.syncData.songsDone + '/' + that.syncData.songsTotal);
                                }
                              });
                this.getStatus(forceClean);
              });
      },

      /**
       * Right now, we poll the server for the current status.
       * The server parse a given amount of songs and then returns us a JSON.
       * We just repeat calling it until it's done.
       */
      getStatus(force) {
        this.loading = true;

        new Promise((resolve, reject) => {
           http.post('syncLibrary' + (force ? '/force' : ''), {'doneAlready' : this.syncData.songsDone}, data => resolve(data), r => reject(r));
        }).then(r => {
          this.loading = false;
          this.syncData = r;
          this.failedSongs = this.failedSongs.concat(this.syncData.songsFailed);
          this.progress.animate(this.syncRatio);
          if (this.syncData.songsFailed.length + this.syncData.songsDone < this.syncData.songsTotal) {
            this.getStatus(false);
          } else {
            this.buttonText = 'Done';
          }
        }).catch(r => {
          this.loading = false;
          // Should close now, but wait for it a bit
          this.stopSync();
        });
      },
    },
  };
</script>

<style lang="sass">
  @import "../../../sass/partials/_vars.scss";
  @import "../../../sass/partials/_mixins.scss";

  #syncLibraryOverlay {
    z-index: 9999;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, .7);
    overflow: auto;

    @include vertical-center();

    $borderRadius: 5px;

    > div.borderBox {
      width: 85%;
      max-width: 480px;
      border: 1px solid #323232;
      background: rgba(33, 33, 33, 0.8);
      border-radius: $borderRadius;
      display: flex;
      flex-wrap: wrap;

      #progressBar {
        margin: 20px auto;
        width: 50%;
        height: 150px;
        position: relative;
        align-self: center;
        max-width: 150px;

        div.progressbar-text {
          position: absolute;
          left: 50%;
          top: 50%;
          padding: 0px;
          margin: 0px;
          transform: translate(-50%, -50%);
          color: #840;
          font-family: Raleway, Helvetica, sans-serif;
          font-size: 22px;
          font-size: 1.7rem;
          font-size: 1.6vmax;
        }
      }

      > div.info {
        height: 100px;
        width: 300px;
        vertical-align: bottom;
        line-height: 2rem;
        align-self: center;
        padding-left: 5px;

        > p.title {
          font-weight: bold;
          margin-top: 32px;
          margin-top: 2.5rem;
          
          > span::before {
            content: "\f071";
            color: #fe0;
            font-family: fontAwesome;
            display: inline-block;
            float: right; 
            margin-right: 10px;
          }
        }
        > p {
          color: #fff;

          > em {
            color: #c74b00;
          }
        }

        ol.error {
          overflow: auto;
          margin-top: 5px;
          max-height: 5em;

          li {
            color: #f30;
            text-overflow: ellipsis;
            font-size: 70%;  
            line-height: 100%;
            margin-top: 5px;
          }
        }

      }

      > button {
        margin-bottom: 10px;
        margin-left: auto;
        margin-right: 10px;
        max-height: 42px;
        max-height: 3.4rem;
      }
    }
  }
</style>

<template>
  <div class="audio-player">
    <transition name="fade">
      <div
        v-if="
          state.playback === 'idle' ||
          state.playback === 'ended' ||
          state.playback === 'paused'
        "
        class="overlay"
        @click="togglePlayback"
      >
      </div>
    </transition>
    <audio
      controls
      ref="audio"
      class="player-audio k-block-type-audio-element"
      :loop="false"
      :muted="state.muted"
      preload="auto"
      @play="onPlay"
      @pause="onPause"
      @ended="onEnded"
      @click="togglePlayback"
    />
  </div>
</template>

<script>
import Hls from "hls.js";

export default {
  name: "AudioPlayer",
  props: {
    src: {
      type: String,
      required: true,
    }
  },
  data() {
    return {
      state: {
        loaded: false,
        playback: "idle",
        muted: false,
      },
      hls: null,
    };
  },
  mounted() {
    this.load();
  },
  beforeUnmount() {
    this.destroy();
  },
  methods: {
    load() {
      if (Hls.isSupported()) {
        this.hls = new Hls();
        this.hls.loadSource(this.src);
        this.hls.attachMedia(this.$refs.audio);
        this.hls.on(Hls.Events.MEDIA_ATTACHED, this.onLoad);
      } else {
        this.$refs.audio.src = this.src;
        this.$refs.audio.load();
        this.state.loaded = true;
      }
    },
    destroy() {
      if (this.hls) {
        this.hls.detachMedia();
        this.hls.destroy();
      }
    },
    play() {
      const playPromise = this.$refs.audio.play();
      if (playPromise !== null) {
        playPromise.catch(() => {
          this.pause();
        });
      }
    },
    pause() {
      this.$refs.audio.pause();
    },
    togglePlayback() {
      if (this.state.playback === "playing") {
        this.pause();
      } else {
        this.play();
      }
    },
    onLoad() {
      this.state.loaded = true;
    },
    onPlay() {
      this.state.playback = "playing";
    },
    onPause() {
      this.state.playback = "paused";
    },
    onEnded() {
      this.state.playback = "ended";
    },
  },
};
</script>

<style lang="postcss">
.audio-player {
  position: relative;
  display: flex;
}
audio {
  width: 100%;
  cursor: pointer;
}
</style>

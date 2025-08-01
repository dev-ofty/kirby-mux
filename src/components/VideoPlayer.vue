<template>
  <div class="video-player" :style="containerStyle">
    <div class="video-wrapper">
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
          <button class="overlay__inner">
            <svg
              width="10"
              height="13"
              viewBox="0 0 10 13"
              fill="currentColor"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path d="M0 0.388916L10 6.50003L0 12.6111V0.388916Z" />
            </svg>
          </button>
        </div>
      </transition>
      <video
        ref="video"
        class="player-video"
        :poster="posterUrl"
        :loop="false"
        :muted="state.muted"
        preload="auto"
        @play="onPlay"
        @pause="onPause"
        @ended="onEnded"
        @click="togglePlayback"
      />
    </div>
  </div>
</template>

<script>
import Hls from "hls.js";

export default {
  name: "VideoPlayer",
  props: {
    src: {
      type: String,
      required: true,
    },
    thumb: {
      type: Object,
      default: () => ({}),
    },
    aspectRatio: {
      type: String,
      default: null,
    },
    width: {
      type: [String, Number],
      default: null,
    },
    height: {
      type: [String, Number],
      default: null,
    },
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
  computed: {
    posterUrl() {
      return this.thumb?.src || "";
    },
    containerStyle() {
      // Try padding-bottom approach first for better browser support
      if (this.width && this.height) {
        const paddingBottom = (parseFloat(this.height) / parseFloat(this.width)) * 100;
        return {
          position: "relative",
          paddingBottom: `${paddingBottom}%`,
          height: 0,
        };
      } else if (this.aspectRatio) {
        // Use CSS aspect-ratio for modern browsers
        return {
          aspectRatio: this.aspectRatio,
        };
      }
      return {};
    },
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
        this.hls.attachMedia(this.$refs.video);
        this.hls.on(Hls.Events.MEDIA_ATTACHED, this.onLoad);
      } else {
        this.$refs.video.src = this.src;
        this.$refs.video.load();
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
      const playPromise = this.$refs.video.play();
      if (playPromise !== null) {
        playPromise.catch(() => {
          this.pause();
        });
      }
    },
    pause() {
      this.$refs.video.pause();
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
.video-player {
  display: block;
  width: 100%;
}

.video-wrapper {
  position: relative;
  width: 100%;
  height: 100%;
}

/* When using padding-bottom technique */
.video-player[style*="padding-bottom"] .video-wrapper {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

/* Ensure video fills container */
.video-player[style*="aspect-ratio"] video,
.video-player[style*="padding-bottom"] video {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.overlay {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 20;
}

.overlay__inner {
  width: 56px;
  height: 42px;
  padding-left: 3px;
  background: #000;
  border: 0;
  display: flex;
  color: #fff;
  align-items: center;
  justify-content: center;
  transition: width 0.15s ease-out, height 0.15s ease-out;
  cursor: pointer;
}

.overlay__inner svg {
  width: 14px;
  height: auto;
}

.overlay__inner:active {
  width: 53.333px;
  height: 40px;
}

video {
  width: 100%;
  height: 100%;
  cursor: pointer;
  display: block;
}
.k-block-container-type-mux-video {
  position: relative;
  z-index: 0;
}
.k-block-container-type-mux-video .k-block-figure-empty {
  height: 100%;
}
</style>

<template>
  <div class="video-player">
    <transition name="fade">
      <img
        v-if="!!thumb && state.playback === 'idle'"
        :src="thumb.src"
        :srcset="thumb.srcset"
        sizes="auto"
        class="thumb"
      />
    </transition>
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
      ref="videoRef"
      class="player-video"
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

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from 'vue'
import Hls from "hls.js"

// Props
const props = defineProps({
  src: {
    type: String,
    required: true,
  },
  thumb: {
    type: Object,
    default: () => ({}),
  },
})

// State
const videoRef = ref(null)
const state = reactive({
  loaded: false,
  playback: "idle",
  muted: false,
})
const hls = ref(null)

// Methods
const load = () => {
  if (Hls.isSupported()) {
    hls.value = new Hls()
    hls.value.loadSource(props.src)
    hls.value.attachMedia(videoRef.value)
    hls.value.on(Hls.Events.MEDIA_ATTACHED, onLoad)
  } else {
    videoRef.value.src = props.src
    videoRef.value.load()
    state.loaded = true
  }
}

const destroy = () => {
  if (hls.value) {
    hls.value.detachMedia()
    hls.value.destroy()
  }
}

const play = () => {
  const playPromise = videoRef.value.play()
  if (playPromise !== null && playPromise !== undefined) {
    playPromise.catch(() => {
      pause()
    })
  }
}

const pause = () => {
  videoRef.value.pause()
}

const togglePlayback = () => {
  if (state.playback === "playing") {
    pause()
  } else {
    play()
  }
}

const onLoad = () => {
  state.loaded = true
}

const onPlay = () => {
  state.playback = "playing"
}

const onPause = () => {
  state.playback = "paused"
}

const onEnded = () => {
  state.playback = "ended"
}

// Lifecycle
onMounted(() => {
  load()
})

onBeforeUnmount(() => {
  destroy()
})
</script>

<style lang="postcss">
.video-player {
  position: relative;
  display: flex;
}

.thumb {
  width: 100%;
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 10;
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
  cursor: pointer;
}
.k-block-container-type-mux-video {
  position: relative;
  z-index: 0;
}
.k-block-container-type-mux-video .k-block-figure-empty {
  height: 100%;
}
</style>

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
      ref="audioRef"
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

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from 'vue'
import Hls from "hls.js"

// Props
const props = defineProps({
  src: {
    type: String,
    required: true,
  }
})

// State
const audioRef = ref(null)
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
    hls.value.attachMedia(audioRef.value)
    hls.value.on(Hls.Events.MEDIA_ATTACHED, onLoad)
  } else {
    audioRef.value.src = props.src
    audioRef.value.load()
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
  const playPromise = audioRef.value.play()
  if (playPromise !== null && playPromise !== undefined) {
    playPromise.catch(() => {
      pause()
    })
  }
}

const pause = () => {
  audioRef.value.pause()
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
.audio-player {
  position: relative;
  display: flex;
}
audio {
  width: 100%;
  cursor: pointer;
}
</style>

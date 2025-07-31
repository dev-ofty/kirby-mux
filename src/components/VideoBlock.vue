<template>
  <k-block-figure
    :is-empty="!video.url"
    :caption="content.caption"
    empty-icon="image"
    empty-text="No file selected yet …"
    @open="open"
    @update="update"
  >
    <VideoPlayer v-if="src" :src="src" :thumb="thumb" />
  </k-block-figure>
</template>

<script setup>
import { ref, computed, watch, inject } from 'vue'
import VideoPlayer from "./VideoPlayer.vue"

// Props
const props = defineProps({
  content: {
    type: Object,
    required: true
  },
  open: {
    type: Function,
    required: true
  },
  update: {
    type: Function,
    required: true
  }
})

// Inject Kirby's context
const $api = inject('$api')

// State
const mux = ref(null)

// Computed
const video = computed(() => props.content.video?.[0] || {})
const id = computed(() => mux.value?.playback_ids?.[0]?.id)
const src = computed(() => {
  if (!id.value) return ""
  return `https://stream.mux.com/${id.value}.m3u8`
})
const time = computed(() => props.content.thumbnail || 0)
const thumb = computed(() => {
  if (!id.value) return ""
  const url = `https://image.mux.com/${id.value}/thumbnail.jpg?time=${time.value}`
  const srcset = [300, 600, 900, 1200, 1600]
  return {
    src: url,
    srcset: srcset.map((w) => `${url}&width=${w} ${w}w`).join(", "),
  }
})

// Watch video link changes
watch(
  () => video.value.link,
  async (link) => {
    if (link && $api) {
      try {
        const file = await $api.get(link)
        mux.value = JSON.parse(file.content.mux)
      } catch (error) {
        console.error('Failed to load video metadata:', error)
      }
    }
  },
  { immediate: true }
)
</script>

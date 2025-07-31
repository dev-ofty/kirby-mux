<template>
  <k-block-figure
    :is-empty="!audio.url"
    :caption="content.caption"
    empty-icon="image"
    empty-text="No file selected yet …"
    @open="open"
    @update="update"
  >
    <AudioPlayer v-if="src" :src="src" />
  </k-block-figure>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import AudioPlayer from "./AudioPlayer.vue"

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

// State
const mux = ref(null)

// Computed
const audio = computed(() => props.content.audio?.[0] || {})
const id = computed(() => mux.value?.playback_ids?.[0]?.id)
const src = computed(() => {
  if (!id.value) return ""
  return `https://stream.mux.com/${id.value}.m3u8`
})

// Watch audio link changes
watch(
  () => audio.value.link,
  async (link) => {
    if (link) {
      try {
        const file = await window.panel.api.get(link)
        mux.value = JSON.parse(file.content.mux)
      } catch (error) {
        console.error('Failed to load audio metadata:', error)
      }
    }
  },
  { immediate: true }
)
</script>

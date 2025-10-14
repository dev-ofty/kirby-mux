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

<script>
import AudioPlayer from "./AudioPlayer.vue";

export default {
  name: "AudioBlock",
  components: {
    AudioPlayer,
  },
  inject: ["$api"],
  props: {
    content: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      mux: null,
    };
  },
  computed: {
    audio() {
      return this.content.audio?.[0] || {};
    },
    id() {
      return this.mux?.playback_ids?.[0]?.id;
    },
    src() {
      if (!this.id) return "";
      return `https://stream.mux.com/${this.id}.m3u8`;
    }
  },
  watch: {
    "audio.link": {
      handler(link) {
        if (link && this.$api) {
          this.$api.get(link).then((file) => {
            // Guard against missing or invalid file data
            if (!file || !file.content) {
              console.error('Invalid file data received');
              return;
            }

            // Guard against missing or invalid mux data
            if (!file.content.mux) {
              console.error('Missing mux data in file content');
              return;
            }

            try {
              const parsedMux = JSON.parse(file.content.mux);
              // Validate that parsed data has expected structure
              if (!parsedMux || typeof parsedMux !== 'object') {
                console.error('Invalid mux data structure');
                return;
              }
              this.mux = parsedMux;
            } catch (error) {
              console.error('Failed to parse mux JSON:', error);
            }
          }).catch((error) => {
            console.error('Failed to load audio metadata:', error);
          });
        }
      },
      immediate: true,
    },
  },
};
</script>

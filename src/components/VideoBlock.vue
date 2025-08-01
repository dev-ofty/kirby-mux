<template>
  <k-block-figure
    :is-empty="!video.url"
    :caption="content.caption"
    empty-icon="image"
    empty-text="No file selected yet …"
    @open="open"
    @update="update"
  >
    <VideoPlayer
      v-if="src"
      :src="src"
      :thumb="thumb"
      :aspect-ratio="aspectRatio"
      :width="videoWidth"
      :height="videoHeight"
    />
  </k-block-figure>
</template>

<script>
import VideoPlayer from "./VideoPlayer.vue";

export default {
  name: "VideoBlock",
  components: {
    VideoPlayer,
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
      fileData: null,
    };
  },
  computed: {
    video() {
      return this.content.video?.[0] || {};
    },
    id() {
      return this.mux?.playback_ids?.[0]?.id;
    },
    src() {
      if (!this.id) return "";
      return `https://stream.mux.com/${this.id}.m3u8`;
    },
    time() {
      return this.content.thumbnail || 0;
    },
    thumb() {
      if (!this.id) return "";
      const url = `https://image.mux.com/${this.id}/thumbnail.jpg?time=${this.time}`;
      const srcset = [300, 600, 900, 1200, 1600];
      return {
        src: url,
        srcset: srcset.map((w) => `${url}&width=${w} ${w}w`).join(", "),
      };
    },
    videoWidth() {
      // Kirby converts field names to lowercase
      return this.fileData?.content?.resolutionx || this.mux?.max_stored_resolution?.split('x')?.[0] || null;
    },
    videoHeight() {
      // Kirby converts field names to lowercase
      return this.fileData?.content?.resolutiony || this.mux?.max_stored_resolution?.split('x')?.[1] || null;
    },
    aspectRatio() {
      // Use the aspect ratio from file metadata if available
      if (this.fileData?.content?.resaspect) {
        return this.fileData.content.resaspect;
      }
      // Otherwise calculate from dimensions
      if (this.videoWidth && this.videoHeight) {
        return `${this.videoWidth}/${this.videoHeight}`;
      }
      // Return null if no dimension data available
      return null;
    },
  },
  watch: {
    "video.link": {
      handler(link) {
        if (link && this.$api) {
          this.$api.get(link).then((file) => {
            this.fileData = file;
            this.mux = JSON.parse(file.content.mux);
          }).catch((error) => {
            console.error('Failed to load video metadata:', error);
          });
        }
      },
      immediate: true,
    },
  },
};
</script>

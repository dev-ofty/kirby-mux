import VideoBlock from "./components/VideoBlock.vue";
import AudioBlock from "./components/AudioBlock.vue";

window.panel.plugin("robinscholz/kirby-mux", {
  blocks: {
    "mux-video": VideoBlock,
    "mux-audio": AudioBlock,
  },
});

import { defineStore } from 'pinia';
import { ref } from 'vue';

const MAX_IMAGES = 13;

export const useSponsorStore = defineStore('sponsors', () => {
  const images     = ref([]);
  const userImages = ref([]);

  function load() {
    // In WP mode: sponsor images come from scvConfig, not localStorage
    const wpImages = (typeof window !== 'undefined' && Array.isArray(window.scvConfig?.sponsorImages))
      ? window.scvConfig.sponsorImages
      : [];

    userImages.value = [...wpImages];
    images.value     = [...wpImages];
  }

  return { images, userImages, load };
});

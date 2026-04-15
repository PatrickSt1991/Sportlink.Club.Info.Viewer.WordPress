<template>
  <div class="sponsor-bar-container" v-if="isVisible">
    <div class="sponsor-bar">
      <div class="sponsor-images">
        <img
          v-for="(image, index) in sponsorImages"
          :key="index"
          :src="image"
          class="sponsor-image"
          alt=""
        />
      </div>
    </div>
    <p class="copyright">
      <a href="https://github.com/PatrickSt1991" target="_blank">
        © {{ year }} Patrick Stel. Vrijgegeven onder de MIT-licentie.
      </a>
    </p>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { USER_CONFIG } from '@/config';

const year         = ref(new Date().getFullYear());
const sponsorImages = ref([]);

const isVisible = computed(() => USER_CONFIG.value.activeSponsors && sponsorImages.value.length > 0);

onMounted(() => {
  // Load sponsor images from WP config (passed via wp_localize_script)
  const wpImages = window.scvConfig?.sponsorImages;
  if (Array.isArray(wpImages) && wpImages.length > 0) {
    sponsorImages.value = wpImages;
  }
});
</script>

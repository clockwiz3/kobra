<script setup lang="ts">
// Лого Kobra: иконка Tabler Heart (@vicons/tabler), ЗАЛИТАЯ фирменным градиентом
// (accent → accent-2), без подложки-квадрата. Градиент темо-реактивный (CSS-переменные):
// меняется вместе с темой/гаммой. Заливка через ссылку fill: url(#kobra-heart-grad).
// В пиксельной теме вместо сердца — пиксельная кобра.
import { Heart } from '@vicons/tabler'
import KobraSprite from './KobraSprite.vue'
import { useTheme } from '@/composables/useTheme'

const { theme } = useTheme()
</script>

<template>
  <div class="brandmark">
    <!-- defs с градиентом — на него ссылается fill силуэта сердца -->
    <svg class="grad-def" width="0" height="0" aria-hidden="true">
      <defs>
        <linearGradient id="kobra-heart-grad" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="var(--accent)" />
          <stop offset="100%" stop-color="var(--accent-2)" />
        </linearGradient>
      </defs>
    </svg>
    <KobraSprite v-if="theme === 'pixel'" :size="28" label="" aria-hidden="true" />
    <Heart v-else class="brand-heart" />
    Kobra
  </div>
</template>

<style scoped>
.grad-def { position: absolute; width: 0; height: 0; }
.brand-heart { width: 28px; height: 28px; flex: none; }
/* залить силуэт сердца градиентом и убрать контурную обводку Tabler-иконки */
.brand-heart :deep(path) { fill: url(#kobra-heart-grad); stroke: none; }
</style>

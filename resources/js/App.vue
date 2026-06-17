<script setup lang="ts">
// Корень SPA. Тема/гамма управляются useTheme (см. main.ts) на #app.ds-root.
// ConfirmRoot — singleton-хост императивного useConfirm() (модалки подтверждения).
import ConfirmRoot from './components/ui/ConfirmRoot.vue'
</script>

<template>
  <router-view />
  <ConfirmRoot />
</template>

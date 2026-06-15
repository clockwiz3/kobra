<script setup lang="ts">
import ThemeSwitch from '@/components/ui/ThemeSwitch.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
</script>

<template>
  <header class="topbar">
    <div class="wrap row">
      <div class="brandmark"><span class="dot">&hearts;</span> Cuploid</div>
      <ThemeSwitch />
    </div>
  </header>

  <main class="hero">
    <div class="wrap" style="position: relative; z-index: 1">
      <span class="kicker">&hearts; Приватный трекер дел — для двоих</span>
      <h1>Делать дела <span class="grad">вместе</span><br />намного нежнее.</h1>
      <p class="lead">
        Каркас Cuploid готов: Laravel API + Vue 3 + дизайн-система подключены.
        Переключи тему вверху — dark / light / pixel.
      </p>
      <div class="cta">
        <BaseButton variant="primary">Создать пространство &hearts;</BaseButton>
        <BaseButton variant="secondary">Узнать больше</BaseButton>
      </div>
    </div>
  </main>
</template>

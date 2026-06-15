<script setup lang="ts">
import ThemeSwitch from '@/components/ui/ThemeSwitch.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import WaitlistForm from '@/components/WaitlistForm.vue'
import ParticlesTeaser from '@/components/ParticlesTeaser.vue'

const features = [
  { t: 'Канбан вдвоём', d: 'Общие дела пары — карточки и статусы, всё синхронно у обоих.' },
  { t: 'Вишлисты', d: 'Списки желаний со скрытой бронью подарков.' },
  { t: 'Тёплая геймификация', d: 'Общий стрик, ачивки и пасхалки — без рейтингов и сравнений.' },
  { t: 'Кастомизация', d: 'Темы, анимации, частицы — ваше пространство, ваш стиль.' },
]
</script>

<template>
  <header class="topbar">
    <div class="wrap row">
      <div class="brandmark"><span class="dot">&hearts;</span> Cuploid</div>
      <ThemeSwitch />
    </div>
  </header>

  <main class="hero">
    <ParticlesTeaser />
    <div class="wrap" style="position: relative; z-index: 1">
      <span class="kicker">&hearts; Приватный трекер дел — для двоих</span>
      <h1>Делать дела <span class="grad">вместе</span><br />намного нежнее.</h1>
      <p class="lead">
        Cuploid — закрытое пространство для пары: общие задачи, вишлисты и тёплая геймификация.
        Запускаемся скоро — занимай место.
      </p>
      <div style="margin-top: 28px"><WaitlistForm /></div>
      <p class="label-strip">Только вы вдвоём. Никаких лент, рейтингов и чужих глаз.</p>
    </div>
  </main>

  <section class="block">
    <div class="wrap">
      <div class="eyebrow">Что внутри</div>
      <h2 class="sec-title">Ваше общее пространство</h2>
      <div class="col2" style="margin-top: 28px">
        <BaseCard v-for="f in features" :key="f.t">
          <h3 class="sub-h" style="margin: 0 0 6px">{{ f.t }}</h3>
          <p style="color: var(--text-muted); margin: 0">{{ f.d }}</p>
        </BaseCard>
      </div>
    </div>
  </section>

  <footer class="block" style="text-align: center">
    <div class="wrap">
      <div class="brandmark" style="justify-content: center"><span class="dot">&hearts;</span> Cuploid</div>
      <p class="sec-sub" style="margin: 12px auto 0">Закрытый запуск · сделано с любовью для двоих</p>
    </div>
  </footer>
</template>

<script setup lang="ts">
/**
 * BaseColorPicker — пикер «рамки» карточки (appearance). Зерно Customization Engine.
 * Модель — CardFrame: заливка solid/gradient (настоящий HSV-пикер ColorSpectrum), стиль/толщина,
 * флаг `animated` (мерцание — работает и на сплошном цвете, и на градиенте). «Аврора» — быстрый
 * пресет (красивый градиент + мерцание). Конвенции ui/: defineModel, токены тем, a11y, scoped-геометрия.
 */
import { computed, ref } from 'vue'
import ColorSpectrum from './ColorSpectrum.vue'
import { frameStyle } from '@/composables/useCardFrame'
import type { BorderStyle, CardFrame, FramePaint } from '@/types/board'
import { useLogger } from '@/composables/useLogger'

const log = useLogger('BaseColorPicker')

const model = defineModel<CardFrame>({ required: true })

// Быстрые свотчи (приятные тона) — мгновенно задают активный цвет; тонкая настройка — спектром.
const SWATCHES = ['#e84aa6', '#ff5f6d', '#ffa14a', '#f4b53c', '#6dff8f', '#37d9bc', '#5b8cff', '#7c5cfc', '#c86bff', '#ffffff']
const STYLES: Array<{ value: BorderStyle; label: string }> = [
  { value: 'solid', label: 'Сплошная' },
  { value: 'dashed', label: 'Пунктир' },
  { value: 'dotted', label: 'Точки' },
]
const WIDTHS: Array<1 | 2 | 3> = [1, 2, 3]

function patch(part: Partial<CardFrame>): void {
  model.value = { ...model.value, ...part }
  log.debug('patch', { keys: Object.keys(part) })
}

const paint = computed<FramePaint>(() => model.value.paint)
const primaryColor = computed(() => (paint.value.kind === 'solid' ? paint.value.color : paint.value.from))

// Активный стоп редактирования градиента (от/до).
const activeStop = ref<'from' | 'to'>('from')

function setKind(kind: FramePaint['kind']): void {
  if (paint.value.kind === kind) return
  if (kind === 'solid') {
    patch({ paint: { kind: 'solid', color: primaryColor.value } })
  } else {
    const to = paint.value.kind === 'gradient' ? paint.value.to : '#9b6bff'
    patch({ paint: { kind: 'gradient', from: primaryColor.value, to, angle: 135 } })
    activeStop.value = 'from'
  }
}
function setGradient(part: Partial<{ from: string; to: string; angle: number }>): void {
  const cur = paint.value.kind === 'gradient' ? paint.value : { from: primaryColor.value, to: '#9b6bff', angle: 135 }
  patch({ paint: { kind: 'gradient', ...cur, ...part } })
}

// Цвет, который сейчас редактирует спектр: сплошной — единственный, градиент — активный стоп.
const activeColor = computed<string>({
  get: () => (paint.value.kind === 'solid' ? paint.value.color : paint.value[activeStop.value]),
  set: (c) => {
    if (paint.value.kind === 'solid') patch({ paint: { kind: 'solid', color: c } })
    else setGradient({ [activeStop.value]: c })
  },
})

function toggleAnimated(): void { patch({ animated: !model.value.animated }) }

const preview = computed(() => frameStyle(model.value, 'var(--surface-2)'))
const gradientBar = computed(() =>
  paint.value.kind === 'gradient' ? `linear-gradient(${paint.value.angle}deg, ${paint.value.from}, ${paint.value.to})` : '',
)
</script>

<template>
  <div class="cpick">
    <!-- Тип заливки -->
    <div class="cpick__row">
      <span class="cpick__label">Заливка</span>
      <div class="cpick__seg" role="group" aria-label="Тип заливки">
        <button type="button" :class="{ 'is-on': paint.kind === 'solid' }" :aria-pressed="paint.kind === 'solid'" @click="setKind('solid')">Цвет</button>
        <button type="button" :class="{ 'is-on': paint.kind === 'gradient' }" :aria-pressed="paint.kind === 'gradient'" @click="setKind('gradient')">Градиент</button>
      </div>
    </div>

    <!-- Градиент: какой стоп редактируем + угол + полоса-превью -->
    <template v-if="paint.kind === 'gradient'">
      <div class="cpick__row">
        <span class="cpick__label">Точка градиента</span>
        <div class="cpick__seg" role="group" aria-label="Стоп градиента">
          <button type="button" :class="{ 'is-on': activeStop === 'from' }" :aria-pressed="activeStop === 'from'" @click="activeStop = 'from'">От</button>
          <button type="button" :class="{ 'is-on': activeStop === 'to' }" :aria-pressed="activeStop === 'to'" @click="activeStop = 'to'">До</button>
        </div>
        <div class="cpick__bar" :style="{ background: gradientBar }" aria-hidden="true" />
      </div>
      <div class="cpick__row">
        <span class="cpick__label">Угол</span>
        <div class="cpick__angle">
          <input type="range" min="0" max="360" step="5" :value="paint.angle" aria-label="Угол градиента" @input="setGradient({ angle: Number(($event.target as HTMLInputElement).value) })" />
          <span class="cpick__deg">{{ paint.angle }}°</span>
        </div>
      </div>
    </template>

    <!-- Настоящий HSV-пикер активного цвета -->
    <div class="cpick__row">
      <span class="cpick__label">{{ paint.kind === 'gradient' ? (activeStop === 'from' ? 'Цвет «От»' : 'Цвет «До»') : 'Цвет' }}</span>
      <ColorSpectrum v-model="activeColor" />
      <div class="cpick__swatches">
        <button
          v-for="c in SWATCHES"
          :key="c"
          type="button"
          class="cpick__swatch"
          :class="{ 'is-on': activeColor.toLowerCase() === c.toLowerCase() }"
          :style="{ background: c }"
          :aria-label="`Цвет ${c}`"
          @click="activeColor = c"
        />
      </div>
    </div>

    <!-- Стиль рамки -->
    <div class="cpick__row">
      <span class="cpick__label">Стиль</span>
      <div class="cpick__seg" role="group" aria-label="Стиль рамки">
        <button v-for="st in STYLES" :key="st.value" type="button" :class="{ 'is-on': model.style === st.value }" :aria-pressed="model.style === st.value" @click="patch({ style: st.value })">{{ st.label }}</button>
      </div>
    </div>

    <!-- Толщина -->
    <div class="cpick__row">
      <span class="cpick__label">Толщина</span>
      <div class="cpick__seg" role="group" aria-label="Толщина рамки">
        <button v-for="w in WIDTHS" :key="w" type="button" :class="{ 'is-on': model.width === w }" :aria-pressed="model.width === w" @click="patch({ width: w })">{{ w }}px</button>
      </div>
    </div>

    <!-- Эффект: мерцание (работает на любом цвете/градиенте) -->
    <div class="cpick__row">
      <span class="cpick__label">Эффект</span>
      <div class="cpick__chips">
        <button type="button" class="cpick__chip" :class="{ 'is-on': model.animated }" :aria-pressed="model.animated" @click="toggleAnimated">✨ Мерцание</button>
      </div>
    </div>

    <!-- Живое превью -->
    <div class="cpick__preview" :class="preview.class" :style="preview.style" aria-hidden="true">
      <span class="cpick__preview-title">Пример карточки</span>
      <span class="cpick__preview-chip">♥ Вместе</span>
    </div>
  </div>
</template>

<style scoped>
.cpick { display: flex; flex-direction: column; gap: 16px; }
.cpick__row { display: flex; flex-direction: column; gap: 8px; }
.cpick__label { font-size: 13px; color: var(--text-muted); font-weight: 500; }

.cpick__chips { display: flex; flex-wrap: wrap; gap: 8px; }
.cpick__chip,
.cpick__seg button {
  padding: 7px 12px; font-size: 13px; font-family: var(--font-body);
  color: var(--text); background: var(--surface-2);
  border: 1px solid var(--border-strong); border-radius: var(--r-pill);
  cursor: pointer; transition: background .14s var(--ease, ease), border-color .14s var(--ease, ease);
}
.cpick__seg { display: inline-flex; gap: 6px; }
.cpick__seg button { border-radius: var(--r-sm); }
.cpick__chip:hover,
.cpick__seg button:hover { background: color-mix(in srgb, var(--accent) 10%, var(--surface-2)); }
.cpick__chip.is-on,
.cpick__seg button.is-on {
  background: color-mix(in srgb, var(--accent) 18%, var(--surface-2));
  border-color: var(--accent); color: var(--text);
}
[data-theme="pixel"] .cpick__chip,
[data-theme="pixel"] .cpick__seg button { border-width: 2px; border-radius: 0; }

/* Полоса-превью градиента. */
.cpick__bar { height: 14px; border-radius: var(--r-pill); border: 1px solid var(--border-strong); }
[data-theme="pixel"] .cpick__bar { border-radius: 0; }

/* Быстрые свотчи. */
.cpick__swatches { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.cpick__swatch {
  width: 24px; height: 24px; padding: 0; border-radius: 50%;
  border: 2px solid transparent; box-shadow: 0 0 0 1px var(--border-strong);
  cursor: pointer; transition: transform .12s var(--ease, ease);
}
.cpick__swatch:hover { transform: scale(1.1); }
.cpick__swatch.is-on { box-shadow: 0 0 0 2px var(--accent); }
[data-theme="pixel"] .cpick__swatch { border-radius: 0; }

/* Слайдер угла. */
.cpick__angle { display: flex; align-items: center; gap: 12px; }
.cpick__angle input[type="range"] { flex: 1; min-width: 0; accent-color: var(--accent); }
.cpick__deg { font-size: 13px; color: var(--text-muted); min-width: 38px; text-align: right; font-variant-numeric: tabular-nums; }

/* Превью рамки — мини-карточка с фоном .task. */
.cpick__preview {
  margin-top: 2px; display: flex; align-items: center; justify-content: space-between; gap: 10px;
  background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--r-md);
  padding: 14px; box-shadow: var(--inner-glow);
}
.cpick__preview-title { font-weight: 500; font-size: 13px; color: var(--text); }
.cpick__preview-chip {
  font-size: 12px; padding: 3px 9px; border-radius: var(--r-pill);
  background: color-mix(in srgb, var(--accent) 16%, transparent); color: var(--text);
}
</style>

<script setup lang="ts">
/**
 * ColorSpectrum — настоящий HSV-колорпикер: квадрат насыщенность/яркость + ползунок оттенка +
 * hex-поле. Модель — hex-строка '#rrggbb'. Используется в BaseColorPicker (сплошной цвет и стопы
 * градиента). Конвенции ui/: defineModel, typed, a11y, scoped-геометрия, токены тем.
 */
import { ref, computed, watch } from 'vue'

const model = defineModel<string>({ required: true })

// — Конвертации цвета —
function clamp(n: number, min: number, max: number): number { return Math.min(max, Math.max(min, n)) }

function hexToRgb(hex: string): { r: number; g: number; b: number } {
  const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim())
  if (!m) return { r: 232, g: 74, b: 166 }
  const n = parseInt(m[1], 16)
  return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 }
}
function rgbToHex(r: number, g: number, b: number): string {
  const h = (v: number) => clamp(Math.round(v), 0, 255).toString(16).padStart(2, '0')
  return `#${h(r)}${h(g)}${h(b)}`
}
function rgbToHsv(r: number, g: number, b: number): { h: number; s: number; v: number } {
  r /= 255; g /= 255; b /= 255
  const max = Math.max(r, g, b), min = Math.min(r, g, b), d = max - min
  let h = 0
  if (d !== 0) {
    if (max === r) h = ((g - b) / d) % 6
    else if (max === g) h = (b - r) / d + 2
    else h = (r - g) / d + 4
    h *= 60
    if (h < 0) h += 360
  }
  return { h, s: max === 0 ? 0 : (d / max) * 100, v: max * 100 }
}
function hsvToRgb(h: number, s: number, v: number): { r: number; g: number; b: number } {
  s /= 100; v /= 100
  const c = v * s, x = c * (1 - Math.abs(((h / 60) % 2) - 1)), m = v - c
  let r = 0, g = 0, b = 0
  if (h < 60) { r = c; g = x } else if (h < 120) { r = x; g = c } else if (h < 180) { g = c; b = x }
  else if (h < 240) { g = x; b = c } else if (h < 300) { r = x; b = c } else { r = c; b = x }
  return { r: (r + m) * 255, g: (g + m) * 255, b: (b + m) * 255 }
}

// — Состояние HSV (источник правды при взаимодействии) —
const h = ref(330)
const s = ref(70)
const v = ref(90)

// Синхронизация из модели, когда она меняется ИЗВНЕ (свотч/градиент-стоп переключили).
function syncFromModel(hex: string): void {
  const { r, g, b } = hexToRgb(hex)
  const hsv = rgbToHsv(r, g, b)
  // Не сбрасываем оттенок при s=0/v=0 (чёрный/белый), чтобы ползунок не прыгал.
  if (hsv.s > 0) h.value = hsv.h
  s.value = hsv.s
  v.value = hsv.v
}
syncFromModel(model.value)
watch(model, (hex) => { if (hex.toLowerCase() !== currentHex.value.toLowerCase()) syncFromModel(hex) })

const currentHex = computed(() => {
  const { r, g, b } = hsvToRgb(h.value, s.value, v.value)
  return rgbToHex(r, g, b)
})

function emit(): void { model.value = currentHex.value }

// Фон квадрата S/V — чистый оттенок.
const hueColor = computed(() => {
  const { r, g, b } = hsvToRgb(h.value, 100, 100)
  return rgbToHex(r, g, b)
})

// — Перетаскивание по квадрату S/V —
const area = ref<HTMLElement | null>(null)
function updateFromPointer(e: PointerEvent): void {
  const el = area.value
  if (!el) return
  const r = el.getBoundingClientRect()
  s.value = clamp(((e.clientX - r.left) / r.width) * 100, 0, 100)
  v.value = clamp((1 - (e.clientY - r.top) / r.height) * 100, 0, 100)
  emit()
}
function onAreaDown(e: PointerEvent): void {
  ;(e.target as HTMLElement).setPointerCapture(e.pointerId)
  updateFromPointer(e)
}
function onAreaMove(e: PointerEvent): void {
  if (e.buttons === 0) return // тащим только при зажатой кнопке
  updateFromPointer(e)
}

function onHue(e: Event): void { h.value = Number((e.target as HTMLInputElement).value); emit() }
function onHex(e: Event): void {
  const val = (e.target as HTMLInputElement).value
  if (/^#?[0-9a-f]{6}$/i.test(val.trim())) { model.value = val.trim().startsWith('#') ? val.trim() : `#${val.trim()}` }
}
</script>

<template>
  <div class="cs">
    <!-- Квадрат насыщенность(X)/яркость(Y) поверх чистого оттенка -->
    <div
      ref="area"
      class="cs__area"
      :style="{ background: `linear-gradient(to top, #000, transparent), linear-gradient(to right, #fff, ${hueColor})` }"
      role="slider"
      aria-label="Насыщенность и яркость"
      :aria-valuetext="currentHex"
      tabindex="0"
      @pointerdown="onAreaDown"
      @pointermove="onAreaMove"
    >
      <span class="cs__thumb" :style="{ left: s + '%', top: 100 - v + '%', background: currentHex }" />
    </div>

    <!-- Оттенок -->
    <input class="cs__hue" type="range" min="0" max="360" :value="h" aria-label="Оттенок" @input="onHue" />

    <!-- Hex -->
    <div class="cs__hex">
      <span class="cs__chip" :style="{ background: currentHex }" />
      <input class="cs__hexinput" type="text" :value="currentHex" maxlength="7" aria-label="HEX-код цвета" spellcheck="false" @change="onHex" />
    </div>
  </div>
</template>

<style scoped>
.cs { display: flex; flex-direction: column; gap: 10px; }

.cs__area {
  position: relative; width: 100%; height: 132px;
  border-radius: var(--r-md); border: 1px solid var(--border-strong);
  cursor: crosshair; touch-action: none;
}
[data-theme="pixel"] .cs__area { border-radius: 0; border-width: 2px; }
.cs__thumb {
  position: absolute; width: 14px; height: 14px; border-radius: 50%;
  transform: translate(-50%, -50%); border: 2px solid #fff;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, .45); pointer-events: none;
}

/* Ползунок оттенка с радужным треком. */
.cs__hue {
  width: 100%; height: 14px; border-radius: var(--r-pill); appearance: none; -webkit-appearance: none;
  background: linear-gradient(to right, #f00 0%, #ff0 17%, #0f0 33%, #0ff 50%, #00f 67%, #f0f 83%, #f00 100%);
  cursor: pointer;
}
.cs__hue::-webkit-slider-thumb {
  -webkit-appearance: none; width: 16px; height: 16px; border-radius: 50%;
  background: #fff; border: 2px solid rgba(0, 0, 0, .35); cursor: pointer;
}
.cs__hue::-moz-range-thumb { width: 16px; height: 16px; border-radius: 50%; background: #fff; border: 2px solid rgba(0, 0, 0, .35); cursor: pointer; }
[data-theme="pixel"] .cs__hue { border-radius: 0; }

.cs__hex { display: flex; align-items: center; gap: 8px; }
.cs__chip { width: 26px; height: 26px; border-radius: var(--r-sm); border: 1px solid var(--border-strong); flex: none; }
[data-theme="pixel"] .cs__chip { border-radius: 0; }
.cs__hexinput {
  flex: 1; min-width: 0; padding: 8px 10px; font-family: var(--font-mono, monospace); font-size: 13px;
  color: var(--text); background: var(--surface-2); border: 1px solid var(--border-strong); border-radius: var(--r-sm);
  text-transform: uppercase;
}
[data-theme="pixel"] .cs__hexinput { border-radius: 0; border-width: 2px; }
</style>

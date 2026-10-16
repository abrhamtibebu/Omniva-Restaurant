<script setup lang="ts">
const props = withDefaults(defineProps<{
  items: Array<{ label: string, value: number, color?: string }>
  size?: number
  emptyLabel?: string
}>(), {
  size: 160,
  emptyLabel: 'Nothing pending',
})

const palette = ['#2481cc', '#0d9488', '#d97706', '#dc2626', '#7c3aed', '#059669']
const total = computed(() => props.items.reduce((sum, i) => sum + i.value, 0))

const slices = computed(() => {
  const t = total.value || 1
  let angle = -90
  return props.items.map((item, index) => {
    const sweep = (item.value / t) * 360
    const start = angle
    angle += sweep
    return {
      ...item,
      color: item.color || palette[index % palette.length],
      start,
      sweep,
    }
  })
})

function arc(cx: number, cy: number, r: number, start: number, sweep: number): string {
  if (sweep <= 0) return ''
  if (sweep >= 359.99) {
    // Full circle as two arcs
    const d1 = polar(cx, cy, r, start)
    const d2 = polar(cx, cy, r, start + 180)
    return `M ${d1.x} ${d1.y} A ${r} ${r} 0 1 1 ${d2.x} ${d2.y} A ${r} ${r} 0 1 1 ${d1.x} ${d1.y}`
  }
  const large = sweep > 180 ? 1 : 0
  const from = polar(cx, cy, r, start)
  const to = polar(cx, cy, r, start + sweep)
  return `M ${cx} ${cy} L ${from.x} ${from.y} A ${r} ${r} 0 ${large} 1 ${to.x} ${to.y} Z`
}

function polar(cx: number, cy: number, r: number, deg: number) {
  const rad = (deg * Math.PI) / 180
  return { x: cx + r * Math.cos(rad), y: cy + r * Math.sin(rad) }
}
</script>

<template>
  <div class="flex flex-col items-center gap-3 sm:flex-row sm:items-start">
    <div v-if="total === 0" class="flex items-center justify-center text-sm text-slate-500" :style="{ width: `${size}px`, height: `${size}px` }">
      {{ emptyLabel }}
    </div>
    <svg v-else :width="size" :height="size" :viewBox="`0 0 ${size} ${size}`" class="shrink-0">
      <path
        v-for="slice in slices"
        :key="slice.label"
        :d="arc(size / 2, size / 2, size * 0.38, slice.start, slice.sweep)"
        :fill="slice.color"
        opacity="0.95"
      />
      <circle :cx="size / 2" :cy="size / 2" :r="size * 0.22" class="fill-[var(--tg-surface,white)]" />
      <text
        :x="size / 2"
        :y="size / 2 - 2"
        text-anchor="middle"
        class="fill-current text-lg font-bold"
      >
        {{ total }}
      </text>
      <text
        :x="size / 2"
        :y="size / 2 + 14"
        text-anchor="middle"
        class="fill-current text-[9px] opacity-60"
      >
        open
      </text>
    </svg>
    <ul class="w-full space-y-1.5 text-sm">
      <li v-for="slice in slices" :key="slice.label" class="flex items-center justify-between gap-2">
        <span class="flex min-w-0 items-center gap-2">
          <span class="size-2.5 shrink-0 rounded-full" :style="{ background: slice.color }" />
          <span class="truncate opacity-80">{{ slice.label }}</span>
        </span>
        <span class="font-semibold tabular-nums">{{ slice.value }}</span>
      </li>
    </ul>
  </div>
</template>

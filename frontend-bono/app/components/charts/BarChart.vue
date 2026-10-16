<script setup lang="ts">
const props = withDefaults(defineProps<{
  items: Array<{ label: string, value: number, color?: string }>
  height?: number
  emptyLabel?: string
}>(), {
  height: 180,
  emptyLabel: 'No data yet',
})

const max = computed(() => Math.max(...props.items.map((i) => i.value), 1))
const palette = ['#2481cc', '#0d9488', '#d97706', '#dc2626', '#7c3aed', '#059669', '#ea580c', '#64748b']
</script>

<template>
  <div v-if="!items.length" class="flex items-center justify-center text-sm text-slate-500" :style="{ height: `${height}px` }">
    {{ emptyLabel }}
  </div>
  <svg
    v-else
    :viewBox="`0 0 320 ${height}`"
    class="h-auto w-full"
    role="img"
  >
    <g v-for="(item, index) in items" :key="item.label">
      <text
        :x="0"
        :y="18 + index * (height / Math.max(items.length, 1))"
        class="fill-current text-[10px] opacity-70"
      >
        {{ item.label.length > 14 ? `${item.label.slice(0, 14)}…` : item.label }}
      </text>
      <rect
        :x="90"
        :y="6 + index * (height / Math.max(items.length, 1))"
        :width="Math.max(4, (item.value / max) * 180)"
        height="14"
        rx="4"
        :fill="item.color || palette[index % palette.length]"
      />
      <text
        :x="96 + Math.max(4, (item.value / max) * 180)"
        :y="17 + index * (height / Math.max(items.length, 1))"
        class="fill-current text-[10px] font-semibold"
      >
        {{ item.value }}
      </text>
    </g>
  </svg>
</template>

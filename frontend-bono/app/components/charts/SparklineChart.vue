<script setup lang="ts">
const props = withDefaults(defineProps<{
  points: number[]
  height?: number
  color?: string
}>(), {
  height: 64,
  color: '#2481cc',
})

const width = 280
const path = computed(() => {
  if (!props.points.length) return ''
  const max = Math.max(...props.points, 1)
  const step = width / Math.max(props.points.length - 1, 1)
  return props.points
    .map((value, index) => {
      const x = index * step
      const y = props.height - 8 - (value / max) * (props.height - 16)
      return `${index === 0 ? 'M' : 'L'} ${x} ${y}`
    })
    .join(' ')
})

const area = computed(() => {
  if (!path.value) return ''
  return `${path.value} L ${width} ${props.height} L 0 ${props.height} Z`
})
</script>

<template>
  <svg :viewBox="`0 0 ${width} ${height}`" class="h-auto w-full" role="img">
    <path :d="area" :fill="color" opacity="0.12" />
    <path :d="path" fill="none" :stroke="color" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
  </svg>
</template>

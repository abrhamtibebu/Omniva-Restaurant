<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()

const slots = useSlots()
const hasActions = computed(() => Boolean(slots.actions))
</script>

<template>
  <div
    class="page-header mb-4 flex flex-col gap-3 sm:mb-6 sm:flex-row sm:items-center sm:justify-between"
    :class="{ 'page-header--has-actions': hasActions }"
  >
    <div class="min-w-0">
      <h1 class="page-title text-xl font-bold tracking-tight sm:text-2xl">{{ title }}</h1>
      <p v-if="description" class="page-desc mt-1 text-sm">{{ description }}</p>
    </div>

    <div v-if="$slots.actions" class="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center">
      <slot name="actions" />
    </div>
  </div>
</template>

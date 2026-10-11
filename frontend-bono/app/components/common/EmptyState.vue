<script setup lang="ts">
withDefaults(defineProps<{
  title: string
  description?: string
  /** Heroicons-style path data; defaults to an inbox glyph. */
  icon?: string
}>(), {
  icon: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z',
})
</script>

<template>
  <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
    <div class="rounded-full bg-slate-100 p-3">
      <svg class="size-7 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" :d="icon" />
      </svg>
    </div>

    <h3 class="mt-4 text-sm font-semibold text-slate-900">{{ title }}</h3>
    <p v-if="description" class="mt-1 max-w-sm text-sm text-slate-500">{{ description }}</p>

    <div v-if="$slots.action" class="mt-5">
      <slot name="action" />
    </div>
  </div>
</template>

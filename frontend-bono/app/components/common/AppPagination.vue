<script setup lang="ts">
import type { PaginationMeta } from '~/types/api'

const props = defineProps<{ meta: PaginationMeta | null }>()

const emit = defineEmits<{ change: [page: number] }>()

const canPrevious = computed(() => (props.meta?.current_page ?? 1) > 1)
const canNext = computed(() => {
  if (!props.meta) return false
  return props.meta.current_page < props.meta.last_page
})

/** Window of page numbers around the current page, with ellipsis gaps as 0. */
const pages = computed<number[]>(() => {
  if (!props.meta) return []

  const { current_page: current, last_page: last } = props.meta
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)

  const window = new Set<number>([1, last, current])
  for (const offset of [-1, 1]) {
    const page = current + offset
    if (page > 1 && page < last) window.add(page)
  }

  const sorted = [...window].sort((a, b) => a - b)
  const result: number[] = []

  sorted.forEach((page, index) => {
    if (index > 0 && page - sorted[index - 1]! > 1) result.push(0)
    result.push(page)
  })

  return result
})

function go(page: number) {
  if (!props.meta || page < 1 || page > props.meta.last_page || page === props.meta.current_page) return
  emit('change', page)
}
</script>

<template>
  <div
    v-if="meta && meta.total > 0"
    class="flex flex-col items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row"
  >
    <p class="text-sm text-slate-600">
      Showing <span class="font-medium">{{ meta.from ?? 0 }}</span>
      to <span class="font-medium">{{ meta.to ?? 0 }}</span>
      of <span class="font-medium">{{ meta.total }}</span>
    </p>

    <nav class="flex items-center gap-1" aria-label="Pagination">
      <button
        type="button"
        class="btn-ghost px-2.5 py-1.5"
        :disabled="!canPrevious"
        aria-label="Previous page"
        @click="go(meta.current_page - 1)"
      >
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
      </button>

      <template v-for="(page, index) in pages" :key="`${page}-${index}`">
        <span v-if="page === 0" class="px-2 text-sm text-slate-400">...</span>
        <button
          v-else
          type="button"
          class="min-w-9 rounded-lg px-2.5 py-1.5 text-sm font-medium transition-colors"
          :class="page === meta.current_page
            ? 'bg-brand-600 text-white'
            : 'text-slate-600 hover:bg-slate-100'"
          :aria-current="page === meta.current_page ? 'page' : undefined"
          @click="go(page)"
        >
          {{ page }}
        </button>
      </template>

      <button
        type="button"
        class="btn-ghost px-2.5 py-1.5"
        :disabled="!canNext"
        aria-label="Next page"
        @click="go(meta.current_page + 1)"
      >
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
      </button>
    </nav>
  </div>
</template>

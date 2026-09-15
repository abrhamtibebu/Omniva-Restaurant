<script setup lang="ts" generic="T extends Record<string, any>">
export interface TableColumn {
  key: string
  label: string
  align?: 'left' | 'center' | 'right'
  /** Hide the column below this breakpoint to keep narrow screens readable. */
  hideBelow?: 'sm' | 'md' | 'lg'
  class?: string
}

const props = withDefaults(defineProps<{
  columns: TableColumn[]
  rows: T[]
  loading?: boolean
  rowKey?: keyof T & string
  emptyTitle?: string
  emptyDescription?: string
  clickable?: boolean
}>(), {
  loading: false,
  rowKey: 'id',
  emptyTitle: 'Nothing here yet',
  clickable: false,
})

const emit = defineEmits<{ rowClick: [row: T] }>()

const HIDE_CLASSES = {
  sm: 'hidden sm:table-cell',
  md: 'hidden md:table-cell',
  lg: 'hidden lg:table-cell',
} as const

const ALIGN_CLASSES = {
  left: 'text-left',
  center: 'text-center',
  right: 'text-right',
} as const

function cellClasses(column: TableColumn) {
  return [
    ALIGN_CLASSES[column.align ?? 'left'],
    column.hideBelow ? HIDE_CLASSES[column.hideBelow] : '',
    column.class ?? '',
  ]
}

const showEmpty = computed(() => !props.loading && props.rows.length === 0)
</script>

<template>
  <div class="overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th
              v-for="column in columns"
              :key="column.key"
              scope="col"
              class="px-4 py-3 text-xs font-semibold tracking-wide text-slate-500 uppercase"
              :class="cellClasses(column)"
            >
              {{ column.label }}
            </th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-100 bg-white">
          <!-- Skeleton rows keep the layout from collapsing while loading. -->
          <template v-if="loading">
            <tr v-for="n in 5" :key="`skeleton-${n}`">
              <td v-for="column in columns" :key="column.key" class="px-4 py-4" :class="cellClasses(column)">
                <div class="h-3 animate-pulse rounded bg-slate-200" />
              </td>
            </tr>
          </template>

          <tr
            v-for="row in rows"
            v-else
            :key="String(row[rowKey])"
            class="transition-colors"
            :class="clickable ? 'cursor-pointer hover:bg-slate-50' : ''"
            @click="clickable && emit('rowClick', row)"
          >
            <td
              v-for="column in columns"
              :key="column.key"
              class="px-4 py-3.5 text-sm text-slate-700"
              :class="cellClasses(column)"
            >
              <slot :name="`cell:${column.key}`" :row="row" :value="row[column.key]">
                {{ row[column.key] ?? '-' }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <EmptyState v-if="showEmpty" :title="emptyTitle" :description="emptyDescription">
      <template v-if="$slots.emptyAction" #action>
        <slot name="emptyAction" />
      </template>
    </EmptyState>
  </div>
</template>

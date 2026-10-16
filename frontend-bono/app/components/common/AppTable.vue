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
  clickable: false,
})

const emit = defineEmits<{ rowClick: [row: T] }>()
const { t } = useI18n()

const resolvedEmptyTitle = computed(() => props.emptyTitle ?? t('common.nothingHere'))

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
const primaryColumns = computed(() => props.columns.filter((c) => c.key !== 'actions'))
</script>

<template>
  <div class="overflow-hidden">
    <!-- Mobile / Telegram: stacked cards -->
    <div class="space-y-3 lg:hidden">
      <template v-if="loading">
        <div v-for="n in 4" :key="`card-sk-${n}`" class="card animate-pulse space-y-2 p-3">
          <div class="h-3 w-1/3 rounded bg-slate-200" />
          <div class="h-3 w-2/3 rounded bg-slate-200" />
        </div>
      </template>
      <template v-else>
        <article
          v-for="row in rows"
          :key="String(row[rowKey])"
          class="card space-y-2 p-3"
          :class="clickable ? 'cursor-pointer active:bg-slate-50' : ''"
          @click="clickable && emit('rowClick', row)"
        >
          <div
            v-for="column in primaryColumns"
            :key="column.key"
            class="flex items-start justify-between gap-3 text-sm"
          >
            <span class="shrink-0 text-xs font-medium tracking-wide text-slate-500 uppercase">{{ column.label }}</span>
            <div class="min-w-0 text-right text-slate-800">
              <slot :name="`cell:${column.key}`" :row="row" :value="row[column.key]">
                {{ row[column.key] ?? t('common.dash') }}
              </slot>
            </div>
          </div>
          <div v-if="$slots['cell:actions']" class="border-t border-slate-100 pt-2">
            <slot name="cell:actions" :row="row" :value="row.actions" />
          </div>
        </article>
      </template>
    </div>

    <!-- Desktop table -->
    <div class="hidden overflow-x-auto lg:block">
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
                {{ row[column.key] ?? t('common.dash') }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <EmptyState v-if="showEmpty" :title="resolvedEmptyTitle" :description="emptyDescription">
      <template v-if="$slots.emptyAction" #action>
        <slot name="emptyAction" />
      </template>
    </EmptyState>
  </div>
</template>

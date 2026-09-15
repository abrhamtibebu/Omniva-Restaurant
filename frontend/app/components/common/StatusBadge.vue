<script setup lang="ts">
/**
 * One place that maps every domain status to a colour, so an order status looks
 * the same on the POS, the orders list and the receipt.
 */
const props = withDefaults(defineProps<{
  status: string
  label?: string
  size?: 'sm' | 'md'
}>(), {
  size: 'sm',
})

const TONES: Record<string, string> = {
  // Neutral / inactive
  draft: 'bg-slate-100 text-slate-700 ring-slate-200',
  cancelled: 'bg-slate-100 text-slate-500 ring-slate-200',
  inactive: 'bg-slate-100 text-slate-500 ring-slate-200',
  no_show: 'bg-slate-200 text-slate-600 ring-slate-300',
  cleaning: 'bg-slate-200 text-slate-700 ring-slate-300',

  // In progress
  submitted: 'bg-sky-50 text-sky-700 ring-sky-200',
  new: 'bg-sky-50 text-sky-700 ring-sky-200',
  pending: 'bg-amber-50 text-amber-700 ring-amber-200',
  preparing: 'bg-amber-50 text-amber-700 ring-amber-200',
  partially_paid: 'bg-amber-50 text-amber-700 ring-amber-200',
  unpaid: 'bg-red-50 text-red-700 ring-red-200',
  reserved: 'bg-violet-50 text-violet-700 ring-violet-200',
  confirmed: 'bg-violet-50 text-violet-700 ring-violet-200',

  // Ready / attention
  ready: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  occupied: 'bg-red-50 text-red-700 ring-red-200',
  seated: 'bg-sky-50 text-sky-700 ring-sky-200',
  low_stock: 'bg-red-50 text-red-700 ring-red-200',

  // Done
  served: 'bg-teal-50 text-teal-700 ring-teal-200',
  completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  paid: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  available: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  active: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  refunded: 'bg-orange-50 text-orange-700 ring-orange-200',
}

const tone = computed(() => TONES[props.status] ?? 'bg-slate-100 text-slate-700 ring-slate-200')
const text = computed(() => props.label ?? humanize(props.status))
</script>

<template>
  <span
    class="inline-flex items-center rounded-full font-medium ring-1 ring-inset whitespace-nowrap"
    :class="[tone, size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-sm']"
  >
    {{ text }}
  </span>
</template>

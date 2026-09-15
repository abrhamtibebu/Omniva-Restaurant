<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
import type { Order } from '~/types/domain'

definePageMeta({
  layout: 'fullscreen',
  permissions: [PERMISSIONS.kitchenView],
})

const api = useRestaurantApi()
const toast = useToast()
const now = ref(Date.now())
const orders = ref<Order[]>([])
const loading = ref(true)
const tab = ref<'new' | 'preparing' | 'ready'>('new')

const columns = computed(() => {
  const items = orders.value.flatMap((order) =>
    (order.items ?? [])
      .filter((item) => item.submitted_to_kitchen)
      .map((item) => ({ order, item })),
  )
  return {
    new: items.filter((row) => row.item.kitchen_status === 'new'),
    preparing: items.filter((row) => row.item.kitchen_status === 'preparing'),
    ready: items.filter((row) => row.item.kitchen_status === 'ready'),
  }
})

const tickets = computed(() => {
  const grouped = new Map<number, Order>()
  for (const { order } of columns.value[tab.value]) {
    grouped.set(order.id, order)
  }
  return [...grouped.values()]
})

async function load() {
  try {
    orders.value = (await api.kitchenOrders()).data
  }
  catch (e: any) {
    toast.error(e.message)
  }
  finally {
    loading.value = false
  }
}

async function bump(itemId: number, status: string) {
  try {
    await api.kitchenStatus(itemId, status)
    await load()
  }
  catch (e: any) {
    toast.error(e.message)
  }
}

let timer: ReturnType<typeof setInterval>
onMounted(() => {
  load()
  timer = setInterval(() => {
    now.value = Date.now()
    load()
  }, 5000)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <div class="flex min-h-screen flex-col bg-slate-900 text-white">
    <header class="flex items-center justify-between border-b border-white/10 px-4 py-3">
      <div>
        <p class="text-lg font-bold">Kitchen</p>
        <NuxtLink to="/" class="text-sm text-slate-400">Back</NuxtLink>
      </div>
      <div class="flex gap-2">
        <button
          v-for="key in (['new', 'preparing', 'ready'] as const)"
          :key="key"
          type="button"
          class="rounded-lg px-4 py-2 text-sm font-semibold uppercase"
          :class="tab === key ? 'bg-brand-600' : 'bg-white/10'"
          @click="tab = key"
        >
          {{ key }}
        </button>
      </div>
    </header>

    <div v-if="loading" class="p-8 text-slate-400">Loading tickets…</div>
    <div v-else class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
      <article v-for="order in tickets" :key="order.id" class="rounded-2xl bg-slate-800 p-5 shadow-lg">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-2xl font-bold">Table {{ order.table?.name ?? 'TW' }}</p>
            <p class="text-slate-300">{{ order.order_number }}</p>
            <p class="text-sm text-slate-400">{{ order.waiter?.name }}</p>
          </div>
          <p class="text-3xl font-black text-amber-400">{{ formatElapsed(order.opened_at, now) }}</p>
        </div>
        <ul class="mt-4 space-y-3">
          <li
            v-for="item in (order.items ?? []).filter(i => i.kitchen_status === tab && i.submitted_to_kitchen)"
            :key="item.id"
          >
            <p class="text-lg font-semibold">{{ item.quantity }} × {{ item.item_name_snapshot }}</p>
            <p v-for="mod in item.modifiers" :key="mod.id" class="text-sm text-slate-300">• {{ mod.name }}</p>
            <p v-if="item.notes" class="text-sm text-amber-200">{{ item.notes }}</p>
            <button
              v-if="tab === 'new'"
              type="button"
              class="btn-primary btn-touch mt-2 w-full"
              @click="bump(item.id, 'preparing')"
            >
              Start preparing
            </button>
            <button
              v-else-if="tab === 'preparing'"
              type="button"
              class="btn-primary btn-touch mt-2 w-full bg-emerald-600 hover:bg-emerald-700"
              @click="bump(item.id, 'ready')"
            >
              Mark ready
            </button>
          </li>
        </ul>
      </article>
      <EmptyState
        v-if="!tickets.length"
        title="No tickets"
        description="New kitchen tickets will appear here."
        class="col-span-full rounded-2xl bg-slate-800"
      />
    </div>
  </div>
</template>

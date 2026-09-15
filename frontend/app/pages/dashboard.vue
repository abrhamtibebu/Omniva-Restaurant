<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({ permissions: [PERMISSIONS.dashboardView] })

const api = useRestaurantApi()
const auth = useAuthStore()
const data = ref<any>(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  try {
    data.value = (await api.dashboard() as any).data
  }
  catch (e: any) {
    error.value = e.message
  }
  finally {
    loading.value = false
  }
}
onMounted(load)

const cards = computed(() => {
  const t = data.value?.today
  if (!t) return []
  return [
    ['Gross sales', formatMoney(t.gross_sales, auth.currency)],
    ['Completed orders', t.completed_orders],
    ['Avg order', formatMoney(t.average_order_value, auth.currency)],
    ['Expenses', formatMoney(t.expenses, auth.currency)],
    ['Est. net', formatMoney(t.estimated_net, auth.currency)],
  ]
})
</script>

<template>
  <div>
    <PageHeader title="Dashboard" description="Today’s snapshot for this branch." />
    <ErrorState v-if="error" :message="error" @retry="load" />
    <div v-else-if="loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
      <div v-for="n in 5" :key="n" class="card h-24 animate-pulse" />
    </div>
    <template v-else>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div v-for="[label, value] in cards" :key="label" class="card p-4">
          <p class="text-sm text-slate-500">{{ label }}</p>
          <p class="mt-1 text-2xl font-bold">{{ value }}</p>
        </div>
      </div>
      <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <div class="card p-4">
          <h2 class="font-semibold">Floor</h2>
          <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
            <div>Open orders <strong>{{ data.operational.open_orders }}</strong></div>
            <div>Preparing <strong>{{ data.operational.preparing_orders }}</strong></div>
            <div>Ready <strong>{{ data.operational.ready_orders }}</strong></div>
            <div>Occupied tables <strong>{{ data.operational.occupied_tables }}</strong></div>
            <div>Available tables <strong>{{ data.operational.available_tables }}</strong></div>
          </dl>
        </div>
        <div class="card p-4">
          <h2 class="font-semibold">Low stock</h2>
          <ul v-if="data.operational.low_stock_items.length" class="mt-3 space-y-1 text-sm">
            <li v-for="item in data.operational.low_stock_items" :key="item.id">
              {{ item.name }} — {{ item.quantity_on_hand }} {{ item.unit }}
            </li>
          </ul>
          <p v-else class="mt-3 text-sm text-slate-500">All stock above minimums.</p>
        </div>
        <div class="card p-4">
          <h2 class="font-semibold">Top items</h2>
          <ul class="mt-3 space-y-1 text-sm">
            <li v-for="item in data.top_selling_items" :key="item.item_name_snapshot">
              {{ item.item_name_snapshot }} · {{ item.quantity_sold }} · {{ formatAmount(item.revenue) }}
            </li>
          </ul>
        </div>
        <div class="card p-4">
          <h2 class="font-semibold">By waiter</h2>
          <ul class="mt-3 space-y-1 text-sm">
            <li v-for="row in data.sales_by_waiter" :key="row.waiter_id">
              {{ row.waiter?.name }} · {{ row.orders }} orders · {{ formatAmount(row.sales) }}
            </li>
          </ul>
        </div>
      </div>
    </template>
  </div>
</template>

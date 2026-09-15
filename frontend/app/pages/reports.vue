<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
definePageMeta({ permissions: [PERMISSIONS.reportsView] })
const api = useRestaurantApi()
const auth = useAuthStore()
const from = ref(formatDateForInput())
const to = ref(formatDateForInput())
const sales = ref<any>(null)
const products = ref<any[]>([])
const payments = ref<any[]>([])
const expenses = ref<any>(null)

async function load() {
  const params = { from: from.value, to: to.value }
  const [s, p, pay, e] = await Promise.all([
    api.report('sales', params),
    api.report('products', params),
    api.report('payments', params),
    api.report('expenses', params),
  ])
  sales.value = (s as any).data
  products.value = (p as any).data
  payments.value = (pay as any).data
  expenses.value = (e as any).data
}
onMounted(load)
</script>
<template>
  <div>
    <PageHeader title="Reports">
      <template #actions>
        <input v-model="from" type="date" class="input w-auto">
        <input v-model="to" type="date" class="input w-auto">
        <button class="btn-primary" type="button" @click="load">Apply</button>
      </template>
    </PageHeader>
    <div v-if="sales" class="grid gap-4 lg:grid-cols-2">
      <div class="card p-4">
        <h2 class="font-semibold">Sales</h2>
        <ul class="mt-3 space-y-1 text-sm">
          <li>Total {{ formatMoney(sales.total_sales, auth.currency) }}</li>
          <li>Orders {{ sales.number_of_orders }}</li>
          <li>Average {{ formatMoney(sales.average_order_value, auth.currency) }}</li>
          <li>Discounts {{ formatAmount(sales.discounts) }}</li>
          <li>Tax {{ formatAmount(sales.tax) }}</li>
        </ul>
      </div>
      <div class="card p-4">
        <h2 class="font-semibold">Payments</h2>
        <ul class="mt-3 space-y-1 text-sm">
          <li v-for="row in payments" :key="row.payment_method">
            {{ humanize(row.payment_method) }} · {{ formatAmount(row.total) }}
          </li>
        </ul>
      </div>
      <div class="card p-4">
        <h2 class="font-semibold">Product sales</h2>
        <ul class="mt-3 space-y-1 text-sm">
          <li v-for="row in products" :key="row.menu_item">
            {{ row.menu_item }} · {{ row.quantity_sold }} · {{ formatAmount(row.revenue) }}
          </li>
        </ul>
      </div>
      <div class="card p-4">
        <h2 class="font-semibold">Expenses</h2>
        <p class="mt-2 text-sm">Total {{ formatAmount(expenses?.total_expenses) }}</p>
        <ul class="mt-2 space-y-1 text-sm">
          <li v-for="row in expenses?.by_category" :key="row.category">
            {{ humanize(row.category) }} · {{ formatAmount(row.total) }}
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

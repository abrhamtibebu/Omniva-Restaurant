<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
definePageMeta({ permissions: [PERMISSIONS.customersView] })
const route = useRoute()
const api = useRestaurantApi()
const data = ref<any>(null)
onMounted(async () => {
  data.value = (await api.customer(Number(route.params.id)) as any).data
})
</script>
<template>
  <div v-if="data">
    <PageHeader :title="data.name" :description="data.phone" />
    <div class="grid gap-4 lg:grid-cols-3">
      <div class="card p-4">
        <p>Orders: {{ data.order_count }}</p>
        <p>Total spending: {{ formatAmount(data.total_spending) }}</p>
      </div>
      <div class="card p-4 lg:col-span-2">
        <h2 class="font-semibold">History</h2>
        <ul class="mt-2 space-y-1 text-sm">
          <li v-for="order in data.orders" :key="order.id">
            <NuxtLink :to="`/orders/${order.id}`">{{ order.order_number }}</NuxtLink>
            · {{ formatAmount(order.total) }} · {{ order.status }}
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

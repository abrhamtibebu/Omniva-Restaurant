<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
import type { Order } from '~/types/domain'
import type { PaginationMeta } from '~/types/api'

definePageMeta({ permissions: [PERMISSIONS.ordersView, PERMISSIONS.ordersViewOwn] })

const api = useRestaurantApi()
const rows = ref<Order[]>([])
const meta = ref<PaginationMeta | null>(null)
const loading = ref(true)
const status = ref('')

async function load(page = 1) {
  loading.value = true
  const res = await api.orders({ page, status: status.value || undefined })
  rows.value = res.data
  meta.value = res.meta
  loading.value = false
}
onMounted(() => load())
</script>

<template>
  <div>
    <PageHeader title="Orders" description="Open and historical tickets.">
      <template #actions>
        <NuxtLink to="/pos" class="btn-primary">New order</NuxtLink>
      </template>
    </PageHeader>
    <select v-model="status" class="input mb-4 max-w-xs" @change="load(1)">
      <option value="">All statuses</option>
      <option v-for="s in ['draft','submitted','preparing','ready','served','completed','cancelled']" :key="s" :value="s">{{ humanize(s) }}</option>
    </select>
    <div class="card">
      <AppTable
        :columns="[
          { key: 'order_number', label: 'Order' },
          { key: 'status', label: 'Status' },
          { key: 'table', label: 'Table' },
          { key: 'total', label: 'Total', align: 'right' },
          { key: 'created_at', label: 'Opened', hideBelow: 'md' },
        ]"
        :rows="rows"
        :loading="loading"
        clickable
        @row-click="(row: Order) => navigateTo(`/orders/${row.id}`)"
      >
        <template #cell:status="{ row }"><StatusBadge :status="row.status" /></template>
        <template #cell:table="{ row }">{{ row.table?.name ?? 'Takeaway' }}</template>
        <template #cell:total="{ row }">{{ formatAmount(row.total) }}</template>
        <template #cell:created_at="{ row }">{{ formatDateTime(row.created_at) }}</template>
      </AppTable>
      <AppPagination :meta="meta" @change="load" />
    </div>
  </div>
</template>

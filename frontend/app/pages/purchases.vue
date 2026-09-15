<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
definePageMeta({ permissions: [PERMISSIONS.purchasesView] })
const api = useRestaurantApi()
const rows = ref<any[]>([])
const loading = ref(true)
onMounted(async () => {
  rows.value = (await api.purchases()).data
  loading.value = false
})
</script>
<template>
  <div>
    <PageHeader title="Purchases" description="Receive stock through purchase orders." />
    <div class="card">
      <AppTable :loading="loading" :rows="rows" :columns="[
        { key: 'purchase_number', label: 'PO' },
        { key: 'purchase_date', label: 'Date' },
        { key: 'total', label: 'Total', align: 'right' },
        { key: 'payment_status', label: 'Status' },
      ]">
        <template #cell:payment_status="{ row }"><StatusBadge :status="row.payment_status" /></template>
        <template #cell:total="{ row }">{{ formatAmount(row.total) }}</template>
      </AppTable>
    </div>
  </div>
</template>

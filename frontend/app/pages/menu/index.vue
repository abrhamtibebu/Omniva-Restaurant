<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
import type { MenuItem } from '~/types/domain'

definePageMeta({ permissions: [PERMISSIONS.menuView] })

const api = useRestaurantApi()
const items = ref<MenuItem[]>([])
const loading = ref(true)

onMounted(async () => {
  items.value = (await api.items({ per_page: 100 })).data
  loading.value = false
})
</script>

<template>
  <div>
    <PageHeader title="Menu" description="Categories, items, variants and add-ons." />
    <div class="card">
      <AppTable
        :columns="[
          { key: 'name', label: 'Item' },
          { key: 'category', label: 'Category' },
          { key: 'base_price', label: 'Price', align: 'right' },
          { key: 'available', label: 'Available' },
        ]"
        :rows="items"
        :loading="loading"
      >
        <template #cell:category="{ row }">{{ row.category?.name }}</template>
        <template #cell:base_price="{ row }">{{ formatAmount(row.base_price) }}</template>
        <template #cell:available="{ row }"><StatusBadge :status="row.available ? 'available' : 'inactive'" /></template>
      </AppTable>
    </div>
  </div>
</template>

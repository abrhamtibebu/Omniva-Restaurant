<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({ permissions: [PERMISSIONS.discrepanciesReview] })

const api = useRestaurantApi()
const toast = useToast()
const rows = ref<any[]>([])
const resolution = ref('Accepted after review')

async function load() {
  rows.value = (await api.discrepancies() as any).data
}

async function resolve(row: any) {
  try {
    await api.resolveDiscrepancy(row.id, { resolution: resolution.value })
    toast.success('Discrepancy resolved')
    await load()
  }
  catch (error: any) { toast.error(error.message) }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Discrepancies" description="Original counts stay on the record. A resolution is a separate audited decision." />
    <input v-model="resolution" class="input mb-4 max-w-md" placeholder="Resolution note">
    <div class="card">
      <AppTable :rows="rows" :columns="[
        { key: 'type', label: 'Type' },
        { key: 'expected_quantity', label: 'Expected' },
        { key: 'actual_quantity', label: 'Actual' },
        { key: 'variance_quantity', label: 'Variance' },
        { key: 'status', label: 'Status' },
        { key: 'actions', label: '' },
      ]">
        <template #cell:status="{ row }"><StatusBadge :status="row.status" /></template>
        <template #cell:actions="{ row }">
          <button v-if="row.status === 'open'" class="text-sm text-brand-700" type="button" @click="resolve(row)">Resolve</button>
        </template>
      </AppTable>
    </div>
  </div>
</template>

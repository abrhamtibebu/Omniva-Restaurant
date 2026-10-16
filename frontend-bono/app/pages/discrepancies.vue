<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({ permissions: [PERMISSIONS.discrepanciesReview] })

const { t } = useI18n()
const api = useRestaurantApi()
const toast = useToast()
const rows = ref<any[]>([])
const resolution = ref('')
const rowActions = useBusyMap()

const columns = computed(() => [
  { key: 'type', label: t('common.type') },
  { key: 'expected_quantity', label: t('common.expected') },
  { key: 'actual_quantity', label: t('common.actual') },
  { key: 'variance_quantity', label: t('common.variance') },
  { key: 'status', label: t('common.status') },
  { key: 'actions', label: '' },
])

async function load() {
  rows.value = (await api.discrepancies() as any).data
}

async function resolve(row: any) {
  await rowActions.run(row.id, async () => {
    try {
      await api.resolveDiscrepancy(row.id, {
        resolution: resolution.value || t('discrepancies.defaultResolution'),
      })
      toast.success(t('discrepancies.resolvedToast'))
      await load()
    }
    catch (error: any) { toast.error(error.message) }
  })
}

onMounted(() => {
  resolution.value = t('discrepancies.defaultResolution')
  load()
})
</script>

<template>
  <div>
    <PageHeader :title="t('discrepancies.title')" :description="t('discrepancies.description')" />
    <input v-model="resolution" class="input mb-4 max-w-md" :placeholder="t('discrepancies.resolutionPlaceholder')">
    <div class="card">
      <AppTable :rows="rows" :columns="columns">
        <template #cell:status="{ row }"><StatusBadge :status="row.status" /></template>
        <template #cell:actions="{ row }">
          <AppButton
            v-if="row.status === 'open'"
            variant="ghost"
            size="sm"
            class="text-sm text-brand-700"
            :loading="rowActions.isBusy(row.id)"
            @click="resolve(row)"
          >
            {{ t('discrepancies.resolve') }}
          </AppButton>
        </template>
      </AppTable>
    </div>
  </div>
</template>

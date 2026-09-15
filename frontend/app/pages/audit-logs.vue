<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
definePageMeta({ permissions: [PERMISSIONS.auditLogsView] })
const api = useRestaurantApi()
const rows = ref<any[]>([])
onMounted(async () => {
  rows.value = (await api.auditLogs()).data
})
</script>
<template>
  <div>
    <PageHeader title="Audit log" description="Sensitive operations only." />
    <div class="card">
      <AppTable :rows="rows" :columns="[
        { key: 'created_at', label: 'When' },
        { key: 'action', label: 'Action' },
        { key: 'user', label: 'User' },
        { key: 'auditable_type', label: 'Record', hideBelow: 'md' },
      ]">
        <template #cell:created_at="{ row }">{{ formatDateTime(row.created_at) }}</template>
        <template #cell:user="{ row }">{{ row.user?.name ?? 'System' }}</template>
      </AppTable>
    </div>
  </div>
</template>

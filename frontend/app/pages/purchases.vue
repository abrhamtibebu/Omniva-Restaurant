<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
definePageMeta({ permissions: [PERMISSIONS.purchasesView] })
const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const rows = ref<any[]>([])
const loading = ref(true)

async function load() {
  loading.value = true
  rows.value = (await api.purchases()).data
  loading.value = false
}

async function decide(row: any, decision: 'approved' | 'rejected') {
  try {
    await api.decidePurchase(row.id, { decision })
    toast.success(decision === 'approved' ? 'Purchase approved' : 'Purchase rejected')
    await load()
  }
  catch (error: any) { toast.error(error.message) }
}

async function receive(row: any) {
  try {
    await api.receivePurchase(row.id)
    toast.success('Accepted quantities added to store stock')
    await load()
  }
  catch (error: any) { toast.error(error.message) }
}

onMounted(load)
</script>
<template>
  <div>
    <PageHeader title="Purchases" description="Receive stock through purchase orders." />
    <div class="card">
      <AppTable :loading="loading" :rows="rows" :columns="[
        { key: 'purchase_number', label: 'PO' },
        { key: 'purchase_date', label: 'Date' },
        { key: 'total', label: 'Total', align: 'right' },
        { key: 'payment_status', label: 'Payment' },
        { key: 'approval_status', label: 'Approval' },
        { key: 'actions', label: '' },
      ]">
        <template #cell:payment_status="{ row }"><StatusBadge :status="row.payment_status" /></template>
        <template #cell:approval_status="{ row }"><StatusBadge :status="row.approval_status ?? 'approved'" /></template>
        <template #cell:actions="{ row }">
          <div class="flex gap-2">
            <button v-if="auth.can(PERMISSIONS.purchasesApprove) && row.approval_status === 'pending'" class="text-sm" type="button" @click="decide(row, 'approved')">Approve</button>
            <button v-if="auth.can(PERMISSIONS.purchasesApprove) && row.approval_status === 'pending'" class="text-sm" type="button" @click="decide(row, 'rejected')">Reject</button>
            <button v-if="auth.can(PERMISSIONS.purchasesManage) && !row.received_at && row.approval_status !== 'rejected' && row.approval_status !== 'pending'" class="text-sm" type="button" @click="receive(row)">Receive</button>
          </div>
        </template>
        <template #cell:total="{ row }">{{ formatAmount(row.total) }}</template>
      </AppTable>
    </div>
  </div>
</template>

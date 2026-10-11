<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({
  permissions: [PERMISSIONS.bonosReconcile, PERMISSIONS.bonosSubmit, PERMISSIONS.barClose, PERMISSIONS.kitchenReconcile],
})

const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const rows = ref<any[]>([])
const types = ref<any[]>([])
const form = reactive({
  department: auth.role === 'barman' ? 'bar' : auth.role === 'kitchen' ? 'kitchen' : 'waiter',
  bono_type_id: '',
  sold_quantity: '0',
  returned_quantity: '0',
  cash_amount: '0',
  transfer_amount: '',
  transfer_reference: '',
})

async function load() {
  rows.value = (await api.reconciliations() as any).data
  if (auth.can(PERMISSIONS.bonosSubmit) || auth.can(PERMISSIONS.bonosIssue)) {
    types.value = (await api.bonoTypes() as any).data
    if (!form.bono_type_id && types.value[0]) form.bono_type_id = String(types.value[0].id)
  }
}

async function submit() {
  try {
    await api.submitReconciliation({
      department: form.department,
      cash_amount: form.cash_amount,
      lines: [{
        bono_type_id: Number(form.bono_type_id),
        sold_quantity: form.sold_quantity,
        returned_quantity: form.returned_quantity,
      }],
      transfers: form.transfer_amount
        ? [{ method: 'mobile_money', amount: form.transfer_amount, reference: form.transfer_reference }]
        : [],
    })
    toast.success('Shift close submitted. Transfers stay unverified until the cashier checks them.')
    await load()
  }
  catch (error: any) { toast.error(error.message) }
}

async function verify(row: any) {
  try {
    await api.verifyReconciliation(row.id, {
      verified_cash: row.submitted_cash,
      transfers: (row.evidence ?? []).map((item: any) => ({ id: item.id, verification_status: 'verified' })),
    })
    toast.success('Counts recorded separately from the submitted figures')
    await load()
  }
  catch (error: any) { toast.error(error.message) }
}

async function accept(row: any) {
  try {
    await api.acceptReconciliation(row.id)
    toast.success('Reconciliation closed')
    await load()
  }
  catch (error: any) { toast.error(error.message) }
}

async function flag(row: any) {
  try {
    await api.flagReconciliation(row.id, { reason: 'Physical count does not match the submission.' })
    toast.success('Discrepancy opened')
    await load()
  }
  catch (error: any) { toast.error(error.message) }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Reconciliation" description="Submitted amounts stay separate from what the cashier verifies." />
    <form v-if="auth.canAny([PERMISSIONS.bonosSubmit, PERMISSIONS.barClose, PERMISSIONS.kitchenReconcile])" class="card mb-4 grid gap-2 p-4 md:grid-cols-3" @submit.prevent="submit">
      <select v-model="form.bono_type_id" class="input">
        <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
      </select>
      <input v-model="form.sold_quantity" class="input" placeholder="Sold">
      <input v-model="form.returned_quantity" class="input" placeholder="Returned">
      <input v-model="form.cash_amount" class="input" placeholder="Cash submitted">
      <input v-model="form.transfer_amount" class="input" placeholder="Transfer amount">
      <input v-model="form.transfer_reference" class="input" placeholder="Transfer reference">
      <button class="btn-primary" type="submit">Submit close</button>
    </form>
    <div class="card">
      <AppTable :rows="rows" :columns="[
        { key: 'reference', label: 'Ref' },
        { key: 'department', label: 'Department' },
        { key: 'status', label: 'Status' },
        { key: 'submitted_cash', label: 'Cash submitted' },
        { key: 'verified_cash', label: 'Cash verified' },
        { key: 'actions', label: '' },
      ]">
        <template #cell:status="{ row }"><StatusBadge :status="row.status" /></template>
        <template #cell:actions="{ row }">
          <div v-if="auth.can(PERMISSIONS.bonosReconcile) && row.status !== 'closed'" class="flex gap-2">
            <button class="text-sm" type="button" @click="verify(row)">Verify</button>
            <button class="text-sm" type="button" @click="accept(row)">Accept</button>
            <button class="text-sm text-red-700" type="button" @click="flag(row)">Flag</button>
          </div>
        </template>
      </AppTable>
    </div>
  </div>
</template>

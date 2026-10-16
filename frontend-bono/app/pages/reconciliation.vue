<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({
  permissions: [PERMISSIONS.bonosReconcile, PERMISSIONS.bonosSubmit, PERMISSIONS.barClose, PERMISSIONS.kitchenReconcile],
})

const { t } = useI18n()
const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const rows = ref<any[]>([])
const types = ref<any[]>([])
const { busy: submitting, run: runSubmit } = useBusyAction()
const rowActions = useBusyMap()
const form = reactive({
  department: auth.role === 'barman' ? 'bar' : auth.role === 'kitchen' ? 'kitchen' : 'waiter',
  bono_type_id: '',
  sold_quantity: '0',
  returned_quantity: '0',
  cash_amount: '0',
  transfer_amount: '',
  transfer_reference: '',
})

const columns = computed(() => [
  { key: 'reference', label: t('common.reference') },
  { key: 'department', label: t('common.department') },
  { key: 'status', label: t('common.status') },
  { key: 'submitted_cash', label: t('reconciliation.cashSubmitted') },
  { key: 'verified_cash', label: t('reconciliation.cashVerified') },
  { key: 'actions', label: '' },
])

async function load() {
  rows.value = (await api.reconciliations() as any).data
  if (auth.can(PERMISSIONS.bonosSubmit) || auth.can(PERMISSIONS.bonosIssue)) {
    types.value = (await api.bonoTypes() as any).data
    if (!form.bono_type_id && types.value[0]) form.bono_type_id = String(types.value[0].id)
  }
}

async function submit() {
  await runSubmit(async () => {
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
      toast.success(t('reconciliation.submittedToast'))
      await load()
    }
    catch (error: any) { toast.error(error.message) }
  })
}

async function verify(row: any) {
  await rowActions.run(`verify-${row.id}`, async () => {
    try {
      await api.verifyReconciliation(row.id, {
        verified_cash: row.submitted_cash,
        transfers: (row.evidence ?? []).map((item: any) => ({ id: item.id, verification_status: 'verified' })),
      })
      toast.success(t('reconciliation.verifiedToast'))
      await load()
    }
    catch (error: any) { toast.error(error.message) }
  })
}

async function accept(row: any) {
  await rowActions.run(`accept-${row.id}`, async () => {
    try {
      await api.acceptReconciliation(row.id)
      toast.success(t('reconciliation.closedToast'))
      await load()
    }
    catch (error: any) { toast.error(error.message) }
  })
}

async function flag(row: any) {
  await rowActions.run(`flag-${row.id}`, async () => {
    try {
      await api.flagReconciliation(row.id, { reason: t('reconciliation.flagReason') })
      toast.success(t('reconciliation.flaggedToast'))
      await load()
    }
    catch (error: any) { toast.error(error.message) }
  })
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader :title="t('reconciliation.title')" :description="t('reconciliation.description')" />
    <form v-if="auth.canAny([PERMISSIONS.bonosSubmit, PERMISSIONS.barClose, PERMISSIONS.kitchenReconcile])" class="card mb-4 grid gap-2 p-4 md:grid-cols-3" @submit.prevent="submit">
      <select v-model="form.bono_type_id" class="input">
        <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
      </select>
      <input v-model="form.sold_quantity" class="input" :placeholder="t('reconciliation.sold')">
      <input v-model="form.returned_quantity" class="input" :placeholder="t('reconciliation.returned')">
      <input v-model="form.cash_amount" class="input" :placeholder="t('reconciliation.cashSubmitted')">
      <input v-model="form.transfer_amount" class="input" :placeholder="t('reconciliation.transferAmount')">
      <input v-model="form.transfer_reference" class="input" :placeholder="t('reconciliation.transferReference')">
      <AppButton type="submit" :loading="submitting">{{ t('reconciliation.submitClose') }}</AppButton>
    </form>
    <div class="card">
      <AppTable :rows="rows" :columns="columns">
        <template #cell:status="{ row }"><StatusBadge :status="row.status" /></template>
        <template #cell:actions="{ row }">
          <div v-if="auth.can(PERMISSIONS.bonosReconcile) && row.status !== 'closed'" class="flex gap-2">
            <AppButton variant="ghost" size="sm" class="text-sm" :loading="rowActions.isBusy(`verify-${row.id}`)" @click="verify(row)">{{ t('reconciliation.verify') }}</AppButton>
            <AppButton variant="ghost" size="sm" class="text-sm" :loading="rowActions.isBusy(`accept-${row.id}`)" @click="accept(row)">{{ t('reconciliation.accept') }}</AppButton>
            <AppButton variant="ghost" size="sm" class="text-sm text-red-700" :loading="rowActions.isBusy(`flag-${row.id}`)" @click="flag(row)">{{ t('reconciliation.flag') }}</AppButton>
          </div>
        </template>
      </AppTable>
    </div>
  </div>
</template>

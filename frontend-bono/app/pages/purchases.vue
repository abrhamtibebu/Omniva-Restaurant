<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({ permissions: [PERMISSIONS.purchasesView, PERMISSIONS.purchasesManage, PERMISSIONS.purchasesApprove] })

const { t } = useI18n()
const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const { isTelegram } = useTelegramWebApp()
const rows = ref<any[]>([])
const suppliers = ref<any[]>([])
const items = ref<any[]>([])
const loading = ref(true)
const { busy: creating, run: runCreate } = useBusyAction()
const rowActions = useBusyMap()
const form = reactive({
  supplier_id: '',
  purchase_date: new Date().toISOString().slice(0, 10),
  inventory_item_id: '',
  quantity: '1',
  unit_cost: '0',
  notes: '',
})

const columns = computed(() => [
  { key: 'purchase_number', label: t('common.po') },
  { key: 'purchase_date', label: t('common.date') },
  { key: 'total', label: t('common.total'), align: 'right' as const },
  { key: 'payment_status', label: t('common.payment') },
  { key: 'approval_status', label: t('common.approval') },
  { key: 'actions', label: '' },
])

async function load() {
  loading.value = true
  try {
    rows.value = ((await api.purchases()) as any).data ?? []
    if (auth.can(PERMISSIONS.purchasesManage)) {
      const [sup, inv] = await Promise.all([
        api.suppliers({ per_page: 100 }),
        api.inventory({ per_page: 100 }),
      ])
      suppliers.value = (sup as any).data ?? []
      items.value = (inv as any).data ?? []
      if (!form.supplier_id && suppliers.value[0]) form.supplier_id = String(suppliers.value[0].id)
      if (!form.inventory_item_id && items.value[0]) form.inventory_item_id = String(items.value[0].id)
    }
  }
  catch (error: any) {
    toast.error(error.message)
  }
  finally {
    loading.value = false
  }
}

async function create() {
  await runCreate(async () => {
    try {
      await api.createPurchase({
        supplier_id: Number(form.supplier_id),
        purchase_date: form.purchase_date,
        notes: form.notes || null,
        items: [{
          inventory_item_id: Number(form.inventory_item_id),
          quantity: form.quantity,
          unit_cost: form.unit_cost,
        }],
      })
      toast.success(t('purchases.requestedToast'))
      await load()
    }
    catch (error: any) {
      toast.error(error.message)
    }
  })
}

async function decide(row: any, decision: 'approved' | 'rejected') {
  await rowActions.run(`${decision}-${row.id}`, async () => {
    try {
      await api.decidePurchase(row.id, { decision })
      toast.success(decision === 'approved' ? t('purchases.approvedToast') : t('purchases.rejectedToast'))
      await load()
    }
    catch (error: any) {
      toast.error(error.message)
    }
  })
}

async function receive(row: any) {
  await rowActions.run(`receive-${row.id}`, async () => {
    try {
      await api.receivePurchase(row.id)
      toast.success(t('purchases.receivedToast'))
      await load()
    }
    catch (error: any) {
      toast.error(error.message)
    }
  })
}

onMounted(load)
</script>

<template>
  <div class="space-y-4">
    <PageHeader
      :title="t('purchases.title')"
      :description="t('purchases.description')"
    />

    <form
      v-if="auth.can(PERMISSIONS.purchasesManage)"
      class="mb-4 grid gap-2 p-4 md:grid-cols-3 tg-stack"
      :class="isTelegram ? 'tg-soft-card' : 'card'"
      @submit.prevent="create"
    >
      <p v-if="isTelegram" class="tg-section-label md:col-span-3">{{ t('purchases.requestPurchase') }}</p>
      <select v-model="form.supplier_id" class="input" required>
        <option disabled value="">{{ t('common.supplier') }}</option>
        <option v-for="supplier in suppliers" :key="supplier.id" :value="String(supplier.id)">
          {{ supplier.name }}
        </option>
      </select>
      <input v-model="form.purchase_date" class="input" type="date" required>
      <select v-model="form.inventory_item_id" class="input" required>
        <option disabled value="">{{ t('common.inventoryItem') }}</option>
        <option v-for="item in items" :key="item.id" :value="String(item.id)">
          {{ item.name }} · {{ t('purchases.onHand', { qty: item.quantity_on_hand }) }}
        </option>
      </select>
      <input v-model="form.quantity" class="input" type="number" min="0.001" step="0.001" :placeholder="t('common.qty')" required>
      <input v-model="form.unit_cost" class="input" type="number" min="0" step="0.01" :placeholder="t('common.unitCost')" required>
      <input v-model="form.notes" class="input" :placeholder="t('purchases.notesPlaceholder')">
      <AppButton type="submit" class="md:col-span-3" :loading="creating">{{ t('purchases.requestPurchase') }}</AppButton>
    </form>

    <template v-if="isTelegram">
      <div class="tg-soft-card !py-1">
        <p class="tg-section-label px-0 pt-2">{{ t('purchases.title') }}</p>
        <div v-for="row in rows" :key="row.id" class="tg-meta-row !items-start">
          <div class="tg-meta-body space-y-1">
            <p class="tg-meta-title">{{ row.purchase_number }} · {{ formatAmount(row.total) }}</p>
            <p class="tg-meta-sub">{{ row.purchase_date }}</p>
            <div class="flex flex-wrap gap-1">
              <StatusBadge :status="row.payment_status" />
              <StatusBadge :status="row.approval_status ?? 'approved'" />
            </div>
            <div class="flex flex-wrap gap-2 pt-1">
              <AppButton
                v-if="auth.can(PERMISSIONS.purchasesApprove) && row.approval_status === 'pending'"
                size="sm"
                :loading="rowActions.isBusy(`approved-${row.id}`)"
                @click="decide(row, 'approved')"
              >
                {{ t('common.approve') }}
              </AppButton>
              <AppButton
                v-if="auth.can(PERMISSIONS.purchasesApprove) && row.approval_status === 'pending'"
                variant="danger"
                size="sm"
                :loading="rowActions.isBusy(`rejected-${row.id}`)"
                @click="decide(row, 'rejected')"
              >
                {{ t('common.reject') }}
              </AppButton>
              <AppButton
                v-if="auth.can(PERMISSIONS.purchasesManage) && !row.received_at && row.approval_status !== 'rejected' && row.approval_status !== 'pending'"
                size="sm"
                variant="secondary"
                :loading="rowActions.isBusy(`receive-${row.id}`)"
                @click="receive(row)"
              >
                {{ t('purchases.receive') }}
              </AppButton>
            </div>
          </div>
        </div>
        <p v-if="!rows.length && !loading" class="py-3 text-sm opacity-60">{{ t('common.nothingHere') }}</p>
      </div>
    </template>

    <div v-else class="card">
      <AppTable
        :loading="loading"
        :rows="rows"
        :columns="columns"
      >
        <template #cell:payment_status="{ row }"><StatusBadge :status="row.payment_status" /></template>
        <template #cell:approval_status="{ row }"><StatusBadge :status="row.approval_status ?? 'approved'" /></template>
        <template #cell:total="{ row }">{{ formatAmount(row.total) }}</template>
        <template #cell:actions="{ row }">
          <div class="flex flex-wrap justify-end gap-2">
            <AppButton
              v-if="auth.can(PERMISSIONS.purchasesApprove) && row.approval_status === 'pending'"
              variant="ghost"
              size="sm"
              class="text-sm text-brand-700"
              :loading="rowActions.isBusy(`approved-${row.id}`)"
              @click="decide(row, 'approved')"
            >
              {{ t('common.approve') }}
            </AppButton>
            <AppButton
              v-if="auth.can(PERMISSIONS.purchasesApprove) && row.approval_status === 'pending'"
              variant="ghost"
              size="sm"
              class="text-sm text-red-600"
              :loading="rowActions.isBusy(`rejected-${row.id}`)"
              @click="decide(row, 'rejected')"
            >
              {{ t('common.reject') }}
            </AppButton>
            <AppButton
              v-if="auth.can(PERMISSIONS.purchasesManage) && !row.received_at && row.approval_status !== 'rejected' && row.approval_status !== 'pending'"
              variant="ghost"
              size="sm"
              class="text-sm text-brand-700"
              :loading="rowActions.isBusy(`receive-${row.id}`)"
              @click="receive(row)"
            >
              {{ t('purchases.receive') }}
            </AppButton>
          </div>
        </template>
      </AppTable>
    </div>
  </div>
</template>

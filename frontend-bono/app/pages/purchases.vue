<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({ permissions: [PERMISSIONS.purchasesView, PERMISSIONS.purchasesManage, PERMISSIONS.purchasesApprove] })

const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const rows = ref<any[]>([])
const suppliers = ref<any[]>([])
const items = ref<any[]>([])
const loading = ref(true)
const form = reactive({
  supplier_id: '',
  purchase_date: new Date().toISOString().slice(0, 10),
  inventory_item_id: '',
  quantity: '1',
  unit_cost: '0',
  notes: '',
})

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
    toast.success('Purchase requested — waiting for owner approval')
    await load()
  }
  catch (error: any) {
    toast.error(error.message)
  }
}

async function decide(row: any, decision: 'approved' | 'rejected') {
  try {
    await api.decidePurchase(row.id, { decision })
    toast.success(decision === 'approved' ? 'Purchase approved' : 'Purchase rejected')
    await load()
  }
  catch (error: any) {
    toast.error(error.message)
  }
}

async function receive(row: any) {
  try {
    await api.receivePurchase(row.id)
    toast.success('Accepted quantities added to store stock')
    await load()
  }
  catch (error: any) {
    toast.error(error.message)
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="Purchases"
      description="Store requests purchase orders for low stock. Admin or owner approves before goods can be received."
    />

    <form
      v-if="auth.can(PERMISSIONS.purchasesManage)"
      class="card mb-4 grid gap-2 p-4 md:grid-cols-3"
      @submit.prevent="create"
    >
      <select v-model="form.supplier_id" class="input" required>
        <option disabled value="">Supplier</option>
        <option v-for="supplier in suppliers" :key="supplier.id" :value="String(supplier.id)">
          {{ supplier.name }}
        </option>
      </select>
      <input v-model="form.purchase_date" class="input" type="date" required>
      <select v-model="form.inventory_item_id" class="input" required>
        <option disabled value="">Inventory item</option>
        <option v-for="item in items" :key="item.id" :value="String(item.id)">
          {{ item.name }} · on hand {{ item.quantity_on_hand }}
        </option>
      </select>
      <input v-model="form.quantity" class="input" type="number" min="0.001" step="0.001" placeholder="Qty" required>
      <input v-model="form.unit_cost" class="input" type="number" min="0" step="0.01" placeholder="Unit cost" required>
      <input v-model="form.notes" class="input" placeholder="Notes (e.g. low stock)">
      <button class="btn-primary md:col-span-3" type="submit">Request purchase</button>
    </form>

    <div class="card">
      <AppTable
        :loading="loading"
        :rows="rows"
        :columns="[
          { key: 'purchase_number', label: 'PO' },
          { key: 'purchase_date', label: 'Date' },
          { key: 'total', label: 'Total', align: 'right' },
          { key: 'payment_status', label: 'Payment' },
          { key: 'approval_status', label: 'Approval' },
          { key: 'actions', label: '' },
        ]"
      >
        <template #cell:payment_status="{ row }"><StatusBadge :status="row.payment_status" /></template>
        <template #cell:approval_status="{ row }"><StatusBadge :status="row.approval_status ?? 'approved'" /></template>
        <template #cell:total="{ row }">{{ formatAmount(row.total) }}</template>
        <template #cell:actions="{ row }">
          <div class="flex flex-wrap justify-end gap-2">
            <button
              v-if="auth.can(PERMISSIONS.purchasesApprove) && row.approval_status === 'pending'"
              class="text-sm text-brand-700"
              type="button"
              @click="decide(row, 'approved')"
            >
              Approve
            </button>
            <button
              v-if="auth.can(PERMISSIONS.purchasesApprove) && row.approval_status === 'pending'"
              class="text-sm text-red-600"
              type="button"
              @click="decide(row, 'rejected')"
            >
              Reject
            </button>
            <button
              v-if="auth.can(PERMISSIONS.purchasesManage) && !row.received_at && row.approval_status !== 'rejected' && row.approval_status !== 'pending'"
              class="text-sm text-brand-700"
              type="button"
              @click="receive(row)"
            >
              Receive
            </button>
          </div>
        </template>
      </AppTable>
    </div>
  </div>
</template>

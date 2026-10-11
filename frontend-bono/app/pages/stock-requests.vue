<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({ permissions: [PERMISSIONS.stockRequest, PERMISSIONS.stockIssue, PERMISSIONS.stockConfirm] })

const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const rows = ref<any[]>([])
const items = ref<any[]>([])
const approvingId = ref<number | null>(null)
const approveQty = ref<Record<number, string>>({})
const form = reactive({
  destination: auth.role === 'kitchen' ? 'kitchen' : 'bar',
  inventory_item_id: '',
  quantity: '1',
})

async function load() {
  rows.value = (await api.stockRequests() as any).data
  if (auth.can(PERMISSIONS.stockRequest) || auth.can(PERMISSIONS.stockIssue)) {
    items.value = (await api.inventory({ per_page: 100 }) as any).data
    if (!form.inventory_item_id && items.value[0]) form.inventory_item_id = String(items.value[0].id)
  }
}

async function create() {
  try {
    await api.createStockRequest({
      destination: form.destination,
      lines: [{ inventory_item_id: Number(form.inventory_item_id), quantity: form.quantity }],
    })
    toast.success('Request sent to the store')
    await load()
  }
  catch (error: any) { toast.error(error.message) }
}

function startApprove(row: any) {
  approvingId.value = row.id
  const next: Record<number, string> = {}
  for (const line of row.lines ?? []) {
    next[line.id] = String(line.approved_quantity ?? line.requested_quantity ?? '0')
  }
  approveQty.value = next
}

async function approveAndIssue(row: any) {
  try {
    const lines = (row.lines ?? []).map((line: any) => ({
      id: line.id,
      approved_quantity: approveQty.value[line.id] ?? line.requested_quantity,
    }))
    await api.approveStockRequest(row.id, { lines })
    await api.issueStockRequest(row.id, {
      lines: lines.map((line: any) => ({
        id: line.id,
        issued_quantity: line.approved_quantity,
      })),
    })
    toast.success('Issued from store. Confirm receipt at bar/kitchen.')
    approvingId.value = null
    await load()
  }
  catch (error: any) { toast.error(error.message) }
}

async function confirm(row: any) {
  try {
    await api.confirmStockRequest(row.id, {
      lines: row.lines.map((line: any) => ({ id: line.id, received_quantity: line.issued_quantity })),
    })
    toast.success('Receipt confirmed')
    await load()
  }
  catch (error: any) { toast.error(error.message) }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="Stock requests"
      description="Bar and kitchen request inventory from the store. Store can lower the quantity before issuing."
    />
    <form v-if="auth.can(PERMISSIONS.stockRequest)" class="card mb-4 grid gap-2 p-4 md:grid-cols-4" @submit.prevent="create">
      <select v-model="form.destination" class="input">
        <option value="bar">Bar</option>
        <option value="kitchen">Kitchen</option>
      </select>
      <select v-model="form.inventory_item_id" class="input">
        <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
      </select>
      <input v-model="form.quantity" class="input" placeholder="Quantity">
      <button class="btn-primary" type="submit">Request from store</button>
    </form>
    <div class="card">
      <AppTable
        :rows="rows"
        :columns="[
          { key: 'reference', label: 'Ref' },
          { key: 'status', label: 'Status' },
          { key: 'requester', label: 'Requester' },
          { key: 'qty', label: 'Qty' },
          { key: 'actions', label: '' },
        ]"
      >
        <template #cell:status="{ row }"><StatusBadge :status="row.status" /></template>
        <template #cell:requester="{ row }">{{ row.requester?.name }}</template>
        <template #cell:qty="{ row }">
          <span v-for="line in row.lines" :key="line.id" class="block text-sm">
            {{ line.item?.name || line.inventory_item_id }}: {{ line.requested_quantity }}
            <template v-if="line.approved_quantity != null"> → {{ line.approved_quantity }}</template>
          </span>
        </template>
        <template #cell:actions="{ row }">
          <div class="flex min-w-[12rem] flex-col items-end gap-2">
            <button
              v-if="auth.can(PERMISSIONS.stockIssue) && row.status === 'submitted' && approvingId !== row.id"
              class="text-sm text-brand-700"
              type="button"
              @click="startApprove(row)"
            >
              Review &amp; issue
            </button>
            <div v-if="approvingId === row.id" class="w-full space-y-2 rounded-lg bg-slate-50 p-2">
              <div v-for="line in row.lines" :key="line.id" class="space-y-1">
                <p class="text-xs text-slate-600">Requested {{ line.requested_quantity }}</p>
                <input
                  v-model="approveQty[line.id]"
                  class="input text-sm"
                  type="number"
                  min="0"
                  :max="line.requested_quantity"
                  step="0.001"
                >
              </div>
              <div class="flex justify-end gap-2">
                <button class="btn-secondary text-sm" type="button" @click="approvingId = null">Cancel</button>
                <button class="btn-primary text-sm" type="button" @click="approveAndIssue(row)">Approve &amp; issue</button>
              </div>
            </div>
            <button
              v-if="auth.can(PERMISSIONS.stockConfirm) && row.status === 'awaiting_confirmation' && row.requester_id === auth.user?.id"
              class="text-sm text-brand-700"
              type="button"
              @click="confirm(row)"
            >
              Confirm receipt
            </button>
          </div>
        </template>
      </AppTable>
    </div>
  </div>
</template>

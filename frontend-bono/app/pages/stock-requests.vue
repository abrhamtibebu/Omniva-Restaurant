<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({ permissions: [PERMISSIONS.stockRequest, PERMISSIONS.stockIssue, PERMISSIONS.stockConfirm] })

const { t } = useI18n()
const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const { isTelegram } = useTelegramWebApp()
const rows = ref<any[]>([])
const items = ref<any[]>([])
const approvingId = ref<number | null>(null)
const approveQty = ref<Record<number, string>>({})
const { busy: creating, run: runCreate } = useBusyAction()
const rowActions = useBusyMap()
const form = reactive({
  destination: auth.role === 'kitchen' ? 'kitchen' : 'bar',
  inventory_item_id: '',
  quantity: '1',
})

const columns = computed(() => [
  { key: 'reference', label: t('common.reference') },
  { key: 'status', label: t('common.status') },
  { key: 'requester', label: t('common.requester') },
  { key: 'qty', label: t('common.qty') },
  { key: 'actions', label: '' },
])

function initialsOf(name?: string | null) {
  const parts = String(name || '?').trim().split(/\s+/).filter(Boolean)
  if (parts.length >= 2) return `${parts[0]![0]}${parts[1]![0]}`.toUpperCase()
  return String(name || '?').slice(0, 2).toUpperCase()
}

function qtySummary(row: any) {
  return (row.lines ?? [])
    .map((line: any) => `${line.item?.name || line.inventory_item_id}: ${line.requested_quantity}`)
    .join(' · ')
}

async function load() {
  rows.value = (await api.stockRequests() as any).data
  if (auth.can(PERMISSIONS.stockRequest) || auth.can(PERMISSIONS.stockIssue)) {
    items.value = (await api.inventory({ per_page: 100 }) as any).data
    if (!form.inventory_item_id && items.value[0]) form.inventory_item_id = String(items.value[0].id)
  }
}

async function create() {
  await runCreate(async () => {
    try {
      await api.createStockRequest({
        destination: form.destination,
        lines: [{ inventory_item_id: Number(form.inventory_item_id), quantity: form.quantity }],
      })
      toast.success(t('stock.requestSent'))
      await load()
    }
    catch (error: any) { toast.error(error.message) }
  })
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
  await rowActions.run(`approve-${row.id}`, async () => {
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
      toast.success(t('stock.issuedConfirm'))
      approvingId.value = null
      await load()
    }
    catch (error: any) { toast.error(error.message) }
  })
}

async function confirm(row: any) {
  await rowActions.run(`confirm-${row.id}`, async () => {
    try {
      await api.confirmStockRequest(row.id, {
        lines: row.lines.map((line: any) => ({ id: line.id, received_quantity: line.issued_quantity })),
      })
      toast.success(t('stock.receiptConfirmed'))
      await load()
    }
    catch (error: any) { toast.error(error.message) }
  })
}

onMounted(() => {
  void load()
  if (import.meta.client && window.location.hash === '#create') {
    nextTick(() => document.getElementById('create')?.scrollIntoView({ behavior: 'smooth' }))
  }
})
</script>

<template>
  <div class="space-y-4">
    <PageHeader
      :title="t('stock.title')"
      :description="t('stock.description')"
    />

    <form
      v-if="auth.can(PERMISSIONS.stockRequest)"
      id="create"
      class="mb-4 grid gap-2 p-4 md:grid-cols-4 tg-stack"
      :class="isTelegram ? 'tg-soft-card' : 'card'"
      @submit.prevent="create"
    >
      <p v-if="isTelegram" class="tg-section-label md:col-span-4">{{ t('stock.requestFromStore') }}</p>
      <select v-model="form.destination" class="input">
        <option value="bar">{{ t('departments.bar') }}</option>
        <option value="kitchen">{{ t('departments.kitchen') }}</option>
      </select>
      <select v-model="form.inventory_item_id" class="input">
        <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
      </select>
      <input v-model="form.quantity" class="input" :placeholder="t('common.quantity')">
      <AppButton type="submit" :loading="creating">{{ t('stock.requestFromStore') }}</AppButton>
    </form>

    <template v-if="isTelegram">
      <div class="tg-soft-card !py-1">
        <p class="tg-section-label px-0 pt-2">{{ t('stock.title') }}</p>
        <div v-for="row in rows" :key="row.id" class="tg-meta-row !items-start">
          <div class="tg-meta-avatar">{{ initialsOf(row.requester?.name) }}</div>
          <div class="tg-meta-body space-y-1">
            <p class="tg-meta-title">{{ row.reference }}</p>
            <p class="tg-meta-sub">{{ row.requester?.name }} · {{ qtySummary(row) }}</p>
            <StatusBadge :status="row.status" />
            <div v-if="approvingId === row.id" class="space-y-2 pt-1">
              <div v-for="line in row.lines" :key="line.id" class="space-y-1">
                <p class="text-xs opacity-70">{{ t('stock.requestedQty', { qty: line.requested_quantity }) }}</p>
                <input v-model="approveQty[line.id]" class="input text-sm" type="number" min="0" :max="line.requested_quantity" step="0.001">
              </div>
              <div class="flex gap-2">
                <button class="btn-secondary flex-1" type="button" @click="approvingId = null">{{ t('common.cancel') }}</button>
                <AppButton class="flex-1" size="sm" :loading="rowActions.isBusy(`approve-${row.id}`)" @click="approveAndIssue(row)">{{ t('stock.approveAndIssue') }}</AppButton>
              </div>
            </div>
            <div v-else class="flex flex-wrap gap-2 pt-1">
              <button
                v-if="auth.can(PERMISSIONS.stockIssue) && row.status === 'submitted'"
                class="text-sm font-semibold text-[color:var(--tg-theme-button-color,#2481cc)]"
                type="button"
                @click="startApprove(row)"
              >
                {{ t('stock.reviewAndIssue') }}
              </button>
              <AppButton
                v-if="auth.can(PERMISSIONS.stockConfirm) && row.status === 'awaiting_confirmation' && row.requester_id === auth.user?.id"
                size="sm"
                :loading="rowActions.isBusy(`confirm-${row.id}`)"
                @click="confirm(row)"
              >
                {{ t('stock.confirmReceipt') }}
              </AppButton>
            </div>
          </div>
        </div>
        <p v-if="!rows.length" class="py-3 text-sm opacity-60">{{ t('common.nothingHere') }}</p>
      </div>
    </template>

    <div v-else class="card">
      <AppTable
        :rows="rows"
        :columns="columns"
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
              {{ t('stock.reviewAndIssue') }}
            </button>
            <div v-if="approvingId === row.id" class="w-full space-y-2 rounded-lg bg-slate-50 p-2">
              <div v-for="line in row.lines" :key="line.id" class="space-y-1">
                <p class="text-xs text-slate-600">{{ t('stock.requestedQty', { qty: line.requested_quantity }) }}</p>
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
                <button class="btn-secondary text-sm" type="button" @click="approvingId = null">{{ t('common.cancel') }}</button>
                <AppButton size="sm" :loading="rowActions.isBusy(`approve-${row.id}`)" @click="approveAndIssue(row)">{{ t('stock.approveAndIssue') }}</AppButton>
              </div>
            </div>
            <AppButton
              v-if="auth.can(PERMISSIONS.stockConfirm) && row.status === 'awaiting_confirmation' && row.requester_id === auth.user?.id"
              variant="ghost"
              size="sm"
              class="text-sm text-brand-700"
              :loading="rowActions.isBusy(`confirm-${row.id}`)"
              @click="confirm(row)"
            >
              {{ t('stock.confirmReceipt') }}
            </AppButton>
          </div>
        </template>
      </AppTable>
    </div>
  </div>
</template>

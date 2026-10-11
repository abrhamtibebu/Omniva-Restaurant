<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({
  permissions: [PERMISSIONS.bonosRequest, PERMISSIONS.bonosConfirm, PERMISSIONS.bonosIssue],
})

const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()

const types = ref<any[]>([])
const handovers = ref<any[]>([])
const cashiers = ref<any[]>([])
const rejectReason = ref('')
const rejectingId = ref<number | null>(null)
const approvingId = ref<number | null>(null)
const approveQty = ref<Record<number, string>>({})

const requestForm = reactive({ bono_type_id: '', quantity: '1' })
const returnForm = reactive({ receiver_id: '', bono_type_id: '', quantity: '1' })

const canRequest = computed(() => auth.can(PERMISSIONS.bonosRequest))
const canReturn = computed(() => canRequest.value && ['waiter', 'barman'].includes(auth.role ?? ''))
const canConfirm = computed(() => auth.can(PERMISSIONS.bonosConfirm))
const isOwnerApprover = computed(() => ['admin', 'manager'].includes(auth.role ?? ''))
const canApproveFloor = computed(() => isOwnerApprover.value || (auth.role === 'cashier' && auth.can(PERMISSIONS.bonosIssue)))
const canApproveCashier = computed(() => isOwnerApprover.value)

const requestHelp = computed(() => {
  if (auth.role === 'cashier') return 'Admin or owner will approve and issue from central stock.'
  if (['waiter', 'barman'].includes(auth.role ?? '')) return 'Cashier or admin will approve — they can lower the quantity if stock is short.'
  return 'Submit a request for approval.'
})

function canApproveRow(row: any): boolean {
  if (row.status !== 'submitted') return false
  if (row.kind === 'cashier_request') return canApproveCashier.value
  return canApproveFloor.value
}

function startApprove(row: any) {
  approvingId.value = row.id
  const next: Record<number, string> = {}
  for (const line of row.lines ?? []) {
    next[line.id] = String(line.approved_quantity ?? line.requested_quantity ?? '0')
  }
  approveQty.value = next
}

async function loadPeople() {
  if (!canReturn.value) return
  try {
    const cash = await api.bonoPeople({ role: 'cashier' })
    cashiers.value = (cash as any).data ?? []
    if (!returnForm.receiver_id && cashiers.value[0]) {
      returnForm.receiver_id = String(cashiers.value[0].id)
    }
  }
  catch {
    // picker stays empty
  }
}

async function load() {
  try {
    const [t, h] = await Promise.all([api.bonoTypes(), api.bonoHandovers()])
    types.value = (t as any).data ?? []
    handovers.value = (h as any).data ?? []
    if (!requestForm.bono_type_id && types.value[0]) requestForm.bono_type_id = String(types.value[0].id)
    if (!returnForm.bono_type_id && types.value[0]) returnForm.bono_type_id = String(types.value[0].id)
  }
  catch (error: any) {
    toast.error(error.message)
  }
  await loadPeople()
}

async function requestBonos() {
  try {
    await api.createBonoHandover({
      kind: 'request',
      lines: [{ bono_type_id: Number(requestForm.bono_type_id), quantity: requestForm.quantity }],
    })
    toast.success(auth.role === 'cashier' ? 'Request sent to admin / owner' : 'Request sent for approval')
    await load()
  }
  catch (error: any) {
    toast.error(error.message)
  }
}

async function returnBonos() {
  if (!returnForm.receiver_id) {
    toast.error('Choose the cashier who will receive the return.')
    return
  }
  try {
    await api.createBonoHandover({
      kind: 'return',
      receiver_id: Number(returnForm.receiver_id),
      lines: [{ bono_type_id: Number(returnForm.bono_type_id), quantity: returnForm.quantity }],
    })
    toast.success('Return sent. Waiting for the cashier to confirm.')
    await load()
  }
  catch (error: any) {
    toast.error(error.message)
  }
}

async function approveAndIssue(row: any) {
  try {
    const lines = (row.lines ?? []).map((line: any) => ({
      id: line.id,
      approved_quantity: approveQty.value[line.id] ?? line.requested_quantity,
    }))
    await api.approveBonoHandover(row.id, { lines })
    await api.issueBonoHandover(row.id, {
      lines: lines.map((line: any) => ({
        id: line.id,
        issued_quantity: line.approved_quantity,
      })),
    })
    toast.success('Approved and issued. Waiting for the receiver to confirm.')
    approvingId.value = null
    await load()
  }
  catch (error: any) {
    toast.error(error.message)
  }
}

async function reject(row: any) {
  if (!rejectReason.value.trim()) {
    toast.error('Enter a reject reason.')
    return
  }
  try {
    await api.rejectBonoHandover(row.id, { reason: rejectReason.value.trim() })
    toast.success('Request rejected')
    rejectingId.value = null
    rejectReason.value = ''
    await load()
  }
  catch (error: any) {
    toast.error(error.message)
  }
}

async function confirm(row: any) {
  try {
    await api.confirmBonoHandover(row.id, {
      lines: row.lines.map((line: any) => ({ id: line.id, received_quantity: line.issued_quantity })),
    })
    toast.success('Receipt confirmed')
    await load()
  }
  catch (error: any) {
    toast.error(error.message)
  }
}

function kindLabel(kind: string): string {
  return ({
    cashier_request: 'Cashier request',
    waiter_request: 'Waiter request',
    bar_request: 'Bar request',
    waiter_return: 'Waiter return',
    bar_return: 'Bar return',
    admin_to_cashier: 'Admin issue',
    cashier_to_waiter: 'Cashier issue',
  } as Record<string, string>)[kind] ?? kind
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="Bono requests"
      description="Cashiers request from admin/owner. Waiters request from cashier or admin. Approvers can lower the quantity before issuing."
    />

    <div class="mb-4 grid gap-4 lg:grid-cols-2">
      <div v-if="canRequest" class="card space-y-2 p-4">
        <h2 class="font-semibold">Request bonos</h2>
        <p class="text-sm text-slate-600">{{ requestHelp }}</p>
        <select v-model="requestForm.bono_type_id" class="input">
          <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
        </select>
        <input v-model="requestForm.quantity" class="input" placeholder="Quantity" type="number" min="1" step="1">
        <button class="btn-primary" type="button" @click="requestBonos">
          Submit request
        </button>
      </div>

      <div v-if="canReturn" class="card space-y-2 p-4">
        <h2 class="font-semibold">Return unused</h2>
        <p class="text-sm text-slate-600">Hand unused bonos back to a cashier. Custody moves after they confirm.</p>
        <select v-model="returnForm.bono_type_id" class="input">
          <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
        </select>
        <select v-model="returnForm.receiver_id" class="input" required>
          <option disabled value="">Select cashier</option>
          <option v-for="person in cashiers" :key="person.id" :value="String(person.id)">
            {{ person.name }}
          </option>
        </select>
        <input v-model="returnForm.quantity" class="input" placeholder="Unused quantity" type="number" min="1" step="1">
        <button class="btn-secondary" type="button" @click="returnBonos">
          Return unused
        </button>
      </div>
    </div>

    <div class="card">
      <AppTable
        :rows="handovers"
        :columns="[
          { key: 'reference', label: 'Ref' },
          { key: 'kind', label: 'Kind' },
          { key: 'parties', label: 'From → To' },
          { key: 'qty', label: 'Qty' },
          { key: 'status', label: 'Status' },
          { key: 'actions', label: '' },
        ]"
      >
        <template #cell:kind="{ row }">{{ kindLabel(row.kind) }}</template>
        <template #cell:parties="{ row }">
          {{ row.issuer?.name || '—' }} → {{ row.receiver?.name || '—' }}
        </template>
        <template #cell:qty="{ row }">
          <span v-for="line in row.lines" :key="line.id" class="block text-sm">
            {{ line.bono_type?.name }}:
            {{ line.requested_quantity }}
            <template v-if="line.approved_quantity != null"> → {{ line.approved_quantity }} approved</template>
            <template v-if="line.issued_quantity != null"> → {{ line.issued_quantity }} issued</template>
          </span>
        </template>
        <template #cell:status="{ row }">
          <StatusBadge :status="row.status" />
        </template>
        <template #cell:actions="{ row }">
          <div class="flex min-w-[14rem] flex-col items-end gap-2">
            <button
              v-if="canApproveRow(row) && approvingId !== row.id"
              class="text-sm text-brand-700"
              type="button"
              @click="startApprove(row)"
            >
              Review &amp; issue
            </button>
            <div v-if="approvingId === row.id" class="w-full space-y-2 rounded-lg bg-slate-50 p-2">
              <div v-for="line in row.lines" :key="line.id" class="space-y-1">
                <p class="text-xs text-slate-600">
                  {{ line.bono_type?.name }} — requested {{ line.requested_quantity }}
                </p>
                <input
                  v-model="approveQty[line.id]"
                  class="input text-sm"
                  type="number"
                  min="0"
                  :max="line.requested_quantity"
                  step="1"
                >
              </div>
              <div class="flex justify-end gap-2">
                <button class="btn-secondary text-sm" type="button" @click="approvingId = null">Cancel</button>
                <button class="btn-primary text-sm" type="button" @click="approveAndIssue(row)">
                  Approve &amp; issue
                </button>
              </div>
            </div>
            <button
              v-if="canApproveRow(row)"
              class="text-sm text-red-600"
              type="button"
              @click="rejectingId = row.id"
            >
              Reject
            </button>
            <div v-if="rejectingId === row.id" class="flex gap-1">
              <input v-model="rejectReason" class="input text-sm" placeholder="Reason">
              <button class="btn-secondary text-sm" type="button" @click="reject(row)">
                Send
              </button>
            </div>
            <button
              v-if="canConfirm && row.status === 'awaiting_confirmation' && row.receiver_id === auth.user?.id"
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

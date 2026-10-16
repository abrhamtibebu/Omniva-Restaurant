<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({
  permissions: [PERMISSIONS.bonosRequest, PERMISSIONS.bonosConfirm, PERMISSIONS.bonosIssue],
})

const { t, te } = useI18n()
const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const { haptic, isTelegram } = useTelegramWebApp()
const viewMode = ref<'needs' | 'all'>('needs')
const statusFilter = ref<'confirm' | 'issue' | 'waiting' | 'other'>('confirm')

const types = ref<any[]>([])
const handovers = ref<any[]>([])
const balances = ref<any[]>([])
const cashiers = ref<any[]>([])
const rejectReason = ref('')
const rejectingId = ref<number | null>(null)
const approvingId = ref<number | null>(null)
const issuingId = ref<number | null>(null)
const confirmingId = ref<number | null>(null)
const approveQty = ref<Record<number, string>>({})
const issueQty = ref<Record<number, string>>({})
const receiveQty = ref<Record<number, string>>({})
const confirmingBusy = ref(false)
const issuingBusy = ref(false)
const { busy: requestBusy, run: runRequest } = useBusyAction()
const { busy: returnBusy, run: runReturn } = useBusyAction()
const approveActions = useBusyMap()
const rejectActions = useBusyMap()

/** Bono counts are whole units — never show trailing .000 */
function formatQty(value: unknown): string {
  const n = Number(value)
  if (!Number.isFinite(n)) return '0'
  return String(Math.round(n))
}

const requestForm = reactive({ bono_type_id: '', quantity: '1' })
const returnForm = reactive({ receiver_id: '', bono_type_id: '', quantity: '1' })

const canRequest = computed(() => auth.can(PERMISSIONS.bonosRequest))
const canReturn = computed(() => canRequest.value && ['waiter', 'barman'].includes(auth.role ?? ''))
const canConfirm = computed(() => auth.can(PERMISSIONS.bonosConfirm))
const isOwnerApprover = computed(() => ['admin', 'manager'].includes(auth.role ?? ''))
const canApproveFloor = computed(() => isOwnerApprover.value || (auth.role === 'cashier' && auth.can(PERMISSIONS.bonosIssue)))
const canApproveCashier = computed(() => isOwnerApprover.value)
const showOnHand = computed(() => ['waiter', 'barman', 'cashier'].includes(auth.role ?? ''))

const requestHelp = computed(() => {
  if (auth.role === 'cashier') return t('requests.helpCashier')
  if (['waiter', 'barman'].includes(auth.role ?? '')) return t('requests.helpFloor')
  return t('requests.helpDefault')
})

const myBalances = computed(() =>
  balances.value.filter((row) =>
    row.holder_type === 'user'
    && Number(row.holder_id) === Number(auth.user?.id)
    && Number(row.quantity) > 0,
  ),
)

const pendingConfirm = computed(() =>
  handovers.value.filter((row) => canConfirmRow(row)),
)

const awaitingIssue = computed(() =>
  handovers.value.filter((row) => isAwaitingIssueForMe(row)),
)

/** Cashier/owner: approved requests that still need to be handed over. */
const needsIssue = computed(() =>
  handovers.value.filter((row) => canIssueRow(row)),
)

const otherHandovers = computed(() =>
  handovers.value.filter((row) =>
    !canConfirmRow(row)
    && !isAwaitingIssueForMe(row)
    && !canIssueRow(row),
  ),
)

const needsYouCount = computed(() =>
  pendingConfirm.value.length + needsIssue.value.length + awaitingIssue.value.length,
)

const filteredOther = computed(() => {
  if (statusFilter.value === 'confirm') return []
  if (statusFilter.value === 'issue') return needsIssue.value
  if (statusFilter.value === 'waiting') return awaitingIssue.value
  return otherHandovers.value
})

function initialsOf(name?: string | null) {
  const parts = String(name || '?').trim().split(/\s+/).filter(Boolean)
  if (parts.length >= 2) return `${parts[0]![0]}${parts[1]![0]}`.toUpperCase()
  return String(name || '?').slice(0, 2).toUpperCase()
}

watch(
  () => [pendingConfirm.value.length, needsIssue.value.length, awaitingIssue.value.length] as const,
  ([confirm, issue, waiting]) => {
    if (confirm > 0) statusFilter.value = 'confirm'
    else if (issue > 0) statusFilter.value = 'issue'
    else if (waiting > 0) statusFilter.value = 'waiting'
    else statusFilter.value = 'other'
    if (confirm + issue + waiting === 0) viewMode.value = 'all'
  },
  { immediate: true },
)

const mainButtonText = computed(() =>
  pendingConfirm.value.length > 0 ? t('requests.mainConfirm') : t('requests.mainRequest'),
)

const mainButtonEnabled = computed(() => {
  if (pendingConfirm.value.length > 0) return true
  return canRequest.value && Boolean(requestForm.bono_type_id)
})

const columns = computed(() => [
  { key: 'reference', label: t('common.reference') },
  { key: 'kind', label: t('common.kind') },
  { key: 'parties', label: t('common.fromTo') },
  { key: 'qty', label: t('common.qty') },
  { key: 'status', label: t('common.status') },
  { key: 'actions', label: '' },
])

function handoverStatus(row: any): string {
  return String(row?.status ?? '')
}

function isHandoverReceiver(row: any): boolean {
  const me = Number(auth.user?.id)
  if (!Number.isFinite(me) || me <= 0) return false
  const receiverId = Number(row?.receiver_id ?? row?.receiver?.id)
  return Number.isFinite(receiverId) && receiverId === me
}

function canApproveKind(row: any): boolean {
  if (row.kind === 'cashier_request') return canApproveCashier.value
  return canApproveFloor.value
}

function canApproveRow(row: any): boolean {
  return handoverStatus(row) === 'submitted' && canApproveKind(row)
}

/** Approved but not yet issued — cashier must finish issue before waiter can accept. */
function canIssueRow(row: any): boolean {
  const status = handoverStatus(row)
  if (status !== 'approved' && status !== 'partially_approved') return false
  return canApproveKind(row)
}

function isAwaitingIssueForMe(row: any): boolean {
  if (!isHandoverReceiver(row)) return false
  const status = handoverStatus(row)
  return status === 'approved' || status === 'partially_approved'
}

function canConfirmRow(row: any): boolean {
  if (handoverStatus(row) !== 'awaiting_confirmation') return false
  if (!isHandoverReceiver(row)) return false
  // API allows confirm permission or request+receiver; keep UI aligned.
  return canConfirm.value || canRequest.value
}

function startApprove(row: any) {
  approvingId.value = row.id
  issuingId.value = null
  const next: Record<number, string> = {}
  for (const line of row.lines ?? []) {
    next[line.id] = formatQty(line.approved_quantity ?? line.requested_quantity ?? 0)
  }
  approveQty.value = next
}

function startIssue(row: any) {
  issuingId.value = row.id
  approvingId.value = null
  const next: Record<number, string> = {}
  for (const line of row.lines ?? []) {
    next[line.id] = formatQty(line.approved_quantity ?? line.requested_quantity ?? 0)
  }
  issueQty.value = next
}

function startConfirm(row: any) {
  confirmingId.value = row.id
  const next: Record<number, string> = {}
  for (const line of row.lines ?? []) {
    next[line.id] = formatQty(line.issued_quantity ?? line.approved_quantity ?? 0)
  }
  receiveQty.value = next
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
    const [typeRes, handoverRes, balRes] = await Promise.all([
      api.bonoTypes(),
      api.bonoHandovers(),
      api.bonoBalances(),
    ])
    types.value = (typeRes as any).data ?? []
    handovers.value = (handoverRes as any).data ?? []
    balances.value = (balRes as any).data ?? []
    if (!requestForm.bono_type_id && types.value[0]) requestForm.bono_type_id = String(types.value[0].id)
    if (!returnForm.bono_type_id && types.value[0]) returnForm.bono_type_id = String(types.value[0].id)
  }
  catch (error: any) {
    toast.error(error.message)
  }
  await loadPeople()
}

async function requestBonos() {
  await runRequest(async () => {
    try {
      await api.createBonoHandover({
        kind: 'request',
        lines: [{ bono_type_id: Number(requestForm.bono_type_id), quantity: requestForm.quantity }],
      })
      haptic('success')
      toast.success(auth.role === 'cashier' ? t('requests.requestSentAdmin') : t('requests.requestSentApproval'))
      await load()
    }
    catch (error: any) {
      haptic('error')
      toast.error(error.message)
    }
  })
}

async function returnBonos() {
  if (!returnForm.receiver_id) {
    toast.error(t('requests.chooseCashierReturn'))
    return
  }
  await runReturn(async () => {
    try {
      await api.createBonoHandover({
        kind: 'return',
        receiver_id: Number(returnForm.receiver_id),
        lines: [{ bono_type_id: Number(returnForm.bono_type_id), quantity: returnForm.quantity }],
      })
      haptic('success')
      toast.success(t('requests.returnSent'))
      await load()
    }
    catch (error: any) {
      haptic('error')
      toast.error(error.message)
    }
  })
}

async function approveAndIssue(row: any) {
  await approveActions.run(row.id, async () => {
    try {
      const lines = (row.lines ?? []).map((line: any) => ({
        id: line.id,
        approved_quantity: formatQty(approveQty.value[line.id] ?? line.requested_quantity),
        issued_quantity: formatQty(approveQty.value[line.id] ?? line.requested_quantity),
      }))
      try {
        await api.approveAndIssueBonoHandover(row.id, { lines })
      }
      catch (error: any) {
        // Older API builds only expose separate approve + issue calls.
        if (error?.status !== 404 && error?.status !== 405) throw error
        await api.approveBonoHandover(row.id, { lines })
        await api.issueBonoHandover(row.id, {
          lines: lines.map((line: any) => ({
            id: line.id,
            issued_quantity: line.issued_quantity,
          })),
        })
      }
      haptic('success')
      toast.success(t('requests.approvedIssued'))
      approvingId.value = null
      await load()
    }
    catch (error: any) {
      haptic('error')
      toast.error(error.message)
    }
  })
}

async function issueOnly(row: any) {
  if (issuingBusy.value) return
  issuingBusy.value = true
  try {
    const lines = (row.lines ?? []).map((line: any) => ({
      id: line.id,
      issued_quantity: formatQty(issueQty.value[line.id] ?? line.approved_quantity ?? line.requested_quantity),
    }))
    await api.issueBonoHandover(row.id, { lines })
    haptic('success')
    toast.success(t('requests.issuedForConfirm'))
    issuingId.value = null
    await load()
  }
  catch (error: any) {
    haptic('error')
    toast.error(error.message)
  }
  finally {
    issuingBusy.value = false
  }
}

async function reject(row: any) {
  if (!rejectReason.value.trim()) {
    toast.error(t('requests.enterRejectReason'))
    return
  }
  await rejectActions.run(row.id, async () => {
    try {
      await api.rejectBonoHandover(row.id, { reason: rejectReason.value.trim() })
      toast.success(t('requests.requestRejected'))
      rejectingId.value = null
      rejectReason.value = ''
      await load()
    }
    catch (error: any) {
      toast.error(error.message)
    }
  })
}

function receiptLines(row: any) {
  return (row.lines ?? []).map((line: any) => ({
    id: line.id,
    received_quantity: receiveQty.value[line.id]
      ?? line.issued_quantity
      ?? line.approved_quantity
      ?? line.requested_quantity
      ?? '0',
  }))
}

async function confirm(row: any) {
  if (confirmingBusy.value) return
  confirmingBusy.value = true
  try {
    await api.confirmBonoHandover(row.id, { lines: receiptLines(row) })
    haptic('success')
    toast.success(t('requests.receiptConfirmedBalance'))
    confirmingId.value = null
    await load()
  }
  catch (error: any) {
    haptic('error')
    toast.error(error.message)
  }
  finally {
    confirmingBusy.value = false
  }
}

/** One-tap accept using issued quantities (optional adjust stays available). */
async function confirmAsIssued(row: any) {
  startConfirm(row)
  await confirm(row)
}

function kindLabel(kind: string): string {
  const key = `handoverKinds.${kind}`
  return te(key) ? t(key) : kind
}

function qtyLabel(line: any): string {
  const parts = [t('requests.qtyReq', { qty: formatQty(line.requested_quantity) })]
  if (line.approved_quantity != null && Number(line.approved_quantity) > 0) {
    parts.push(t('requests.qtyOk', { qty: formatQty(line.approved_quantity) }))
  }
  if (line.issued_quantity != null && Number(line.issued_quantity) > 0) {
    parts.push(t('requests.qtyOut', { qty: formatQty(line.issued_quantity) }))
  }
  if (line.received_quantity != null && Number(line.received_quantity) > 0) {
    parts.push(t('requests.qtyGot', { qty: formatQty(line.received_quantity) }))
  }
  return `${line.bono_type?.name}: ${parts.join(' → ')}`
}

async function mainButtonAction() {
  if (pendingConfirm.value.length > 0) {
    await confirmAsIssued(pendingConfirm.value[0])
    return
  }
  await requestBonos()
}

useTelegramMainButton(mainButtonText, () => mainButtonAction(), mainButtonEnabled)

let refreshTimer: ReturnType<typeof setInterval> | undefined

function onVisibility() {
  if (document.visibilityState === 'visible') void load()
}

onMounted(() => {
  void load()
  refreshTimer = setInterval(() => {
    if (document.visibilityState === 'visible') void load()
  }, 12_000)
  document.addEventListener('visibilitychange', onVisibility)
  if (import.meta.client && window.location.hash === '#primary') {
    nextTick(() => document.getElementById('primary')?.scrollIntoView({ behavior: 'smooth' }))
  }
})

onBeforeUnmount(() => {
  if (refreshTimer) clearInterval(refreshTimer)
  document.removeEventListener('visibilitychange', onVisibility)
})
</script>

<template>
  <div class="space-y-4">
    <PageHeader
      :title="t('requests.title')"
      :description="t('requests.description')"
    />

    <!-- Mini App: soft task layout -->
    <template v-if="isTelegram">
      <div v-if="showOnHand" class="tg-soft-card space-y-2">
        <p class="tg-section-label">{{ t('requests.onHand') }}</p>
        <ul v-if="myBalances.length" class="space-y-1">
          <li
            v-for="row in myBalances"
            :key="row.id"
            class="tg-meta-row !border-0 !py-1"
          >
            <div class="tg-meta-body">
              <p class="tg-meta-title">{{ row.bono_type?.name || t('common.bono') }}</p>
            </div>
            <span class="text-lg font-bold tabular-nums">{{ formatQty(row.quantity) }}</span>
          </li>
        </ul>
        <p v-else class="text-sm opacity-60">{{ t('requests.nothingOnHand') }}</p>
      </div>

      <div class="tg-segment">
        <button
          type="button"
          class="tg-segment-btn"
          :class="{ 'tg-segment-btn-active': viewMode === 'needs' }"
          @click="viewMode = 'needs'"
        >
          {{ t('requests.needsYou') }}
          <span v-if="needsYouCount" class="opacity-70">({{ needsYouCount }})</span>
        </button>
        <button
          type="button"
          class="tg-segment-btn"
          :class="{ 'tg-segment-btn-active': viewMode === 'all' }"
          @click="viewMode = 'all'"
        >
          {{ t('requests.viewAll') }}
        </button>
      </div>

      <template v-if="viewMode === 'needs'">
        <div v-if="!needsYouCount" class="tg-soft-card text-sm opacity-70">
          {{ t('requests.noOtherHandovers') }}
        </div>

        <div v-if="pendingConfirm.length" class="space-y-3">
          <p class="tg-section-label">{{ t('requests.waitingConfirmation') }}</p>
          <article
            v-for="row in pendingConfirm"
            :key="`tg-pending-${row.id}`"
            class="tg-soft-card space-y-3"
          >
            <div class="tg-meta-row !border-0 !py-0">
              <div class="tg-meta-avatar">{{ initialsOf(row.issuer?.name) }}</div>
              <div class="tg-meta-body">
                <p class="tg-meta-title">{{ row.reference }} · {{ kindLabel(row.kind) }}</p>
                <p class="tg-meta-sub">{{ t('requests.fromIssuer', { name: row.issuer?.name || t('common.dash') }) }}</p>
              </div>
              <StatusBadge :status="row.status" />
            </div>
            <ul class="space-y-1 text-sm opacity-80">
              <li v-for="line in row.lines" :key="line.id">{{ qtyLabel(line) }}</li>
            </ul>
            <div v-if="confirmingId === row.id" class="space-y-2">
              <div v-for="line in row.lines" :key="`tg-recv-${line.id}`" class="space-y-1">
                <p class="text-xs font-medium">{{ t('requests.issuedQty', { name: line.bono_type?.name, qty: formatQty(line.issued_quantity) }) }}</p>
                <input v-model="receiveQty[line.id]" class="input" type="number" min="0" :max="formatQty(line.issued_quantity)" step="1" inputmode="numeric">
              </div>
              <div class="flex gap-2">
                <button class="btn-secondary flex-1" type="button" @click="confirmingId = null">{{ t('common.cancel') }}</button>
                <AppButton class="flex-1" :loading="confirmingBusy" @click="confirm(row)">{{ t('requests.confirmReceived') }}</AppButton>
              </div>
            </div>
            <div v-else class="flex flex-col gap-2">
              <AppButton class="w-full" :loading="confirmingBusy" @click="confirmAsIssued(row)">{{ t('requests.confirmReceived') }}</AppButton>
              <button class="text-sm font-medium text-[color:var(--tg-theme-button-color,#2481cc)]" type="button" @click="startConfirm(row)">
                {{ t('requests.adjustReceivedQty') }}
              </button>
            </div>
          </article>
        </div>

        <div v-if="needsIssue.length" class="space-y-3">
          <p class="tg-section-label">{{ t('requests.needsIssue') }}</p>
          <article
            v-for="row in needsIssue"
            :key="`tg-issue-${row.id}`"
            class="tg-soft-card space-y-3"
          >
            <div class="tg-meta-row !border-0 !py-0">
              <div class="tg-meta-avatar">{{ initialsOf(row.receiver?.name) }}</div>
              <div class="tg-meta-body">
                <p class="tg-meta-title">{{ row.reference }} · {{ kindLabel(row.kind) }}</p>
                <p class="tg-meta-sub">{{ t('requests.issueToName', { name: row.receiver?.name || t('common.dash') }) }}</p>
              </div>
              <StatusBadge :status="row.status" />
            </div>
            <ul class="space-y-1 text-sm opacity-80">
              <li v-for="line in row.lines" :key="line.id">{{ qtyLabel(line) }}</li>
            </ul>
            <div v-if="issuingId === row.id" class="space-y-2">
              <div v-for="line in row.lines" :key="`tg-ni-${line.id}`" class="space-y-1">
                <p class="text-xs font-medium">{{ t('requests.approvedQty', { name: line.bono_type?.name, qty: formatQty(line.approved_quantity) }) }}</p>
                <input v-model="issueQty[line.id]" class="input" type="number" min="0" :max="formatQty(line.approved_quantity)" step="1" inputmode="numeric">
              </div>
              <div class="flex gap-2">
                <button class="btn-secondary flex-1" type="button" @click="issuingId = null">{{ t('common.cancel') }}</button>
                <AppButton class="flex-1" :loading="issuingBusy" @click="issueOnly(row)">{{ t('requests.issueToReceiver') }}</AppButton>
              </div>
            </div>
            <button v-else class="btn-primary w-full" type="button" @click="startIssue(row)">{{ t('requests.issueToReceiver') }}</button>
          </article>
        </div>

        <div v-if="awaitingIssue.length" class="space-y-3">
          <p class="tg-section-label">{{ t('requests.waitingIssue') }}</p>
          <article
            v-for="row in awaitingIssue"
            :key="`tg-await-${row.id}`"
            class="tg-soft-card space-y-2"
          >
            <div class="tg-meta-row !border-0 !py-0">
              <div class="tg-meta-avatar">{{ initialsOf(row.issuer?.name) }}</div>
              <div class="tg-meta-body">
                <p class="tg-meta-title">{{ row.reference }} · {{ kindLabel(row.kind) }}</p>
                <p class="tg-meta-sub">{{ t('requests.waitingIssueHelp') }}</p>
              </div>
              <StatusBadge :status="row.status" />
            </div>
            <ul class="space-y-1 text-sm opacity-80">
              <li v-for="line in row.lines" :key="line.id">{{ qtyLabel(line) }}</li>
            </ul>
          </article>
        </div>
      </template>

      <template v-else>
        <div class="tg-pills">
          <button type="button" class="tg-pill" :class="{ 'tg-pill-active': statusFilter === 'confirm' }" @click="statusFilter = 'confirm'">
            {{ t('requests.filterConfirm') }}
          </button>
          <button type="button" class="tg-pill" :class="{ 'tg-pill-active': statusFilter === 'issue' }" @click="statusFilter = 'issue'">
            {{ t('requests.filterIssue') }}
          </button>
          <button type="button" class="tg-pill" :class="{ 'tg-pill-active': statusFilter === 'waiting' }" @click="statusFilter = 'waiting'">
            {{ t('requests.filterWaiting') }}
          </button>
          <button type="button" class="tg-pill" :class="{ 'tg-pill-active': statusFilter === 'other' }" @click="statusFilter = 'other'">
            {{ t('requests.filterOther') }}
          </button>
        </div>

        <div v-if="statusFilter === 'confirm'" class="space-y-3">
          <article v-for="row in pendingConfirm" :key="`tg-f-c-${row.id}`" class="tg-soft-card space-y-2">
            <div class="tg-meta-row !border-0 !py-0">
              <div class="tg-meta-avatar">{{ initialsOf(row.issuer?.name) }}</div>
              <div class="tg-meta-body">
                <p class="tg-meta-title">{{ row.reference }}</p>
                <p class="tg-meta-sub">{{ kindLabel(row.kind) }}</p>
              </div>
              <StatusBadge :status="row.status" />
            </div>
            <AppButton :loading="confirmingBusy" @click="confirmAsIssued(row)">{{ t('requests.confirmReceived') }}</AppButton>
          </article>
          <p v-if="!pendingConfirm.length" class="text-sm opacity-60">{{ t('common.nothingHere') }}</p>
        </div>

        <div v-else-if="statusFilter === 'issue' || statusFilter === 'waiting'" class="tg-soft-card !py-1">
          <div v-for="row in filteredOther" :key="`tg-f-${row.id}`" class="tg-meta-row">
            <div class="tg-meta-avatar">{{ initialsOf(row.receiver?.name || row.issuer?.name) }}</div>
            <div class="tg-meta-body">
              <p class="tg-meta-title">{{ row.reference }} · {{ kindLabel(row.kind) }}</p>
              <p class="tg-meta-sub">{{ row.issuer?.name || t('common.dash') }} → {{ row.receiver?.name || t('common.dash') }}</p>
            </div>
            <div class="tg-meta-trail"><StatusBadge :status="row.status" /></div>
          </div>
          <p v-if="!filteredOther.length" class="py-3 text-sm opacity-60">{{ t('common.nothingHere') }}</p>
        </div>

        <div v-else class="tg-soft-card !py-1">
          <div v-for="row in otherHandovers" :key="`tg-o-${row.id}`" class="tg-meta-row">
            <div class="tg-meta-avatar">{{ initialsOf(row.issuer?.name) }}</div>
            <div class="tg-meta-body">
              <p class="tg-meta-title">{{ row.reference }} · {{ kindLabel(row.kind) }}</p>
              <p class="tg-meta-sub">{{ row.issuer?.name || t('common.dash') }} → {{ row.receiver?.name || t('common.dash') }}</p>
            </div>
            <div class="tg-meta-trail space-y-1">
              <StatusBadge :status="row.status" />
              <button
                v-if="canApproveRow(row)"
                type="button"
                class="text-xs font-semibold text-[color:var(--tg-theme-button-color,#2481cc)]"
                @click="startApprove(row)"
              >
                {{ t('requests.reviewAndIssue') }}
              </button>
            </div>
          </div>
          <div v-if="approvingId" class="space-y-2 border-t border-[color:var(--tg-border)] py-3">
            <template v-for="row in otherHandovers" :key="`tg-ap-${row.id}`">
              <div v-if="approvingId === row.id" class="space-y-2">
                <div v-for="line in row.lines" :key="line.id" class="space-y-1">
                  <p class="text-xs opacity-70">{{ t('requests.requestedQty', { name: line.bono_type?.name, qty: formatQty(line.requested_quantity) }) }}</p>
                  <input v-model="approveQty[line.id]" class="input" type="number" min="0" step="1" inputmode="numeric">
                </div>
                <div class="flex gap-2">
                  <button class="btn-secondary flex-1" type="button" @click="approvingId = null">{{ t('common.cancel') }}</button>
                  <AppButton class="flex-1" :loading="approveActions.isBusy(row.id)" @click="approveAndIssue(row)">{{ t('requests.approveAndIssue') }}</AppButton>
                </div>
              </div>
            </template>
          </div>
          <p v-if="!otherHandovers.length" class="py-3 text-sm opacity-60">{{ t('requests.noOtherHandovers') }}</p>
        </div>

        <div v-if="canRequest" id="primary" class="tg-soft-card space-y-2">
          <p class="tg-section-label">{{ t('requests.requestBonos') }}</p>
          <p class="text-sm opacity-70">{{ requestHelp }}</p>
          <select v-model="requestForm.bono_type_id" class="input">
            <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
          </select>
          <input v-model="requestForm.quantity" class="input" :placeholder="t('common.quantity')" type="number" min="1" step="1">
          <AppButton :loading="requestBusy" @click="requestBonos">{{ t('requests.submitRequest') }}</AppButton>
        </div>

        <div v-if="canReturn" class="tg-soft-card space-y-2">
          <p class="tg-section-label">{{ t('requests.returnUnused') }}</p>
          <select v-model="returnForm.bono_type_id" class="input">
            <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
          </select>
          <select v-model="returnForm.receiver_id" class="input" required>
            <option disabled value="">{{ t('common.selectCashier') }}</option>
            <option v-for="person in cashiers" :key="person.id" :value="String(person.id)">{{ person.name }}</option>
          </select>
          <input v-model="returnForm.quantity" class="input" :placeholder="t('requests.unusedQuantity')" type="number" min="1" step="1">
          <AppButton variant="secondary" :loading="returnBusy" @click="returnBonos">{{ t('requests.returnUnused') }}</AppButton>
        </div>
      </template>
    </template>

    <!-- Desktop / browser -->
    <template v-else>
    <div v-if="showOnHand" class="card space-y-2 p-4">
      <h2 class="font-semibold">{{ t('requests.onHand') }}</h2>
      <p class="text-sm text-slate-600">
        {{ t('requests.onHandHelp') }}
      </p>
      <ul v-if="myBalances.length" class="space-y-2">
        <li
          v-for="row in myBalances"
          :key="row.id"
          class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm"
        >
          <span class="font-medium">{{ row.bono_type?.name || t('common.bono') }}</span>
          <span class="text-lg font-bold tabular-nums">{{ formatQty(row.quantity) }}</span>
        </li>
      </ul>
      <p v-else class="text-sm text-slate-500">{{ t('requests.nothingOnHand') }}</p>
    </div>

    <div v-if="needsIssue.length" class="space-y-3">
      <h2 class="text-sm font-semibold tracking-wide text-amber-700 uppercase">{{ t('requests.needsIssue') }}</h2>
      <article
        v-for="row in needsIssue"
        :key="`needs-issue-${row.id}`"
        class="card space-y-3 border border-amber-300 p-4 ring-1 ring-amber-100"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="font-semibold">{{ row.reference }} · {{ kindLabel(row.kind) }}</p>
            <p class="text-sm text-slate-600">
              {{ t('requests.issueToName', { name: row.receiver?.name || t('common.dash') }) }}
            </p>
          </div>
          <StatusBadge :status="row.status" />
        </div>
        <ul class="space-y-1 text-sm">
          <li v-for="line in row.lines" :key="line.id">{{ qtyLabel(line) }}</li>
        </ul>
        <div v-if="issuingId === row.id" class="space-y-2 rounded-lg bg-slate-50 p-3">
          <p class="text-xs text-slate-600">{{ t('requests.issueHelp') }}</p>
          <div v-for="line in row.lines" :key="`ni-${line.id}`" class="space-y-1">
            <p class="text-xs font-medium">{{ t('requests.approvedQty', { name: line.bono_type?.name, qty: formatQty(line.approved_quantity) }) }}</p>
            <input
              v-model="issueQty[line.id]"
              class="input"
              type="number"
              min="0"
              :max="formatQty(line.approved_quantity)"
              step="1"
              inputmode="numeric"
            >
          </div>
          <div class="flex gap-2">
            <button class="btn-secondary flex-1" type="button" @click="issuingId = null">{{ t('common.cancel') }}</button>
            <AppButton class="flex-1" :loading="issuingBusy" @click="issueOnly(row)">
              {{ issuingBusy ? t('requests.issuing') : t('requests.issueToReceiver') }}
            </AppButton>
          </div>
        </div>
        <button
          v-else
          class="btn-primary w-full min-h-11"
          type="button"
          @click="startIssue(row)"
        >
          {{ t('requests.issueToReceiver') }}
        </button>
      </article>
    </div>

    <div v-if="awaitingIssue.length" class="space-y-3">
      <h2 class="text-sm font-semibold tracking-wide text-amber-600 uppercase">{{ t('requests.waitingIssue') }}</h2>
      <article
        v-for="row in awaitingIssue"
        :key="`await-issue-${row.id}`"
        class="card space-y-2 border border-amber-200 p-4"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="font-semibold">{{ row.reference }} · {{ kindLabel(row.kind) }}</p>
            <p class="text-sm text-slate-600">{{ t('requests.waitingIssueHelp') }}</p>
          </div>
          <StatusBadge :status="row.status" />
        </div>
        <ul class="space-y-1 text-sm">
          <li v-for="line in row.lines" :key="line.id">{{ qtyLabel(line) }}</li>
        </ul>
      </article>
    </div>

    <div v-if="pendingConfirm.length" class="space-y-3">
      <h2 class="text-sm font-semibold tracking-wide text-slate-500 uppercase">{{ t('requests.waitingConfirmation') }}</h2>
      <article
        v-for="row in pendingConfirm"
        :key="`pending-${row.id}`"
        class="card space-y-3 border border-brand-200 p-4 ring-1 ring-brand-100"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="font-semibold">{{ row.reference }} · {{ kindLabel(row.kind) }}</p>
            <p class="text-sm text-slate-600">{{ t('requests.fromIssuer', { name: row.issuer?.name || t('common.dash') }) }}</p>
          </div>
          <StatusBadge :status="row.status" />
        </div>
        <ul class="space-y-1 text-sm">
          <li v-for="line in row.lines" :key="line.id">{{ qtyLabel(line) }}</li>
        </ul>

        <div v-if="confirmingId === row.id" class="space-y-2 rounded-lg bg-slate-50 p-3">
          <p class="text-xs text-slate-600">{{ t('requests.confirmReceivedHelp') }}</p>
          <div v-for="line in row.lines" :key="`recv-${line.id}`" class="space-y-1">
            <p class="text-xs font-medium">{{ t('requests.issuedQty', { name: line.bono_type?.name, qty: formatQty(line.issued_quantity) }) }}</p>
            <input
              v-model="receiveQty[line.id]"
              class="input"
              type="number"
              min="0"
              :max="formatQty(line.issued_quantity)"
              step="1"
              inputmode="numeric"
            >
          </div>
          <div class="flex gap-2">
            <button class="btn-secondary flex-1" type="button" @click="confirmingId = null">{{ t('common.cancel') }}</button>
            <AppButton class="flex-1" :loading="confirmingBusy" @click="confirm(row)">
              {{ confirmingBusy ? t('requests.confirming') : t('requests.confirmReceived') }}
            </AppButton>
          </div>
        </div>
        <div v-else class="flex flex-col gap-2">
          <AppButton
            class="w-full min-h-11"
            :loading="confirmingBusy"
            @click="confirmAsIssued(row)"
          >
            {{ confirmingBusy ? t('requests.confirming') : t('requests.confirmReceived') }}
          </AppButton>
          <button
            class="text-sm font-medium text-brand-700"
            type="button"
            @click="startConfirm(row)"
          >
            {{ t('requests.adjustReceivedQty') }}
          </button>
        </div>
      </article>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <div v-if="canRequest" class="card space-y-2 p-4">
        <h2 class="font-semibold">{{ t('requests.requestBonos') }}</h2>
        <p class="text-sm text-slate-600">{{ requestHelp }}</p>
        <select v-model="requestForm.bono_type_id" class="input">
          <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
        </select>
        <input v-model="requestForm.quantity" class="input" :placeholder="t('common.quantity')" type="number" min="1" step="1">
        <AppButton :loading="requestBusy" @click="requestBonos">
          {{ t('requests.submitRequest') }}
        </AppButton>
      </div>

      <div v-if="canReturn" class="card space-y-2 p-4">
        <h2 class="font-semibold">{{ t('requests.returnUnused') }}</h2>
        <p class="text-sm text-slate-600">{{ t('requests.returnHelp') }}</p>
        <select v-model="returnForm.bono_type_id" class="input">
          <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
        </select>
        <select v-model="returnForm.receiver_id" class="input" required>
          <option disabled value="">{{ t('common.selectCashier') }}</option>
          <option v-for="person in cashiers" :key="person.id" :value="String(person.id)">
            {{ person.name }}
          </option>
        </select>
        <input v-model="returnForm.quantity" class="input" :placeholder="t('requests.unusedQuantity')" type="number" min="1" step="1">
        <AppButton variant="secondary" :loading="returnBusy" @click="returnBonos">
          {{ t('requests.returnUnused') }}
        </AppButton>
      </div>
    </div>

    <div class="card">
      <div class="border-b border-slate-100 px-4 py-3">
        <h2 class="font-semibold">{{ t('requests.allHandovers') }}</h2>
      </div>
      <AppTable
        :rows="otherHandovers"
        :empty-title="t('requests.noOtherHandovers')"
        :empty-description="t('requests.pendingAbove')"
        :columns="columns"
      >
        <template #cell:kind="{ row }">{{ kindLabel(row.kind) }}</template>
        <template #cell:parties="{ row }">
          {{ row.issuer?.name || t('common.dash') }} → {{ row.receiver?.name || t('common.dash') }}
        </template>
        <template #cell:qty="{ row }">
          <span v-for="line in row.lines" :key="line.id" class="block text-sm">
            {{ qtyLabel(line) }}
          </span>
        </template>
        <template #cell:status="{ row }">
          <StatusBadge :status="row.status" />
        </template>
        <template #cell:actions="{ row }">
          <div class="flex min-w-[14rem] flex-col items-stretch gap-2 sm:items-end">
            <button
              v-if="canApproveRow(row) && approvingId !== row.id"
              class="btn-primary text-sm"
              type="button"
              @click="startApprove(row)"
            >
              {{ t('requests.reviewAndIssue') }}
            </button>
            <div v-if="approvingId === row.id" class="w-full space-y-2 rounded-lg bg-slate-50 p-2">
              <div v-for="line in row.lines" :key="line.id" class="space-y-1">
                <p class="text-xs text-slate-600">
                  {{ t('requests.requestedQty', { name: line.bono_type?.name, qty: formatQty(line.requested_quantity) }) }}
                </p>
                <input
                  v-model="approveQty[line.id]"
                  class="input text-sm"
                  type="number"
                  min="0"
                  :max="formatQty(line.requested_quantity)"
                  step="1"
                  inputmode="numeric"
                >
              </div>
              <div class="flex justify-end gap-2">
                <button class="btn-secondary text-sm" type="button" @click="approvingId = null">{{ t('common.cancel') }}</button>
                <AppButton size="sm" :loading="approveActions.isBusy(row.id)" @click="approveAndIssue(row)">
                  {{ t('requests.approveAndIssue') }}
                </AppButton>
              </div>
            </div>

            <button
              v-if="canIssueRow(row) && issuingId !== row.id"
              class="btn-primary text-sm"
              type="button"
              @click="startIssue(row)"
            >
              {{ t('requests.issueToReceiver') }}
            </button>
            <div v-if="issuingId === row.id" class="w-full space-y-2 rounded-lg bg-slate-50 p-2">
              <p class="text-xs text-slate-600">{{ t('requests.issueHelp') }}</p>
              <div v-for="line in row.lines" :key="`iss-${line.id}`" class="space-y-1">
                <p class="text-xs text-slate-600">
                  {{ t('requests.approvedQty', { name: line.bono_type?.name, qty: formatQty(line.approved_quantity) }) }}
                </p>
                <input
                  v-model="issueQty[line.id]"
                  class="input text-sm"
                  type="number"
                  min="0"
                  :max="formatQty(line.approved_quantity)"
                  step="1"
                  inputmode="numeric"
                >
              </div>
              <div class="flex justify-end gap-2">
                <button class="btn-secondary text-sm" type="button" @click="issuingId = null">{{ t('common.cancel') }}</button>
                <AppButton size="sm" :loading="issuingBusy" @click="issueOnly(row)">
                  {{ issuingBusy ? t('requests.issuing') : t('requests.issueToReceiver') }}
                </AppButton>
              </div>
            </div>

            <AppButton
              v-if="canConfirmRow(row)"
              size="sm"
              :loading="confirmingBusy"
              @click="confirmAsIssued(row)"
            >
              {{ t('requests.confirmReceived') }}
            </AppButton>

            <button
              v-if="canApproveRow(row) || canIssueRow(row)"
              class="text-sm text-red-600"
              type="button"
              @click="rejectingId = row.id"
            >
              {{ t('common.reject') }}
            </button>
            <div v-if="rejectingId === row.id" class="flex gap-1">
              <input v-model="rejectReason" class="input text-sm" :placeholder="t('common.reason')">
              <AppButton variant="secondary" size="sm" :loading="rejectActions.isBusy(row.id)" @click="reject(row)">
                {{ t('common.send') }}
              </AppButton>
            </div>
          </div>
        </template>
      </AppTable>
    </div>
    </template>
  </div>
</template>

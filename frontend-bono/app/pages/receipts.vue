<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({
  permissions: [PERMISSIONS.bonosReconcile, PERMISSIONS.bonosSubmit],
})

const { t } = useI18n()
const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const { haptic, isTelegram } = useTelegramWebApp()

const rows = ref<any[]>([])
const uploading = ref(false)
const editing = ref<any | null>(null)
const previewUrl = ref<string | null>(null)
const busyId = ref<number | null>(null)
const { busy: saving, run: runSave } = useBusyAction()
const cameraInput = ref<HTMLInputElement | null>(null)
const galleryInput = ref<HTMLInputElement | null>(null)

const form = reactive({
  amount: '',
  payee_name: '',
  account_number: '',
  phone: '',
  transaction_reference: '',
  method: 'mobile_money',
})

const canReview = computed(() =>
  auth.can(PERMISSIONS.bonosReconcile) || auth.role === 'admin' || auth.role === 'manager',
)
const canUpload = computed(() =>
  auth.can(PERMISSIONS.bonosSubmit) || auth.can(PERMISSIONS.bonosReconcile) || auth.role === 'admin',
)

/** Group reviewer inbox by waiter / uploader. */
const groups = computed(() => {
  const map = new Map<string, { key: string, name: string, pending: number, rows: any[] }>()
  for (const row of rows.value) {
    const id = String(row.uploaded_by ?? row.uploader?.id ?? 'unknown')
    const name = row.uploader?.name || t('receipts.unknownWaiter')
    if (!map.has(id)) {
      map.set(id, { key: id, name, pending: 0, rows: [] })
    }
    const group = map.get(id)!
    group.rows.push(row)
    if (row.verification_status === 'pending') group.pending += 1
  }
  return [...map.values()].sort((a, b) => {
    if (a.pending !== b.pending) return b.pending - a.pending
    return a.name.localeCompare(b.name)
  })
})

function statusLabel(status: string): string {
  if (status === 'verified') return t('receipts.statusVerified')
  if (status === 'rejected') return t('receipts.statusRejected')
  return t('receipts.statusPending')
}

async function load() {
  try {
    rows.value = ((await api.transferReceipts()) as any).data ?? []
  }
  catch (error: any) {
    toast.error(error.message)
  }
}

async function uploadFile(file: File | undefined | null) {
  if (!file) return
  uploading.value = true
  try {
    const body = new FormData()
    body.append('file', file)
    body.append('method', 'mobile_money')
    const created = ((await api.uploadTransferReceipt(body)) as any).data
    haptic('success')
    toast.success(created.ocr_status === 'parsed' ? t('receipts.uploadedScanned') : t('receipts.uploadedNeedsReview'))
    openEdit(created)
    await load()
  }
  catch (error: any) {
    haptic('error')
    toast.error(error.message || t('receipts.uploadFailed'))
  }
  finally {
    uploading.value = false
  }
}

async function onFile(event: Event) {
  const input = event.target as HTMLInputElement
  await uploadFile(input.files?.[0])
  input.value = ''
}

function revokePreview() {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }
}

async function loadPreview(row: any) {
  revokePreview()
  try {
    const blob = await api.transferReceiptFile(row.id)
    previewUrl.value = URL.createObjectURL(blob)
  }
  catch {
    previewUrl.value = null
  }
}

async function openEdit(row: any) {
  editing.value = row
  Object.assign(form, {
    amount: String(row.amount ?? ''),
    payee_name: row.payee_name ?? '',
    account_number: row.account_number ?? '',
    phone: row.phone ?? '',
    transaction_reference: row.transaction_reference ?? row.reference ?? '',
    method: row.method ?? 'mobile_money',
  })
  await loadPreview(row)
}

function closeEdit() {
  editing.value = null
  revokePreview()
}

async function save() {
  if (!editing.value) return
  await runSave(async () => {
    try {
      await api.updateTransferReceipt(editing.value!.id, { ...form })
      toast.success(t('receipts.detailsSaved'))
      closeEdit()
      await load()
    }
    catch (error: any) {
      toast.error(error.message)
    }
  })
}

async function setVerification(row: any, status: 'verified' | 'rejected') {
  busyId.value = row.id
  try {
    await api.verifyTransferReceipt(row.id, { verification_status: status })
    haptic(status === 'verified' ? 'success' : 'error')
    toast.success(status === 'verified' ? t('receipts.markedVerified') : t('receipts.markedRejected'))
    if (editing.value?.id === row.id) closeEdit()
    await load()
  }
  catch (error: any) {
    toast.error(error.message)
  }
  finally {
    busyId.value = null
  }
}

onMounted(load)
onBeforeUnmount(revokePreview)
</script>

<template>
  <div class="space-y-4">
    <PageHeader
      :title="t('receipts.title')"
      :description="canReview ? t('receipts.descriptionReviewer') : t('receipts.descriptionWaiter')"
    />

    <div v-if="canUpload" class="space-y-3 p-4" :class="isTelegram ? 'tg-soft-card' : 'card'">
      <p v-if="isTelegram" class="tg-section-label">{{ t('receipts.uploadHeading') }}</p>
      <h2 v-else class="font-semibold">{{ t('receipts.uploadHeading') }}</h2>
      <p v-if="!isTelegram" class="text-sm text-slate-600">{{ t('receipts.uploadHelp') }}</p>
      <div class="grid grid-cols-2 gap-2">
        <AppButton
          class="min-h-11"
          :loading="uploading"
          @click="cameraInput?.click()"
        >
          {{ uploading ? t('receipts.scanning') : t('receipts.takePhoto') }}
        </AppButton>
        <AppButton
          variant="secondary"
          class="min-h-11"
          :loading="uploading"
          @click="galleryInput?.click()"
        >
          {{ uploading ? t('receipts.scanning') : t('receipts.fromGallery') }}
        </AppButton>
      </div>
      <!-- capture=environment opens the rear camera on phones / Telegram WebView -->
      <input
        ref="cameraInput"
        type="file"
        class="hidden"
        accept="image/*"
        capture="environment"
        :disabled="uploading"
        @change="onFile"
      >
      <input
        ref="galleryInput"
        type="file"
        class="hidden"
        accept="image/*,.pdf"
        :disabled="uploading"
        @change="onFile"
      >
    </div>

    <!-- Reviewer: segment by waiter -->
    <div v-if="canReview" class="space-y-4">
      <p v-if="!groups.length" class="text-sm text-slate-500">{{ t('receipts.emptyReviewer') }}</p>
      <section
        v-for="group in groups"
        :key="group.key"
        class="overflow-hidden"
        :class="isTelegram ? 'tg-soft-card !p-0' : 'card'"
      >
        <header class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
          <div>
            <h2 class="font-semibold">{{ group.name }}</h2>
            <p class="text-xs text-slate-500">
              {{ t('receipts.waiterCount', { count: group.rows.length, pending: group.pending }) }}
            </p>
          </div>
          <span
            v-if="group.pending"
            class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800"
          >
            {{ group.pending }} {{ t('receipts.statusPending') }}
          </span>
        </header>
        <ul class="divide-y divide-slate-100">
          <li
            v-for="row in group.rows"
            :key="row.id"
            class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
            :class="{ 'tg-meta-row !border-0': isTelegram }"
          >
            <div class="min-w-0">
              <p class="font-medium tabular-nums">{{ formatAmount(row.amount) }}</p>
              <p class="truncate text-sm text-slate-600">
                {{ row.transaction_reference || row.reference || t('common.dash') }}
                · {{ statusLabel(row.verification_status) }}
              </p>
              <p class="text-xs text-slate-500">{{ row.payee_name || t('common.dash') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="btn-secondary text-sm" @click="openEdit(row)">
                {{ t('common.edit') }}
              </button>
              <AppButton
                v-if="row.verification_status === 'pending'"
                size="sm"
                :loading="busyId === row.id"
                @click="setVerification(row, 'verified')"
              >
                {{ t('receipts.validate') }}
              </AppButton>
              <AppButton
                v-if="row.verification_status === 'pending'"
                variant="danger"
                size="sm"
                :loading="busyId === row.id"
                @click="setVerification(row, 'rejected')"
              >
                {{ t('common.reject') }}
              </AppButton>
            </div>
          </li>
        </ul>
      </section>
    </div>

    <!-- Waiter: own uploads -->
    <div v-else :class="isTelegram ? 'tg-soft-card !p-0' : 'card'">
      <div class="border-b border-slate-100 px-4 py-3">
        <p v-if="isTelegram" class="tg-section-label !mb-0">{{ t('receipts.myUploads') }}</p>
        <h2 v-else class="font-semibold">{{ t('receipts.myUploads') }}</h2>
      </div>
      <ul v-if="rows.length" class="divide-y divide-slate-100">
        <li
          v-for="row in rows"
          :key="row.id"
          class="flex items-center justify-between gap-3 px-4 py-3"
        >
          <div class="min-w-0">
            <p class="font-medium tabular-nums">{{ formatAmount(row.amount) }}</p>
            <p class="truncate text-sm text-slate-600">
              {{ row.transaction_reference || row.reference || t('common.dash') }}
            </p>
            <StatusBadge :status="row.verification_status || 'pending'" />
          </div>
          <button type="button" class="btn-secondary text-sm" @click="openEdit(row)">
            {{ row.verification_status === 'pending' ? t('common.edit') : t('receipts.view') }}
          </button>
        </li>
      </ul>
      <p v-else class="px-4 py-6 text-sm text-slate-500">{{ t('receipts.emptyWaiter') }}</p>
    </div>

    <AppModal
      :model-value="!!editing"
      :title="t('receipts.detailsTitle')"
      @update:model-value="(v: boolean) => { if (!v) closeEdit() }"
    >
      <form class="space-y-3" @submit.prevent="save">
        <img
          v-if="previewUrl"
          :src="previewUrl"
          :alt="t('receipts.previewAlt')"
          class="max-h-56 w-full rounded-lg object-contain bg-slate-100"
        >
        <FormField :label="t('common.amount')">
          <input
            v-model="form.amount"
            class="input"
            type="number"
            min="0"
            step="0.01"
            required
            :disabled="canReview ? false : editing?.verification_status !== 'pending'"
          >
        </FormField>
        <FormField :label="t('common.payee')">
          <input v-model="form.payee_name" class="input" :disabled="canReview ? false : editing?.verification_status !== 'pending'">
        </FormField>
        <FormField :label="t('receipts.accountNumber')">
          <input v-model="form.account_number" class="input" :disabled="canReview ? false : editing?.verification_status !== 'pending'">
        </FormField>
        <FormField :label="t('common.phone')">
          <input v-model="form.phone" class="input" :disabled="canReview ? false : editing?.verification_status !== 'pending'">
        </FormField>
        <FormField :label="t('receipts.ftReference')">
          <input v-model="form.transaction_reference" class="input" :disabled="canReview ? false : editing?.verification_status !== 'pending'">
        </FormField>
        <p v-if="editing?.ocr_raw_text" class="max-h-32 overflow-y-auto rounded bg-slate-50 p-2 text-xs text-slate-600 whitespace-pre-wrap">
          {{ editing.ocr_raw_text }}
        </p>
        <div class="flex flex-col gap-2">
          <AppButton
            v-if="canReview || editing?.verification_status === 'pending'"
            type="submit"
            :loading="saving"
          >
            {{ t('common.save') }}
          </AppButton>
          <div v-if="canReview && editing?.verification_status === 'pending'" class="grid grid-cols-2 gap-2">
            <AppButton :loading="busyId === editing.id" @click="setVerification(editing, 'verified')">
              {{ t('receipts.validate') }}
            </AppButton>
            <AppButton variant="danger" :loading="busyId === editing.id" @click="setVerification(editing, 'rejected')">
              {{ t('common.reject') }}
            </AppButton>
          </div>
        </div>
      </form>
    </AppModal>
  </div>
</template>

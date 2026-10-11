<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({ permissions: [PERMISSIONS.bonosReconcile] })

const api = useRestaurantApi()
const toast = useToast()
const rows = ref<any[]>([])
const uploading = ref(false)
const editing = ref<any | null>(null)
const form = reactive({
  amount: '',
  payee_name: '',
  account_number: '',
  phone: '',
  transaction_reference: '',
  method: 'mobile_money',
})

async function load() {
  try {
    rows.value = ((await api.transferReceipts()) as any).data ?? []
  }
  catch (error: any) {
    toast.error(error.message)
  }
}

async function onFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  uploading.value = true
  try {
    const body = new FormData()
    body.append('file', file)
    body.append('method', 'mobile_money')
    const created = ((await api.uploadTransferReceipt(body)) as any).data
    toast.success(created.ocr_status === 'parsed' ? 'Receipt uploaded and scanned' : 'Receipt uploaded (OCR needs review)')
    openEdit(created)
    await load()
  }
  catch (error: any) {
    toast.error(error.message || 'Upload failed. Duplicate receipts are blocked.')
  }
  finally {
    uploading.value = false
    input.value = ''
  }
}

function openEdit(row: any) {
  editing.value = row
  Object.assign(form, {
    amount: String(row.amount ?? ''),
    payee_name: row.payee_name ?? '',
    account_number: row.account_number ?? '',
    phone: row.phone ?? '',
    transaction_reference: row.transaction_reference ?? row.reference ?? '',
    method: row.method ?? 'mobile_money',
  })
}

async function save() {
  if (!editing.value) return
  try {
    await api.updateTransferReceipt(editing.value.id, { ...form })
    toast.success('Receipt details saved')
    editing.value = null
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
    <PageHeader title="Transfer receipts" description="Upload bank or mobile-money slips. Tesseract reads amount, payee, account, phone, and FT. Duplicates are blocked.">
      <template #actions>
        <label class="btn-primary cursor-pointer">
          <AppSpinner v-if="uploading" class="size-4" />
          {{ uploading ? 'Scanning…' : 'Upload receipt' }}
          <input type="file" class="hidden" accept="image/*,.pdf" :disabled="uploading" @change="onFile">
        </label>
      </template>
    </PageHeader>

    <div class="card">
      <AppTable
        :rows="rows"
        :columns="[
          { key: 'amount', label: 'Amount', align: 'right' },
          { key: 'payee_name', label: 'Payee' },
          { key: 'phone', label: 'Phone' },
          { key: 'transaction_reference', label: 'FT / Ref' },
          { key: 'ocr_status', label: 'OCR' },
          { key: 'actions', label: '' },
        ]"
      >
        <template #cell:amount="{ row }">{{ formatAmount(row.amount) }}</template>
        <template #cell:transaction_reference="{ row }">{{ row.transaction_reference || row.reference || '—' }}</template>
        <template #cell:actions="{ row }">
          <button type="button" class="btn-ghost text-sm" @click="openEdit(row)">Edit</button>
        </template>
      </AppTable>
    </div>

    <AppModal :model-value="!!editing" title="Receipt details" @update:model-value="(v: boolean) => { if (!v) editing = null }">
      <form class="space-y-3" @submit.prevent="save">
        <FormField label="Amount">
          <input v-model="form.amount" class="input" type="number" min="0" step="0.01" required>
        </FormField>
        <FormField label="Payee">
          <input v-model="form.payee_name" class="input">
        </FormField>
        <FormField label="Account number">
          <input v-model="form.account_number" class="input">
        </FormField>
        <FormField label="Phone">
          <input v-model="form.phone" class="input">
        </FormField>
        <FormField label="FT / transaction reference">
          <input v-model="form.transaction_reference" class="input">
        </FormField>
        <p v-if="editing?.ocr_raw_text" class="max-h-32 overflow-y-auto rounded bg-slate-50 p-2 text-xs text-slate-600 whitespace-pre-wrap">
          {{ editing.ocr_raw_text }}
        </p>
        <button type="submit" class="btn-primary">Save</button>
      </form>
    </AppModal>
  </div>
</template>

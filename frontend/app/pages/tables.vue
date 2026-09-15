<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
import type { RestaurantTable } from '~/types/domain'

definePageMeta({ permissions: [PERMISSIONS.tablesView] })

const api = useRestaurantApi()
const toast = useToast()
const auth = useAuthStore()

const tables = ref<RestaurantTable[]>([])
const loading = ref(true)
const error = ref('')
const showForm = ref(false)
const form = reactive({ name: '', capacity: 4 })
const saving = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  try {
    tables.value = (await api.tables()).data
  }
  catch (e: any) {
    error.value = e.message ?? 'Failed to load tables'
  }
  finally {
    loading.value = false
  }
}

async function openTable(table: RestaurantTable) {
  if (table.status === 'occupied' && table.current_order_id) {
    await navigateTo(`/orders/${table.current_order_id}`)
    return
  }

  if (table.status === 'available' && auth.can(PERMISSIONS.ordersCreate)) {
    await navigateTo({ path: '/pos', query: { table: String(table.id) } })
  }
}

async function createTable() {
  saving.value = true
  try {
    await api.createTable({ name: form.name, capacity: form.capacity })
    toast.success('Table created')
    showForm.value = false
    form.name = ''
    await load()
  }
  catch (e: any) {
    toast.error(e.message)
  }
  finally {
    saving.value = false
  }
}

onMounted(load)

const statusClass: Record<string, string> = {
  available: 'border-emerald-200 bg-emerald-50',
  occupied: 'border-red-200 bg-red-50',
  reserved: 'border-violet-200 bg-violet-50',
  cleaning: 'border-slate-200 bg-slate-50',
}
</script>

<template>
  <div>
    <PageHeader title="Tables" description="Floor status. Tap an available table to open the POS.">
      <template #actions>
        <button v-if="auth.can(PERMISSIONS.tablesManage)" type="button" class="btn-primary" @click="showForm = true">
          Add table
        </button>
      </template>
    </PageHeader>

    <ErrorState v-if="error" :message="error" @retry="load" />
    <div v-else-if="loading" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
      <div v-for="n in 10" :key="n" class="h-36 animate-pulse rounded-xl bg-white" />
    </div>
    <EmptyState v-else-if="!tables.length" title="No tables yet" />
    <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
      <button
        v-for="table in tables"
        :key="table.id"
        type="button"
        class="card flex min-h-36 flex-col items-start p-4 text-left ring-2 ring-transparent transition hover:ring-brand-200"
        :class="statusClass[table.status]"
        @click="openTable(table)"
      >
        <div class="flex w-full items-start justify-between gap-2">
          <p class="text-2xl font-bold">{{ table.name }}</p>
          <StatusBadge :status="table.status" />
        </div>
        <p class="mt-2 text-sm text-slate-600">{{ table.capacity }} seats</p>
        <p v-if="table.waiter" class="mt-auto pt-3 text-xs text-slate-500">{{ table.waiter.name }}</p>
        <p v-if="table.current_order" class="mt-1 text-sm font-semibold">
          {{ formatMoney(table.current_order.total, auth.currency) }}
        </p>
      </button>
    </div>

    <AppModal v-model="showForm" title="New table">
      <form class="space-y-4" @submit.prevent="createTable">
        <FormField label="Number / name" required>
          <input v-model="form.name" class="input" required>
        </FormField>
        <FormField label="Capacity" required>
          <input v-model.number="form.capacity" type="number" min="1" class="input" required>
        </FormField>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="showForm = false">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="saving">Save</button>
        </div>
      </form>
    </AppModal>
  </div>
</template>

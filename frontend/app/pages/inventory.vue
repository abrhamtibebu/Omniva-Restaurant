<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({ permissions: [PERMISSIONS.inventoryView] })

const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const rows = ref<any[]>([])
const loading = ref(true)
const show = ref(false)
const form = reactive({ name: '', sku: '', unit: 'kg', quantity_on_hand: '0', minimum_stock: '0' })
const adjust = ref<any>(null)
const adj = reactive({ type: 'adjustment_in', quantity: '1', notes: '' })

async function load() {
  loading.value = true
  rows.value = (await api.inventory({ per_page: 50 })).data
  loading.value = false
}
async function create() {
  await api.createInventory(form)
  show.value = false
  toast.success('Item added')
  await load()
}
async function saveAdjust() {
  await api.adjustInventory(adjust.value.id, adj)
  adjust.value = null
  toast.success('Stock updated')
  await load()
}
onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Inventory" description="Quantities move only through transactions.">
      <template #actions>
        <button v-if="auth.can(PERMISSIONS.inventoryManage)" type="button" class="btn-primary" @click="show = true">Add item</button>
      </template>
    </PageHeader>
    <div class="card">
      <AppTable
        :columns="[
          { key: 'name', label: 'Item' },
          { key: 'sku', label: 'SKU' },
          { key: 'quantity_on_hand', label: 'On hand', align: 'right' },
          { key: 'minimum_stock', label: 'Min', align: 'right' },
          { key: 'unit', label: 'Unit' },
          { key: 'actions', label: '' },
        ]"
        :rows="rows"
        :loading="loading"
      >
        <template #cell:actions="{ row }">
          <button v-if="auth.can(PERMISSIONS.inventoryAdjust)" type="button" class="btn-ghost text-sm" @click="adjust = row">Adjust</button>
        </template>
      </AppTable>
    </div>
    <AppModal v-model="show" title="New inventory item">
      <form class="space-y-3" @submit.prevent="create">
        <FormField label="Name"><input v-model="form.name" class="input" required></FormField>
        <FormField label="SKU"><input v-model="form.sku" class="input"></FormField>
        <FormField label="Unit">
          <select v-model="form.unit" class="input">
            <option v-for="u in ['kg','gram','liter','ml','piece','pack']" :key="u" :value="u">{{ u }}</option>
          </select>
        </FormField>
        <button type="submit" class="btn-primary">Save</button>
      </form>
    </AppModal>
    <AppModal :model-value="!!adjust" title="Stock adjustment" @update:model-value="(v: boolean) => { if (!v) adjust = null }">
      <form class="space-y-3" @submit.prevent="saveAdjust">
        <select v-model="adj.type" class="input">
          <option value="adjustment_in">Adjustment in</option>
          <option value="adjustment_out">Adjustment out</option>
          <option value="waste">Waste</option>
        </select>
        <input v-model="adj.quantity" class="input" type="number" step="0.001" min="0.001">
        <input v-model="adj.notes" class="input" placeholder="Reason">
        <button type="submit" class="btn-primary">Record</button>
      </form>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({ permissions: [PERMISSIONS.kitchenReconcile] })

const api = useRestaurantApi()
const toast = useToast()
const items = ref<any[]>([])
const menu = ref<any[]>([])
const sales = reactive({ menu_item_id: '', quantity: '1', source: 'pos' })
const count = reactive({ inventory_item_id: '', physical_quantity: '0' })

async function load() {
  items.value = (await api.inventory({ per_page: 100 }) as any).data
  menu.value = (await api.items({ per_page: 100 }) as any).data
  if (menu.value[0]) sales.menu_item_id = String(menu.value[0].id)
  if (items.value[0]) count.inventory_item_id = String(items.value[0].id)
}

async function submitSales() {
  try {
    await api.kitchenSales({
      lines: [{ menu_item_id: Number(sales.menu_item_id), quantity: sales.quantity, source: sales.source }],
    })
    toast.success('Summary saved. POS revenue was not duplicated.')
  }
  catch (error: any) { toast.error(error.message) }
}

async function submitCount() {
  try {
    await api.recordCount({
      location: 'kitchen',
      lines: [{ inventory_item_id: Number(count.inventory_item_id), physical_quantity: count.physical_quantity }],
    })
    toast.success('Count saved without a second recipe deduction.')
  }
  catch (error: any) { toast.error(error.message) }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Kitchen close" description="Summaries reconcile against POS. They do not create another sale." />
    <form class="card mb-4 grid gap-2 p-4 md:grid-cols-4" @submit.prevent="submitSales">
      <select v-model="sales.menu_item_id" class="input">
        <option v-for="item in menu" :key="item.id" :value="item.id">{{ item.name }}</option>
      </select>
      <input v-model="sales.quantity" class="input" placeholder="Quantity">
      <select v-model="sales.source" class="input">
        <option value="pos">Already in POS</option>
        <option value="manual">Outside POS</option>
      </select>
      <button class="btn-primary" type="submit">Save summary</button>
    </form>
    <form class="card grid gap-2 p-4 md:grid-cols-3" @submit.prevent="submitCount">
      <select v-model="count.inventory_item_id" class="input">
        <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
      </select>
      <input v-model="count.physical_quantity" class="input" placeholder="Physical quantity">
      <button class="btn-secondary" type="submit">Save closing count</button>
    </form>
  </div>
</template>

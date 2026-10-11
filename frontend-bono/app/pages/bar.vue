<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({ permissions: [PERMISSIONS.barRedeem, PERMISSIONS.barClose] })

const api = useRestaurantApi()
const toast = useToast()
const types = ref<any[]>([])
const items = ref<any[]>([])
const redeem = reactive({
  bono_type_id: '',
  menu_item_id: '',
  inventory_item_id: '',
  quantity: '1',
  waiter_id: '',
})
const count = reactive({ inventory_item_id: '', physical_quantity: '0' })

const selectedType = computed(() => types.value.find((type) => String(type.id) === String(redeem.bono_type_id)))
const linkedMenuItems = computed(() => selectedType.value?.menu_items ?? selectedType.value?.menuItems ?? [])
const needsMenuItem = computed(() => linkedMenuItems.value.length > 0)

watch(
  () => redeem.bono_type_id,
  () => {
    if (!needsMenuItem.value) {
      redeem.menu_item_id = ''
      return
    }
    const stillValid = linkedMenuItems.value.some((item: any) => String(item.id) === String(redeem.menu_item_id))
    if (!stillValid) redeem.menu_item_id = String(linkedMenuItems.value[0]?.id ?? '')
  },
)

async function load() {
  try {
    const [typeResponse, itemResponse] = await Promise.all([
      api.bonoTypes(),
      api.inventory({ per_page: 100 }),
    ])
    types.value = ((typeResponse as any).data as any[]).filter((type) => ['bar', 'any'].includes(type.redemption_department))
    items.value = (itemResponse as any).data
    if (types.value[0]) redeem.bono_type_id = String(types.value[0].id)
    if (items.value[0]) {
      redeem.inventory_item_id = String(items.value[0].id)
      count.inventory_item_id = String(items.value[0].id)
    }
  }
  catch (error: any) {
    toast.error(error.message)
  }
}

async function submitRedeem() {
  if (needsMenuItem.value && !redeem.menu_item_id) {
    toast.error('Choose which drink this bono covers.')
    return
  }

  try {
    await api.redeemBono({
      bono_type_id: Number(redeem.bono_type_id),
      menu_item_id: redeem.menu_item_id ? Number(redeem.menu_item_id) : null,
      inventory_item_id: Number(redeem.inventory_item_id),
      quantity: redeem.quantity,
      waiter_id: redeem.waiter_id ? Number(redeem.waiter_id) : null,
      idempotency_key: `bar-${Date.now()}`,
    })
    toast.success('Drink issued against the bono. Bar stock moved once.')
  }
  catch (error: any) { toast.error(error.message) }
}

async function submitCount() {
  try {
    await api.recordCount({
      location: 'bar',
      lines: [{ inventory_item_id: Number(count.inventory_item_id), physical_quantity: count.physical_quantity }],
    })
    toast.success('Count saved. The ledger was not overwritten.')
  }
  catch (error: any) { toast.error(error.message) }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Bar" description="Redeem drinks against bonos and record the physical close." />
    <form class="card mb-4 grid gap-2 p-4 md:grid-cols-2" @submit.prevent="submitRedeem">
      <h2 class="font-semibold md:col-span-2">Redeem a bono</h2>
      <select v-model="redeem.bono_type_id" class="input">
        <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
      </select>
      <select v-if="needsMenuItem" v-model="redeem.menu_item_id" class="input" required>
        <option v-for="item in linkedMenuItems" :key="item.id" :value="item.id">{{ item.name }}</option>
      </select>
      <select v-model="redeem.inventory_item_id" class="input">
        <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
      </select>
      <input v-model="redeem.quantity" class="input" placeholder="Quantity">
      <input v-model="redeem.waiter_id" class="input" placeholder="Waiter user id (optional)">
      <button class="btn-primary" type="submit">Record redemption</button>
    </form>
    <form class="card grid gap-2 p-4 md:grid-cols-3" @submit.prevent="submitCount">
      <h2 class="font-semibold md:col-span-3">Physical closing count</h2>
      <select v-model="count.inventory_item_id" class="input">
        <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
      </select>
      <input v-model="count.physical_quantity" class="input" placeholder="Physical quantity">
      <button class="btn-secondary" type="submit">Save count</button>
    </form>
  </div>
</template>

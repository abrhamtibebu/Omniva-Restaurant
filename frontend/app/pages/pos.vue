<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
import type { MenuCategory, MenuItem, Order } from '~/types/domain'

definePageMeta({
  layout: 'fullscreen',
  permissions: [PERMISSIONS.ordersCreate],
})

const route = useRoute()
const api = useRestaurantApi()
const toast = useToast()
const auth = useAuthStore()

const categories = ref<MenuCategory[]>([])
const items = ref<MenuItem[]>([])
const categoryId = ref<number | 'all'>('all')
const search = ref('')
const order = ref<Order | null>(null)
const loading = ref(true)
const busy = ref(false)
const picker = ref<MenuItem | null>(null)
const showPicker = computed({
  get: () => picker.value !== null,
  set: (open: boolean) => { if (!open) picker.value = null },
})
const variantId = ref<number | null>(null)
const modifierIds = ref<number[]>([])
const notes = ref('')
const payOpen = ref(false)
const payAmount = ref('')
const payMethod = ref('cash')

const filteredItems = computed(() => {
  return items.value.filter((item) => {
    const catOk = categoryId.value === 'all' || item.category_id === categoryId.value
    const q = search.value.trim().toLowerCase()
    return catOk && item.available && item.active && (!q || item.name.toLowerCase().includes(q))
  })
})

async function bootstrap() {
  loading.value = true
  try {
    const [catRes, itemRes] = await Promise.all([
      api.categories(),
      api.items({ per_page: 100, available_only: true }),
    ])
    categories.value = catRes.data
    items.value = itemRes.data

    const existing = route.query.order
    if (typeof existing === 'string') {
      order.value = (await api.order(Number(existing))).data
      return
    }

    const tableId = route.query.table ? Number(route.query.table) : null
    const created = await api.createOrder({
      table_id: tableId,
      type: tableId ? 'dine_in' : 'takeaway',
    })
    order.value = created.data
  }
  catch (e: any) {
    toast.error(e.message ?? 'Could not open POS')
  }
  finally {
    loading.value = false
  }
}

async function refreshOrder() {
  if (!order.value) return
  order.value = (await api.order(order.value.id)).data
}

function openPicker(item: MenuItem) {
  picker.value = item
  variantId.value = item.variants?.find((v) => v.active)?.id ?? null
  modifierIds.value = []
  notes.value = ''
}

async function addToOrder() {
  if (!order.value || !picker.value) return
  busy.value = true
  try {
    await api.addItem(order.value.id, {
      menu_item_id: picker.value.id,
      menu_item_variant_id: variantId.value,
      quantity: 1,
      notes: notes.value || null,
      modifier_ids: modifierIds.value,
    })
    picker.value = null
    await refreshOrder()
  }
  catch (e: any) {
    toast.error(e.message)
  }
  finally {
    busy.value = false
  }
}

async function changeQty(itemId: number, quantity: number) {
  if (!order.value) return
  if (quantity < 1) {
    await api.removeItem(order.value.id, itemId)
  }
  else {
    await api.updateItemQty(order.value.id, itemId, { quantity })
  }
  await refreshOrder()
}

async function sendKitchen() {
  if (!order.value) return
  busy.value = true
  try {
    order.value = (await api.submitOrder(order.value.id)).data
    toast.success('Sent to kitchen')
  }
  catch (e: any) {
    toast.error(e.message)
  }
  finally {
    busy.value = false
  }
}

async function takePayment() {
  if (!order.value) return
  busy.value = true
  try {
    await api.pay(order.value.id, {
      amount: payAmount.value || order.value.balance_due,
      payment_method: payMethod.value,
    })
    await refreshOrder()
    toast.success('Payment recorded')
    if (order.value.status === 'completed') {
      payOpen.value = false
      await navigateTo(`/orders/${order.value.id}`)
    }
  }
  catch (e: any) {
    toast.error(e.message)
  }
  finally {
    busy.value = false
  }
}

onMounted(bootstrap)
</script>

<template>
  <div class="flex h-screen flex-col bg-slate-100 lg:flex-row">
    <aside class="hidden w-48 shrink-0 overflow-y-auto bg-slate-900 p-3 lg:block">
      <NuxtLink to="/tables" class="mb-4 block text-sm text-slate-400 hover:text-white">← Tables</NuxtLink>
      <button
        type="button"
        class="mb-1 w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium"
        :class="categoryId === 'all' ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-white/5'"
        @click="categoryId = 'all'"
      >
        All
      </button>
      <button
        v-for="category in categories"
        :key="category.id"
        type="button"
        class="mb-1 w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium"
        :class="categoryId === category.id ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-white/5'"
        @click="categoryId = category.id"
      >
        {{ category.name }}
      </button>
    </aside>

    <section class="flex min-h-0 flex-1 flex-col p-4">
      <div class="mb-3 flex items-center gap-3">
        <input v-model="search" class="input" placeholder="Search menu">
        <StatusBadge v-if="order" :status="order.status" size="md" />
      </div>
      <div v-if="loading" class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
        <div v-for="n in 8" :key="n" class="h-28 animate-pulse rounded-xl bg-white" />
      </div>
      <div v-else class="grid grid-cols-2 gap-3 overflow-y-auto md:grid-cols-3 xl:grid-cols-4">
        <button
          v-for="item in filteredItems"
          :key="item.id"
          type="button"
          class="card p-4 text-left hover:ring-2 hover:ring-brand-200"
          @click="openPicker(item)"
        >
          <p class="font-semibold">{{ item.name }}</p>
          <p class="mt-1 text-sm text-slate-500">{{ formatMoney(item.base_price, auth.currency) }}</p>
        </button>
      </div>
    </section>

    <aside class="flex w-full flex-col border-t bg-white lg:w-96 lg:border-l lg:border-t-0">
      <div class="border-b px-4 py-3">
        <p class="font-semibold">{{ order?.order_number ?? 'New order' }}</p>
        <p class="text-sm text-slate-500">{{ order?.table ? `Table ${order.table.name}` : 'Takeaway' }}</p>
      </div>
      <div class="flex-1 overflow-y-auto p-4">
        <div v-for="line in order?.items ?? []" :key="line.id" class="mb-3 rounded-lg bg-slate-50 p-3">
          <div class="flex justify-between gap-2">
            <p class="font-medium">{{ line.item_name_snapshot }}</p>
            <p class="font-semibold">{{ formatAmount(line.subtotal) }}</p>
          </div>
          <p v-for="mod in line.modifiers" :key="mod.id" class="text-xs text-slate-500">+ {{ mod.name }}</p>
          <p v-if="line.notes" class="text-xs text-slate-500">{{ line.notes }}</p>
          <div class="mt-2 flex items-center gap-2">
            <button type="button" class="btn-secondary px-2 py-1" @click="changeQty(line.id, line.quantity - 1)">-</button>
            <span class="w-6 text-center">{{ line.quantity }}</span>
            <button type="button" class="btn-secondary px-2 py-1" @click="changeQty(line.id, line.quantity + 1)">+</button>
          </div>
        </div>
      </div>
      <div class="space-y-1 border-t px-4 py-3 text-sm">
        <div class="flex justify-between"><span>Subtotal</span><span>{{ formatAmount(order?.subtotal) }}</span></div>
        <div class="flex justify-between"><span>Discount</span><span>{{ formatAmount(order?.discount) }}</span></div>
        <div class="flex justify-between"><span>Tax</span><span>{{ formatAmount(order?.tax) }}</span></div>
        <div class="flex justify-between text-base font-bold"><span>Total</span><span>{{ formatMoney(order?.total, auth.currency) }}</span></div>
      </div>
      <div class="grid grid-cols-2 gap-2 p-3">
        <button type="button" class="btn-secondary btn-touch" :disabled="busy" @click="sendKitchen">Send to kitchen</button>
        <button
          v-if="auth.can(PERMISSIONS.paymentsCreate)"
          type="button"
          class="btn-primary btn-touch"
          :disabled="busy"
          @click="payOpen = true; payAmount = order?.balance_due ?? order?.total ?? ''"
        >
          Pay
        </button>
      </div>
    </aside>

    <AppModal v-model="showPicker" :title="picker?.name">
      <div v-if="picker" class="space-y-4">
        <div v-if="picker.variants?.length">
          <p class="label">Size</p>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="variant in picker.variants.filter(v => v.active)"
              :key="variant.id"
              type="button"
              class="rounded-lg px-3 py-2 text-sm ring-1"
              :class="variantId === variant.id ? 'bg-brand-600 text-white ring-brand-600' : 'ring-slate-200'"
              @click="variantId = variant.id"
            >
              {{ variant.name }} · {{ formatAmount(variant.price) }}
            </button>
          </div>
        </div>
        <div v-if="picker.modifiers?.length">
          <p class="label">Add-ons</p>
          <label v-for="mod in picker.modifiers.filter(m => m.active)" :key="mod.id" class="flex items-center gap-2 py-1 text-sm">
            <input v-model="modifierIds" type="checkbox" :value="mod.id">
            {{ mod.name }} ({{ formatAmount(mod.price) }})
          </label>
        </div>
        <FormField label="Kitchen notes">
          <input v-model="notes" class="input" placeholder="No onion, extra spicy…">
        </FormField>
      </div>
      <template #footer>
        <button type="button" class="btn-secondary" @click="showPicker = false">Cancel</button>
        <button type="button" class="btn-primary" :disabled="busy" @click="addToOrder">Add</button>
      </template>
    </AppModal>

    <AppModal v-model="payOpen" title="Take payment">
      <FormField label="Amount">
        <input v-model="payAmount" class="input" type="number" step="0.01">
      </FormField>
      <FormField label="Method" class="mt-3">
        <select v-model="payMethod" class="input">
          <option value="cash">Cash</option>
          <option value="card">Card</option>
          <option value="mobile_money">Mobile money</option>
          <option value="bank_transfer">Bank transfer</option>
          <option value="other">Other</option>
        </select>
      </FormField>
      <p class="mt-3 text-sm text-slate-500">Balance due: {{ formatMoney(order?.balance_due ?? order?.total, auth.currency) }}</p>
      <template #footer>
        <button type="button" class="btn-secondary" @click="payOpen = false">Cancel</button>
        <button type="button" class="btn-primary" :disabled="busy" @click="takePayment">Record payment</button>
      </template>
    </AppModal>
  </div>
</template>

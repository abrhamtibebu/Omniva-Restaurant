<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
import type { Order } from '~/types/domain'

definePageMeta({ permissions: [PERMISSIONS.ordersView, PERMISSIONS.ordersViewOwn, PERMISSIONS.paymentsView] })

const route = useRoute()
const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const order = ref<Order | null>(null)
const payOpen = ref(false)
const payAmount = ref('')
const payMethod = ref('cash')

async function load() {
  order.value = (await api.order(Number(route.params.id))).data
}

async function serve() {
  try {
    order.value = (await api.serveOrder(order.value!.id)).data
    toast.success('Marked served')
  }
  catch (e: any) { toast.error(e.message) }
}

async function pay() {
  try {
    await api.pay(order.value!.id, { amount: payAmount.value, payment_method: payMethod.value })
    await load()
    payOpen.value = false
    toast.success('Payment recorded')
  }
  catch (e: any) { toast.error(e.message) }
}

onMounted(load)
</script>

<template>
  <div v-if="order">
    <PageHeader :title="order.order_number" :description="order.table ? `Table ${order.table.name}` : 'Takeaway'">
      <template #actions>
        <StatusBadge :status="order.status" size="md" />
        <NuxtLink v-if="auth.can(PERMISSIONS.receiptsPrint)" :to="`/orders/${order.id}/receipt`" class="btn-secondary">Receipt</NuxtLink>
        <button v-if="order.status === 'ready' && auth.can(PERMISSIONS.ordersServe)" type="button" class="btn-primary" @click="serve">Mark served</button>
        <button v-if="auth.can(PERMISSIONS.paymentsCreate) && Number(order.balance_due) > 0" type="button" class="btn-primary" @click="payOpen = true; payAmount = order.balance_due ?? ''">Pay</button>
      </template>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-3">
      <div class="card p-4 lg:col-span-2">
        <h2 class="font-semibold">Items</h2>
        <ul class="mt-3 divide-y">
          <li v-for="item in order.items" :key="item.id" class="py-3">
            <div class="flex justify-between">
              <span>{{ item.quantity }} × {{ item.item_name_snapshot }}</span>
              <span>{{ formatAmount(item.subtotal) }}</span>
            </div>
            <p v-for="mod in item.modifiers" :key="mod.id" class="text-sm text-slate-500">{{ mod.name }}</p>
            <p v-if="item.notes" class="text-sm text-amber-700">{{ item.notes }}</p>
          </li>
        </ul>
      </div>
      <div class="card space-y-2 p-4 text-sm">
        <div class="flex justify-between"><span>Subtotal</span><span>{{ formatAmount(order.subtotal) }}</span></div>
        <div class="flex justify-between"><span>Discount</span><span>{{ formatAmount(order.discount) }}</span></div>
        <div class="flex justify-between"><span>Tax</span><span>{{ formatAmount(order.tax) }}</span></div>
        <div class="flex justify-between font-bold"><span>Total</span><span>{{ formatMoney(order.total, auth.currency) }}</span></div>
        <div class="flex justify-between"><span>Paid</span><span>{{ formatAmount(order.amount_paid) }}</span></div>
        <div class="flex justify-between"><span>Balance</span><span>{{ formatAmount(order.balance_due) }}</span></div>
        <ul class="mt-4 space-y-1 border-t pt-3">
          <li v-for="payment in order.payments" :key="payment.id">
            {{ humanize(payment.payment_method) }} · {{ formatAmount(payment.amount) }}
            <StatusBadge :status="payment.status" />
          </li>
        </ul>
      </div>
    </div>

    <AppModal v-model="payOpen" title="Take payment">
      <FormField label="Amount"><input v-model="payAmount" class="input" type="number" step="0.01"></FormField>
      <FormField label="Method" class="mt-3">
        <select v-model="payMethod" class="input">
          <option value="cash">Cash</option>
          <option value="card">Card</option>
          <option value="mobile_money">Mobile money</option>
          <option value="bank_transfer">Bank transfer</option>
        </select>
      </FormField>
      <template #footer>
        <button type="button" class="btn-secondary" @click="payOpen = false">Cancel</button>
        <button type="button" class="btn-primary" @click="pay">Record</button>
      </template>
    </AppModal>
  </div>
</template>

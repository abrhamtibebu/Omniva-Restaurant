<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
import type { Order } from '~/types/domain'

definePageMeta({
  layout: 'fullscreen',
  permissions: [PERMISSIONS.receiptsPrint, PERMISSIONS.paymentsView, PERMISSIONS.ordersView],
})

const route = useRoute()
const api = useRestaurantApi()
const auth = useAuthStore()
const order = ref<Order | null>(null)

onMounted(async () => {
  order.value = (await api.order(Number(route.params.id))).data
})
</script>

<template>
  <div v-if="order" class="mx-auto max-w-md bg-white p-6 print:max-w-none">
    <div class="mb-4 flex justify-between print:hidden">
      <NuxtLink :to="`/orders/${order.id}`" class="btn-secondary">Back</NuxtLink>
      <button type="button" class="btn-primary" @click="print()">Print</button>
    </div>
    <header class="text-center">
      <h1 class="text-xl font-bold">{{ auth.branch?.restaurant?.name ?? 'Betedesta' }}</h1>
      <p>{{ auth.branch?.name }}</p>
      <p class="text-sm text-slate-500">{{ auth.branch?.address }}</p>
    </header>
    <p class="mt-4 text-center font-semibold">{{ order.order_number }}</p>
    <p class="text-center text-sm">{{ formatDateTime(order.opened_at ?? order.created_at) }}</p>
    <p class="text-center text-sm">Table {{ order.table?.name ?? 'Takeaway' }} · Waiter {{ order.waiter?.name }}</p>
    <table class="mt-4 w-full text-sm">
      <tbody>
        <tr v-for="item in order.items" :key="item.id">
          <td class="py-1">{{ item.quantity }} × {{ item.item_name_snapshot }}
            <div v-for="mod in item.modifiers" :key="mod.id" class="text-xs text-slate-500">{{ mod.name }}</div>
          </td>
          <td class="py-1 text-right">{{ formatAmount(item.subtotal) }}</td>
        </tr>
      </tbody>
    </table>
    <div class="mt-4 space-y-1 border-t pt-3 text-sm">
      <div class="flex justify-between"><span>Subtotal</span><span>{{ formatAmount(order.subtotal) }}</span></div>
      <div class="flex justify-between"><span>Discount</span><span>{{ formatAmount(order.discount) }}</span></div>
      <div class="flex justify-between"><span>Tax</span><span>{{ formatAmount(order.tax) }}</span></div>
      <div class="flex justify-between font-bold"><span>Total</span><span>{{ formatMoney(order.total, auth.currency) }}</span></div>
    </div>
    <div class="mt-3 space-y-1 text-sm">
      <div v-for="payment in order.payments" :key="payment.id" class="flex justify-between">
        <span>{{ humanize(payment.payment_method) }}</span>
        <span>{{ formatAmount(payment.amount) }}</span>
      </div>
      <div class="flex justify-between font-semibold"><span>Balance</span><span>{{ formatAmount(order.balance_due) }}</span></div>
    </div>
    <p class="mt-6 text-center text-sm">Ameseginalehu · Thank you for dining at Betedesta.</p>
  </div>
</template>

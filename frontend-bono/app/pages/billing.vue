<script setup lang="ts">
const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const billing = ref<any>(null)

async function load() {
  billing.value = (await api.billing() as any).data
  if (auth.user) await auth.fetchUser()
}

async function pay(invoiceId: number) {
  try {
    await api.payInvoice(invoiceId)
    toast.success('Payment recorded. Bono control is active for this period.')
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
    <PageHeader title="Billing" description="Bono control stays available during the trial and after a recorded payment." />

    <div v-if="billing?.subscription" class="card mb-4 p-4 text-sm">
      <p class="font-semibold">{{ billing.subscription.plan.name }}</p>
      <p class="mt-1">Status: {{ billing.subscription.status.replaceAll('_', ' ') }}</p>
      <p class="mt-1">
        {{ billing.subscription.plan.price }} {{ billing.subscription.plan.currency }} / month
      </p>
      <p v-if="billing.subscription.trial_ends_at" class="mt-1">Trial ends {{ billing.subscription.trial_ends_at }}</p>
      <p v-if="billing.subscription.current_period_end" class="mt-1">Period ends {{ billing.subscription.current_period_end }}</p>
      <p class="mt-2">{{ billing.subscription.entitled ? 'Bono control is open.' : 'Bono control is locked until the invoice is paid.' }}</p>
    </div>
    <p v-else class="card mb-4 p-4 text-sm text-slate-500">This restaurant has no Bono control subscription.</p>

    <div class="card">
      <AppTable :rows="billing?.invoices ?? []" :columns="[
        { key: 'id', label: 'Invoice' },
        { key: 'amount', label: 'Amount' },
        { key: 'status', label: 'Status' },
        { key: 'due_at', label: 'Due' },
      ]">
        <template #cell:amount="{ row }">{{ row.amount }} {{ row.currency }}</template>
        <template #cell:status="{ row }"><StatusBadge :status="row.status" /></template>
      </AppTable>
    </div>

    <div v-if="auth.isPlatform && billing?.platform_invoices?.length" class="card mt-4 p-4">
      <h2 class="font-semibold">Open invoices</h2>
      <ul class="mt-3 space-y-2 text-sm">
        <li v-for="invoice in billing.platform_invoices" :key="invoice.id" class="flex items-center justify-between gap-3">
          <span>{{ invoice.restaurant?.name ?? 'Restaurant' }} · {{ invoice.amount }} {{ invoice.currency }}</span>
          <button class="btn-primary" type="button" @click="pay(invoice.id)">Record payment</button>
        </li>
      </ul>
    </div>
  </div>
</template>

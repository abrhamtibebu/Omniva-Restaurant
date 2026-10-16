<script setup lang="ts">
const { t, te } = useI18n()
const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const billing = ref<any>(null)
const payActions = useBusyMap()

const columns = computed(() => [
  { key: 'id', label: t('common.invoice') },
  { key: 'amount', label: t('common.amount') },
  { key: 'status', label: t('common.status') },
  { key: 'due_at', label: t('common.due') },
])

function statusLabel(status: string): string {
  const key = `status.${status}`
  return te(key) ? t(key) : humanize(status)
}

async function load() {
  billing.value = (await api.billing() as any).data
  if (auth.user) await auth.fetchUser()
}

async function pay(invoiceId: number) {
  await payActions.run(invoiceId, async () => {
    try {
      await api.payInvoice(invoiceId)
      toast.success(t('billing.paymentRecorded'))
      await load()
    }
    catch (error: any) {
      toast.error(error.message)
    }
  })
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader :title="t('billing.title')" :description="t('billing.description')" />

    <div v-if="billing?.subscription" class="card mb-4 p-4 text-sm">
      <p class="font-semibold">{{ billing.subscription.plan.name }}</p>
      <p class="mt-1">{{ t('billing.status', { status: statusLabel(billing.subscription.status) }) }}</p>
      <p class="mt-1">
        {{ t('billing.pricePerMonth', { price: billing.subscription.plan.price, currency: billing.subscription.plan.currency }) }}
      </p>
      <p v-if="billing.subscription.trial_ends_at" class="mt-1">{{ t('billing.trialEnds', { date: billing.subscription.trial_ends_at }) }}</p>
      <p v-if="billing.subscription.current_period_end" class="mt-1">{{ t('billing.periodEnds', { date: billing.subscription.current_period_end }) }}</p>
      <p class="mt-2">{{ billing.subscription.entitled ? t('billing.entitledOpen') : t('billing.entitledLocked') }}</p>
    </div>
    <p v-else class="card mb-4 p-4 text-sm text-slate-500">{{ t('billing.noSubscription') }}</p>

    <div class="card">
      <AppTable :rows="billing?.invoices ?? []" :columns="columns">
        <template #cell:amount="{ row }">{{ row.amount }} {{ row.currency }}</template>
        <template #cell:status="{ row }"><StatusBadge :status="row.status" /></template>
      </AppTable>
    </div>

    <div v-if="auth.isPlatform && billing?.platform_invoices?.length" class="card mt-4 p-4">
      <h2 class="font-semibold">{{ t('billing.openInvoices') }}</h2>
      <ul class="mt-3 space-y-2 text-sm">
        <li v-for="invoice in billing.platform_invoices" :key="invoice.id" class="flex items-center justify-between gap-3">
          <span>{{ invoice.restaurant?.name ?? t('billing.restaurantFallback') }} · {{ invoice.amount }} {{ invoice.currency }}</span>
          <AppButton :loading="payActions.isBusy(invoice.id)" @click="pay(invoice.id)">{{ t('billing.recordPayment') }}</AppButton>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({ permissions: [PERMISSIONS.operationsView, PERMISSIONS.reportsView] })

const api = useRestaurantApi()
const inbox = ref<any>(null)
const report = ref<any>(null)

async function load() {
  inbox.value = (await api.operationsInbox() as any).data
  report.value = (await api.operationsReports() as any).data
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Operations" description="Pending handovers and reports. Unverified amounts are not treated as POS revenue." />
    <div v-if="inbox" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="(value, key) in inbox" :key="key" class="card p-4">
        <p class="text-sm text-slate-500">{{ String(key).replaceAll('_', ' ') }}</p>
        <p class="mt-1 text-2xl font-bold">{{ value }}</p>
      </div>
    </div>
    <div v-if="report" class="card mt-4 p-4 text-sm">
      <p>POS sales (completed orders): <strong>{{ report.pos_sales }}</strong></p>
      <p class="mt-2">Department summaries count as revenue: <strong>{{ report.department_summaries_are_revenue ? 'yes' : 'no' }}</strong></p>
      <p class="mt-2">Open purchase approvals: {{ report.pending_purchase_approvals }}</p>
      <p class="mt-2">Bono balances: {{ report.bono_balances?.length ?? 0 }} · Stock balances: {{ report.stock_balances?.length ?? 0 }} · Discrepancies: {{ report.discrepancies?.length ?? 0 }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
import BarChart from '~/components/charts/BarChart.vue'
import DonutChart from '~/components/charts/DonutChart.vue'
import SparklineChart from '~/components/charts/SparklineChart.vue'
import { TG_ICONS } from '~/utils/telegramNav'

definePageMeta({ permissions: [PERMISSIONS.operationsView, PERMISSIONS.reportsView] })

const { t, te } = useI18n()
const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const { isTelegram } = useTelegramWebApp()

const loading = ref(true)
const inbox = ref<Record<string, number> | null>(null)
const report = ref<any>(null)

function inboxLabel(key: string): string {
  const i18nKey = `operations.inbox.${key}`
  return te(i18nKey) ? t(i18nKey) : humanize(key)
}

const actionQueue = computed(() => {
  const fromCharts = report.value?.charts?.action_queue as Array<{ key: string, value: number }> | undefined
  if (fromCharts?.length) {
    return fromCharts
      .filter((row) => row.value > 0)
      .map((row) => ({ label: inboxLabel(row.key), value: row.value }))
  }
  if (!inbox.value) return []
  return Object.entries(inbox.value)
    .filter(([, value]) => Number(value) > 0)
    .map(([key, value]) => ({ label: inboxLabel(key), value: Number(value) }))
})

const custodyBars = computed(() => {
  const rows = (report.value?.charts?.custody_by_type ?? []) as Array<{ name: string, quantity: string | number }>
  return rows.map((row) => ({
    label: row.name,
    value: Math.round(toNumber(row.quantity)),
  }))
})

const sparkPoints = computed(() =>
  ((report.value?.charts?.reconciliation_trend ?? []) as Array<{ count: number }>).map((row) => row.count),
)

const actionTotal = computed(() =>
  Number(report.value?.action_items ?? actionQueue.value.reduce((sum, row) => sum + row.value, 0)),
)

const openDiscrepancies = computed(() =>
  ((report.value?.discrepancies ?? []) as any[]).filter((row) => row.status === 'open').slice(0, 5),
)

const highlightCards = computed(() => [
  {
    key: 'sales',
    label: t('operations.kpiPosSales'),
    value: formatMoney(report.value?.pos_sales, auth.currency),
    hint: t('operations.kpiPosHint'),
  },
  {
    key: 'actions',
    label: t('operations.kpiActionItems'),
    value: String(actionTotal.value),
    hint: t('operations.kpiActionHint'),
  },
  {
    key: 'purchases',
    label: t('operations.kpiPurchases'),
    value: String(report.value?.pending_purchase_approvals ?? 0),
    hint: t('operations.kpiPurchasesHint'),
  },
  {
    key: 'discrepancies',
    label: t('operations.kpiDiscrepancies'),
    value: String(inbox.value?.open_discrepancies ?? 0),
    hint: t('operations.kpiDiscrepanciesHint'),
  },
])

const shortcuts = computed(() => [
  { to: '/requests', labelKey: 'nav.requests', icon: TG_ICONS.requests },
  { to: '/receipts', labelKey: 'nav.receipts', icon: TG_ICONS.receipts },
  { to: '/purchases', labelKey: 'nav.purchases', icon: TG_ICONS.purchases },
  { to: '/stock-requests', labelKey: 'nav.stockRequests', icon: TG_ICONS.stock },
])

async function load() {
  loading.value = true
  try {
    const [inboxRes, reportRes] = await Promise.all([
      api.operationsInbox(),
      api.operationsReports(),
    ])
    inbox.value = (inboxRes as any).data
    report.value = (reportRes as any).data
  }
  catch (error: any) {
    toast.error(error.message || t('error.fetchFailed'))
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="ops-dash space-y-4">
    <PageHeader :title="t('operations.title')" :description="t('operations.description')">
      <template #actions>
        <AppButton variant="secondary" :loading="loading" @click="load">
          {{ loading ? t('common.loading') : t('common.retry') }}
        </AppButton>
      </template>
    </PageHeader>

    <div v-if="loading && !report" class="grid gap-3 sm:grid-cols-2">
      <div v-for="n in 4" :key="n" class="card h-24 animate-pulse bg-slate-100" />
    </div>

    <template v-else>
      <!-- Mini App: KPI tiles + shortcuts, no charts -->
      <template v-if="isTelegram">
        <div class="grid grid-cols-2 gap-3">
          <div
            v-for="card in highlightCards"
            :key="card.key"
            class="tg-soft-card space-y-1 !p-3.5"
          >
            <p class="tg-section-label !mb-0">{{ card.label }}</p>
            <p class="text-xl font-bold tracking-tight">{{ card.value }}</p>
            <p class="text-[11px] opacity-60">{{ card.hint }}</p>
          </div>
        </div>

        <div v-if="actionQueue.length" class="tg-soft-card !py-1">
          <p class="tg-section-label px-0 pt-2">{{ t('operations.chartActionQueue') }}</p>
          <div v-for="row in actionQueue" :key="row.label" class="tg-meta-row">
            <div class="tg-meta-body">
              <p class="tg-meta-title">{{ row.label }}</p>
            </div>
            <span class="tg-meta-trail tabular-nums">{{ row.value }}</span>
          </div>
        </div>

        <div>
          <p class="tg-section-label">{{ t('operations.quickLinks') }}</p>
          <div class="tg-shortcut-grid">
            <NuxtLink
              v-for="link in shortcuts"
              :key="link.to"
              :to="link.to"
              class="tg-shortcut"
            >
              <svg class="tg-shortcut-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" :d="link.icon" />
              </svg>
              {{ t(link.labelKey) }}
            </NuxtLink>
          </div>
        </div>

        <div v-if="openDiscrepancies.length" class="tg-soft-card !py-1">
          <p class="tg-section-label px-0 pt-2">{{ t('operations.openDiscrepancies') }}</p>
          <div v-for="row in openDiscrepancies" :key="row.id" class="tg-meta-row">
            <div class="tg-meta-body">
              <p class="tg-meta-title">{{ humanize(row.type) }}</p>
              <p class="tg-meta-sub">{{ row.reason || t('common.dash') }}</p>
            </div>
          </div>
        </div>
      </template>

      <!-- Desktop -->
      <template v-else>
        <div class="grid gap-3 sm:grid-cols-2">
          <div
            v-for="card in highlightCards"
            :key="card.key"
            class="card tg-stat-card ops-kpi p-4"
          >
            <p class="tg-stat-label text-xs font-medium tracking-wide uppercase opacity-70">{{ card.label }}</p>
            <p class="tg-stat-value mt-1 text-2xl font-bold tracking-tight">{{ card.value }}</p>
            <p class="mt-1 text-xs opacity-60">{{ card.hint }}</p>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <section class="card space-y-3 p-4">
            <div class="flex items-center justify-between gap-2">
              <h2 class="font-semibold">{{ t('operations.chartActionQueue') }}</h2>
              <NuxtLink to="/requests" class="text-sm text-brand-700">{{ t('operations.openRequests') }}</NuxtLink>
            </div>
            <DonutChart :items="actionQueue" :empty-label="t('operations.queueClear')" />
          </section>

          <section class="card space-y-3 p-4">
            <div class="flex items-center justify-between gap-2">
              <h2 class="font-semibold">{{ t('operations.chartCustody') }}</h2>
              <NuxtLink to="/bonos" class="text-sm text-brand-700">{{ t('nav.bonos') }}</NuxtLink>
            </div>
            <BarChart :items="custodyBars" :height="Math.max(140, custodyBars.length * 28)" :empty-label="t('operations.noCustody')" />
          </section>
        </div>

        <section class="card space-y-3 p-4">
          <div class="flex items-center justify-between gap-2">
            <div>
              <h2 class="font-semibold">{{ t('operations.chartReconTrend') }}</h2>
              <p class="text-xs opacity-60">{{ t('operations.chartReconHint') }}</p>
            </div>
            <NuxtLink to="/reconciliation" class="text-sm text-brand-700">{{ t('nav.reconciliation') }}</NuxtLink>
          </div>
          <SparklineChart :points="sparkPoints" />
          <div class="flex justify-between text-[10px] opacity-50">
            <span>{{ t('operations.daysAgo', { n: 6 }) }}</span>
            <span>{{ t('operations.today') }}</span>
          </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
          <section class="card p-4">
            <div class="mb-3 flex items-center justify-between">
              <h2 class="font-semibold">{{ t('operations.openDiscrepancies') }}</h2>
              <NuxtLink to="/discrepancies" class="text-sm text-brand-700">{{ t('nav.discrepancies') }}</NuxtLink>
            </div>
            <ul v-if="openDiscrepancies.length" class="space-y-2 text-sm">
              <li
                v-for="row in openDiscrepancies"
                :key="row.id"
                class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-white/5"
              >
                <p class="font-medium">{{ humanize(row.type) }}</p>
                <p class="text-xs opacity-70">{{ row.reason || t('common.dash') }}</p>
              </li>
            </ul>
            <p v-else class="text-sm opacity-60">{{ t('operations.noOpenDiscrepancies') }}</p>
          </section>

          <section class="card space-y-3 p-4">
            <h2 class="font-semibold">{{ t('operations.quickLinks') }}</h2>
            <div class="grid grid-cols-2 gap-2">
              <NuxtLink to="/requests" class="btn-secondary justify-center text-sm">{{ t('nav.requests') }}</NuxtLink>
              <NuxtLink to="/receipts" class="btn-secondary justify-center text-sm">{{ t('nav.receipts') }}</NuxtLink>
              <NuxtLink to="/purchases" class="btn-secondary justify-center text-sm">{{ t('nav.purchases') }}</NuxtLink>
              <NuxtLink to="/stock-requests" class="btn-secondary justify-center text-sm">{{ t('nav.stockRequests') }}</NuxtLink>
            </div>
            <p class="text-xs opacity-60">
              {{ t('operations.noteUnverified') }}
            </p>
          </section>
        </div>
      </template>
    </template>
  </div>
</template>

<style scoped>
.ops-kpi {
  border-left: 3px solid var(--tg-theme-button-color, #2481cc);
}
</style>

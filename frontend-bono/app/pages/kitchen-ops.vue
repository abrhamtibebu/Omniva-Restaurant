<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({ permissions: [PERMISSIONS.kitchenReconcile] })

const { t } = useI18n()
const api = useRestaurantApi()
const toast = useToast()
const { isTelegram } = useTelegramWebApp()
const items = ref<any[]>([])
const menu = ref<any[]>([])
const { busy: savingSales, run: runSales } = useBusyAction()
const { busy: savingCount, run: runCount } = useBusyAction()
const segment = ref<'sales' | 'count'>('sales')
const sales = reactive({ menu_item_id: '', quantity: '1', source: 'pos' })
const count = reactive({ inventory_item_id: '', physical_quantity: '0' })

async function load() {
  items.value = (await api.inventory({ per_page: 100 }) as any).data
  menu.value = (await api.items({ per_page: 100 }) as any).data
  if (menu.value[0]) sales.menu_item_id = String(menu.value[0].id)
  if (items.value[0]) count.inventory_item_id = String(items.value[0].id)
}

async function submitSales() {
  await runSales(async () => {
    try {
      await api.kitchenSales({
        lines: [{ menu_item_id: Number(sales.menu_item_id), quantity: sales.quantity, source: sales.source }],
      })
      toast.success(t('kitchen.summarySaved'))
    }
    catch (error: any) { toast.error(error.message) }
  })
}

async function submitCount() {
  await runCount(async () => {
    try {
      await api.recordCount({
        location: 'kitchen',
        lines: [{ inventory_item_id: Number(count.inventory_item_id), physical_quantity: count.physical_quantity }],
      })
      toast.success(t('kitchen.countSaved'))
    }
    catch (error: any) { toast.error(error.message) }
  })
}

onMounted(load)
</script>

<template>
  <div class="space-y-4">
    <PageHeader :title="t('kitchen.title')" :description="t('kitchen.description')" />

    <template v-if="isTelegram">
      <div class="tg-segment">
        <button type="button" class="tg-segment-btn" :class="{ 'tg-segment-btn-active': segment === 'sales' }" @click="segment = 'sales'">
          {{ t('kitchen.saveSummary') }}
        </button>
        <button type="button" class="tg-segment-btn" :class="{ 'tg-segment-btn-active': segment === 'count' }" @click="segment = 'count'">
          {{ t('kitchen.saveClosingCount') }}
        </button>
      </div>

      <form v-if="segment === 'sales'" class="tg-soft-card space-y-3" @submit.prevent="submitSales">
        <select v-model="sales.menu_item_id" class="input">
          <option v-for="item in menu" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <input v-model="sales.quantity" class="input" :placeholder="t('common.quantity')">
        <select v-model="sales.source" class="input">
          <option value="pos">{{ t('kitchen.alreadyInPos') }}</option>
          <option value="manual">{{ t('kitchen.outsidePos') }}</option>
        </select>
        <AppButton type="submit" :loading="savingSales">{{ t('kitchen.saveSummary') }}</AppButton>
      </form>

      <form v-else class="tg-soft-card space-y-3" @submit.prevent="submitCount">
        <select v-model="count.inventory_item_id" class="input">
          <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <input v-model="count.physical_quantity" class="input" :placeholder="t('kitchen.physicalQuantity')">
        <AppButton type="submit" variant="secondary" :loading="savingCount">{{ t('kitchen.saveClosingCount') }}</AppButton>
      </form>
    </template>

    <template v-else>
      <form class="card mb-4 grid gap-2 p-4 md:grid-cols-4" @submit.prevent="submitSales">
        <select v-model="sales.menu_item_id" class="input">
          <option v-for="item in menu" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <input v-model="sales.quantity" class="input" :placeholder="t('common.quantity')">
        <select v-model="sales.source" class="input">
          <option value="pos">{{ t('kitchen.alreadyInPos') }}</option>
          <option value="manual">{{ t('kitchen.outsidePos') }}</option>
        </select>
        <AppButton type="submit" :loading="savingSales">{{ t('kitchen.saveSummary') }}</AppButton>
      </form>
      <form class="card grid gap-2 p-4 md:grid-cols-3" @submit.prevent="submitCount">
        <select v-model="count.inventory_item_id" class="input">
          <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <input v-model="count.physical_quantity" class="input" :placeholder="t('kitchen.physicalQuantity')">
        <AppButton type="submit" variant="secondary" :loading="savingCount">{{ t('kitchen.saveClosingCount') }}</AppButton>
      </form>
    </template>
  </div>
</template>

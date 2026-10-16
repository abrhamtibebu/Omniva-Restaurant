<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({ permissions: [PERMISSIONS.barRedeem, PERMISSIONS.barClose] })

const { t } = useI18n()
const api = useRestaurantApi()
const toast = useToast()
const route = useRoute()
const { isTelegram } = useTelegramWebApp()
const types = ref<any[]>([])
const items = ref<any[]>([])
const { busy: redeeming, run: runRedeem } = useBusyAction()
const { busy: counting, run: runCount } = useBusyAction()
const segment = ref<'redeem' | 'close'>('redeem')
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
    toast.error(t('bar.chooseDrink'))
    return
  }

  await runRedeem(async () => {
    try {
      await api.redeemBono({
        bono_type_id: Number(redeem.bono_type_id),
        menu_item_id: redeem.menu_item_id ? Number(redeem.menu_item_id) : null,
        inventory_item_id: Number(redeem.inventory_item_id),
        quantity: redeem.quantity,
        waiter_id: redeem.waiter_id ? Number(redeem.waiter_id) : null,
        idempotency_key: `bar-${Date.now()}`,
      })
      toast.success(t('bar.drinkIssued'))
    }
    catch (error: any) { toast.error(error.message) }
  })
}

async function submitCount() {
  await runCount(async () => {
    try {
      await api.recordCount({
        location: 'bar',
        lines: [{ inventory_item_id: Number(count.inventory_item_id), physical_quantity: count.physical_quantity }],
      })
      toast.success(t('bar.countSavedLedger'))
    }
    catch (error: any) { toast.error(error.message) }
  })
}

onMounted(() => {
  void load()
  if (route.hash === '#redeem' || route.hash === '#close') {
    segment.value = route.hash === '#close' ? 'close' : 'redeem'
  }
})
</script>

<template>
  <div class="space-y-4">
    <PageHeader :title="t('bar.title')" :description="t('bar.description')" />

    <template v-if="isTelegram">
      <div class="tg-segment">
        <button
          type="button"
          class="tg-segment-btn"
          :class="{ 'tg-segment-btn-active': segment === 'redeem' }"
          @click="segment = 'redeem'"
        >
          {{ t('bar.segmentRedeem') }}
        </button>
        <button
          type="button"
          class="tg-segment-btn"
          :class="{ 'tg-segment-btn-active': segment === 'close' }"
          @click="segment = 'close'"
        >
          {{ t('bar.segmentClose') }}
        </button>
      </div>

      <form
        v-if="segment === 'redeem'"
        id="redeem"
        class="tg-soft-card space-y-3"
        @submit.prevent="submitRedeem"
      >
        <p class="tg-section-label">{{ t('bar.redeemHeading') }}</p>
        <select v-model="redeem.bono_type_id" class="input">
          <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
        </select>
        <select v-if="needsMenuItem" v-model="redeem.menu_item_id" class="input" required>
          <option v-for="item in linkedMenuItems" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <select v-model="redeem.inventory_item_id" class="input">
          <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <input v-model="redeem.quantity" class="input" :placeholder="t('common.quantity')">
        <input v-model="redeem.waiter_id" class="input" :placeholder="t('bar.waiterIdOptional')">
        <AppButton type="submit" :loading="redeeming">{{ t('bar.recordRedemption') }}</AppButton>
      </form>

      <form
        v-else
        id="close"
        class="tg-soft-card space-y-3"
        @submit.prevent="submitCount"
      >
        <p class="tg-section-label">{{ t('bar.physicalClosing') }}</p>
        <select v-model="count.inventory_item_id" class="input">
          <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <input v-model="count.physical_quantity" class="input" :placeholder="t('bar.physicalQuantity')">
        <AppButton type="submit" variant="secondary" :loading="counting">{{ t('bar.saveCount') }}</AppButton>
      </form>
    </template>

    <template v-else>
      <form class="card mb-4 grid gap-2 p-4 md:grid-cols-2" @submit.prevent="submitRedeem">
        <h2 class="font-semibold md:col-span-2">{{ t('bar.redeemHeading') }}</h2>
        <select v-model="redeem.bono_type_id" class="input">
          <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
        </select>
        <select v-if="needsMenuItem" v-model="redeem.menu_item_id" class="input" required>
          <option v-for="item in linkedMenuItems" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <select v-model="redeem.inventory_item_id" class="input">
          <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <input v-model="redeem.quantity" class="input" :placeholder="t('common.quantity')">
        <input v-model="redeem.waiter_id" class="input" :placeholder="t('bar.waiterIdOptional')">
        <AppButton type="submit" :loading="redeeming">{{ t('bar.recordRedemption') }}</AppButton>
      </form>
      <form class="card grid gap-2 p-4 md:grid-cols-3" @submit.prevent="submitCount">
        <h2 class="font-semibold md:col-span-3">{{ t('bar.physicalClosing') }}</h2>
        <select v-model="count.inventory_item_id" class="input">
          <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <input v-model="count.physical_quantity" class="input" :placeholder="t('bar.physicalQuantity')">
        <AppButton type="submit" variant="secondary" :loading="counting">{{ t('bar.saveCount') }}</AppButton>
      </form>
    </template>
  </div>
</template>

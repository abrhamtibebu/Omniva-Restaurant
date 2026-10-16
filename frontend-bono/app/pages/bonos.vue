<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({
  permissions: [PERMISSIONS.bonosIssue, PERMISSIONS.bonosTypesManage],
})

const { t } = useI18n()
const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const { isTelegram } = useTelegramWebApp()
const types = ref<any[]>([])
const menuItems = ref<any[]>([])
const balances = ref<any[]>([])
const cashiers = ref<any[]>([])
const floorPeople = ref<any[]>([])
const editingTypeId = ref<number | null>(null)

const typeForm = reactive({
  name: '',
  unit_value: '50',
  redemption_department: 'bar',
  menu_item_ids: [] as number[],
})
const issueForm = reactive({ receiver_id: '', bono_type_id: '', quantity: '1' })

const menuPickerId = ref('')
const menuItemsLoading = ref(false)
const menuItemsError = ref('')
const { busy: savingType, run: runSaveType } = useBusyAction()
const { busy: openingBusy, run: runOpening } = useBusyAction()
const { busy: issuingBusy, run: runIssue } = useBusyAction()
const { busy: retryingMenu, run: runRetryMenu } = useBusyAction()

const isAdmin = computed(() => auth.role === 'admin')

function linkedNames(type: any): string {
  const names = (type.menu_items ?? type.menuItems ?? []).map((item: any) => item.name)
  return names.length ? names.join(', ') : t('bonos.noMenuItemsLinked')
}

function resetTypeForm() {
  editingTypeId.value = null
  menuPickerId.value = ''
  Object.assign(typeForm, {
    name: '',
    unit_value: '50',
    redemption_department: 'bar',
    menu_item_ids: [],
  })
}

const availableMenuOptions = computed(() =>
  menuItems.value.filter((item) => !typeForm.menu_item_ids.includes(item.id)),
)

const selectedMenuItems = computed(() =>
  typeForm.menu_item_ids
    .map((id) => menuItems.value.find((item) => item.id === id))
    .filter(Boolean),
)

const menuPickerPlaceholder = computed(() => {
  if (menuItemsLoading.value) return t('bonos.loadingMenu')
  if (menuItemsError.value) return t('bonos.menuFailed')
  if (!menuItems.value.length) return t('bonos.noMenuItems')
  if (!availableMenuOptions.value.length) return t('bonos.allMenuAssigned')
  return t('bonos.selectMenuItem')
})

function addMenuItemFromDropdown() {
  const id = Number(menuPickerId.value)
  if (!id || typeForm.menu_item_ids.includes(id)) return

  typeForm.menu_item_ids.push(id)
  const item = menuItems.value.find((row) => row.id === id)
  if (item) {
    if (!typeForm.name) typeForm.name = item.name
    if (!typeForm.unit_value || typeForm.unit_value === '50') {
      typeForm.unit_value = String(item.base_price ?? typeForm.unit_value)
    }
  }
  menuPickerId.value = ''
}

function removeMenuItem(id: number) {
  const index = typeForm.menu_item_ids.indexOf(id)
  if (index >= 0) typeForm.menu_item_ids.splice(index, 1)
}

function editType(type: any) {
  editingTypeId.value = type.id
  typeForm.name = type.name
  typeForm.unit_value = String(type.unit_value)
  typeForm.redemption_department = type.redemption_department
  typeForm.menu_item_ids = (type.menu_items ?? type.menuItems ?? []).map((item: any) => item.id)
}

async function loadMenuItems() {
  menuItemsLoading.value = true
  menuItemsError.value = ''
  try {
    const page = await api.items({ per_page: 100 })
    menuItems.value = (page.data ?? []).filter((item: any) => item.active !== false)
  }
  catch (error: any) {
    menuItems.value = []
    menuItemsError.value = error?.message || t('bonos.couldNotLoadMenu')
  }
  finally {
    menuItemsLoading.value = false
  }
}

async function retryMenuItems() {
  await runRetryMenu(async () => {
    await loadMenuItems()
  })
}

async function loadPeople() {
  try {
    if (auth.can(PERMISSIONS.bonosIssue) || isAdmin.value) {
      const [cash, floor] = await Promise.all([
        api.bonoPeople({ role: 'cashier' }),
        api.bonoPeople({ role: 'floor' }),
      ])
      cashiers.value = (cash as any).data ?? []
      floorPeople.value = (floor as any).data ?? []
      const issueTargets = isAdmin.value ? cashiers.value : floorPeople.value
      if (!issueForm.receiver_id && issueTargets[0]) issueForm.receiver_id = String(issueTargets[0].id)
    }
  }
  catch {
    // pickers stay empty
  }
}

async function load() {
  try {
    const [typeRes, balRes] = await Promise.all([api.bonoTypes(), api.bonoBalances()])
    types.value = (typeRes as any).data
    balances.value = (balRes as any).data
    if (!issueForm.bono_type_id && types.value[0]) issueForm.bono_type_id = String(types.value[0].id)
  }
  catch (error: any) {
    toast.error(error.message)
  }

  await loadPeople()

  if (auth.can(PERMISSIONS.bonosTypesManage) || auth.can(PERMISSIONS.menuView) || auth.can(PERMISSIONS.menuManage)) {
    await loadMenuItems()
  }
}

async function saveType() {
  if (!typeForm.menu_item_ids.length) {
    toast.error(t('bonos.selectMenuRequired'))
    return
  }

  await runSaveType(async () => {
    const payload = {
      name: typeForm.name,
      unit_value: typeForm.unit_value,
      redemption_department: typeForm.redemption_department,
      menu_item_ids: typeForm.menu_item_ids,
    }

    try {
      if (editingTypeId.value) await api.updateBonoType(editingTypeId.value, payload)
      else await api.createBonoType(payload)
      toast.success(editingTypeId.value ? t('bonos.typeUpdated') : t('bonos.typeSaved'))
      resetTypeForm()
      await load()
    }
    catch (error: any) { toast.error(error.message) }
  })
}

async function opening() {
  await runOpening(async () => {
    try {
      await api.bonoOpening({ lines: [{ bono_type_id: Number(issueForm.bono_type_id), quantity: issueForm.quantity }] })
      toast.success(t('bonos.openingRecorded'))
      await load()
    }
    catch (error: any) { toast.error(error.message) }
  })
}

async function issue() {
  if (!issueForm.receiver_id) {
    toast.error(t('bonos.chooseReceiver'))
    return
  }
  await runIssue(async () => {
    try {
      await api.createBonoHandover({
        kind: 'issue',
        receiver_id: Number(issueForm.receiver_id),
        lines: [{ bono_type_id: Number(issueForm.bono_type_id), quantity: issueForm.quantity }],
      })
      toast.success(t('bonos.handoverIssued'))
      await load()
    }
    catch (error: any) { toast.error(error.message) }
  })
}

const issueTargets = computed(() => (isAdmin.value ? cashiers.value : floorPeople.value))

onMounted(load)
</script>

<template>
  <div>
    <PageHeader :title="t('bonos.title')" :description="t('bonos.description')">
      <template #actions>
        <NuxtLink to="/requests" class="btn-secondary">
          {{ t('bonos.openRequests') }}
        </NuxtLink>
      </template>
    </PageHeader>

    <form
      v-if="auth.can(PERMISSIONS.bonosTypesManage)"
      class="mb-4 space-y-3 p-4"
      :class="isTelegram ? 'tg-soft-card' : 'card'"
      @submit.prevent="saveType"
    >
      <div class="flex flex-wrap items-center justify-between gap-2">
        <p v-if="isTelegram" class="tg-section-label !mb-0">{{ editingTypeId ? t('bonos.editType') : t('bonos.newType') }}</p>
        <h2 v-else class="font-semibold">{{ editingTypeId ? t('bonos.editType') : t('bonos.newType') }}</h2>
        <button v-if="editingTypeId" class="btn-secondary" type="button" @click="resetTypeForm">{{ t('bonos.cancelEdit') }}</button>
      </div>
      <div class="grid gap-2 md:grid-cols-3">
        <input v-model="typeForm.name" class="input" :placeholder="t('bonos.typeNamePlaceholder')" required>
        <input v-model="typeForm.unit_value" class="input" :placeholder="t('bonos.unitValue')" required>
        <select v-model="typeForm.redemption_department" class="input">
          <option value="bar">{{ t('departments.bar') }}</option>
          <option value="kitchen">{{ t('departments.kitchen') }}</option>
          <option value="any">{{ t('departments.any') }}</option>
        </select>
      </div>
      <div class="space-y-2">
        <p class="text-sm text-slate-600">{{ t('bonos.menuItemsCovered') }}</p>
        <div class="flex flex-col gap-2 sm:flex-row">
          <select
            v-model="menuPickerId"
            class="input flex-1"
            :disabled="menuItemsLoading || !availableMenuOptions.length"
          >
            <option disabled value="">
              {{ menuPickerPlaceholder }}
            </option>
            <option v-for="item in availableMenuOptions" :key="item.id" :value="String(item.id)">
              {{ item.name }} · {{ item.base_price }}{{ item.category?.name ? ` · ${item.category.name}` : '' }}
            </option>
          </select>
          <button class="btn-secondary" type="button" :disabled="!menuPickerId" @click="addMenuItemFromDropdown">
            {{ t('bonos.addToBono') }}
          </button>
        </div>
        <p v-if="menuItemsError" class="text-sm text-red-600">
          {{ menuItemsError }}
          <AppButton variant="ghost" size="sm" class="ml-2 underline" :loading="retryingMenu" @click="retryMenuItems">
            {{ t('common.retry') }}
          </AppButton>
        </p>
        <ul v-if="selectedMenuItems.length" class="flex flex-wrap gap-2">
          <li
            v-for="item in selectedMenuItems"
            :key="item!.id"
            class="flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-sm"
          >
            <span>{{ item!.name }}</span>
            <button type="button" class="text-slate-500 hover:text-red-600" @click="removeMenuItem(item!.id)">{{ t('common.remove') }}</button>
          </li>
        </ul>
        <NuxtLink
          v-if="auth.can(PERMISSIONS.menuManage) || auth.can(PERMISSIONS.menuView)"
          to="/menu"
          class="inline-block text-sm text-brand-700"
        >
          {{ t('bonos.manageCatalogue') }}
        </NuxtLink>
      </div>
      <AppButton type="submit" :loading="savingType">{{ editingTypeId ? t('bonos.updateType') : t('bonos.addType') }}</AppButton>
    </form>

    <div
      v-if="auth.can(PERMISSIONS.bonosTypesManage)"
      class="mb-4 p-4"
      :class="isTelegram ? 'tg-soft-card !py-1' : 'card'"
    >
      <p v-if="isTelegram" class="tg-section-label px-0 pt-2">{{ t('bonos.types') }}</p>
      <h2 v-else class="font-semibold">{{ t('bonos.types') }}</h2>
      <ul :class="isTelegram ? '' : 'mt-3 space-y-2 text-sm'">
        <li
          v-for="type in types"
          :key="type.id"
          :class="isTelegram ? 'tg-meta-row' : 'flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-2 last:border-0'"
        >
          <div :class="isTelegram ? 'tg-meta-body' : ''">
            <p :class="isTelegram ? 'tg-meta-title' : 'font-medium'">{{ type.name }} · {{ type.unit_value }}</p>
            <p :class="isTelegram ? 'tg-meta-sub' : 'text-slate-600'">{{ linkedNames(type) }}</p>
          </div>
          <button class="text-sm text-brand-700" type="button" @click="editType(type)">{{ t('common.edit') }}</button>
        </li>
        <li v-if="!types.length" class="text-slate-500 py-2">{{ t('bonos.noTypes') }}</li>
      </ul>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <div class="p-4" :class="isTelegram ? 'tg-soft-card !py-1' : 'card'">
        <p v-if="isTelegram" class="tg-section-label px-0 pt-2">{{ t('bonos.balances') }}</p>
        <h2 v-else class="font-semibold">{{ t('bonos.balances') }}</h2>
        <ul :class="isTelegram ? '' : 'mt-3 space-y-1 text-sm'">
          <li v-for="row in balances" :key="row.id" :class="isTelegram ? 'tg-meta-row' : ''">
            <div :class="isTelegram ? 'tg-meta-body' : ''">
              <p :class="isTelegram ? 'tg-meta-title' : ''">{{ row.bono_type?.name }}</p>
              <p :class="isTelegram ? 'tg-meta-sub' : ''">{{ row.holder_type }} {{ row.holder_id || t('common.pool') }}</p>
            </div>
            <span :class="isTelegram ? 'tg-meta-trail tabular-nums' : ''">{{ row.quantity }}</span>
          </li>
          <li v-if="!balances.length" class="text-slate-500 py-2">{{ t('bonos.noCustody') }}</li>
        </ul>
      </div>
      <div class="space-y-4 p-4" :class="isTelegram ? 'tg-soft-card' : 'card'">
        <template v-if="auth.can(PERMISSIONS.bonosTypesManage)">
          <div class="space-y-2">
            <p v-if="isTelegram" class="tg-section-label">{{ t('bonos.openingBalance') }}</p>
            <h2 v-else class="font-semibold">{{ t('bonos.openingBalance') }}</h2>
            <select v-model="issueForm.bono_type_id" class="input">
              <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
            <input v-model="issueForm.quantity" class="input" :placeholder="t('common.quantity')">
            <AppButton variant="secondary" :loading="openingBusy" @click="opening">{{ t('bonos.recordOpening') }}</AppButton>
          </div>
        </template>
        <template v-if="auth.can(PERMISSIONS.bonosIssue)">
          <div class="space-y-2">
            <p v-if="isTelegram" class="tg-section-label">{{ isAdmin ? t('bonos.issueToCashier') : t('bonos.issueToFloor') }}</p>
            <h2 v-else class="font-semibold">{{ isAdmin ? t('bonos.issueToCashier') : t('bonos.issueToFloor') }}</h2>
            <select v-model="issueForm.bono_type_id" class="input">
              <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
            <select v-model="issueForm.receiver_id" class="input" required>
              <option disabled value="">{{ t('common.selectPerson') }}</option>
              <option v-for="person in issueTargets" :key="person.id" :value="String(person.id)">
                {{ person.name }} · {{ person.role?.name || person.role?.slug }}
              </option>
            </select>
            <input v-model="issueForm.quantity" class="input" :placeholder="t('common.quantity')">
            <AppButton :loading="issuingBusy" @click="issue">{{ t('bonos.issue') }}</AppButton>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

definePageMeta({
  permissions: [PERMISSIONS.bonosIssue, PERMISSIONS.bonosTypesManage],
})

const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
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

const isAdmin = computed(() => auth.role === 'admin')

function linkedNames(type: any): string {
  const names = (type.menu_items ?? type.menuItems ?? []).map((item: any) => item.name)
  return names.length ? names.join(', ') : 'No menu items'
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
    menuItemsError.value = error?.message || 'Could not load menu items.'
  }
  finally {
    menuItemsLoading.value = false
  }
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
    const [t, b] = await Promise.all([api.bonoTypes(), api.bonoBalances()])
    types.value = (t as any).data
    balances.value = (b as any).data
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
    toast.error('Select at least one menu item for this bono type.')
    return
  }

  const payload = {
    name: typeForm.name,
    unit_value: typeForm.unit_value,
    redemption_department: typeForm.redemption_department,
    menu_item_ids: typeForm.menu_item_ids,
  }

  try {
    if (editingTypeId.value) await api.updateBonoType(editingTypeId.value, payload)
    else await api.createBonoType(payload)
    toast.success(editingTypeId.value ? 'Bono type updated' : 'Bono type saved')
    resetTypeForm()
    await load()
  }
  catch (error: any) { toast.error(error.message) }
}

async function opening() {
  try {
    await api.bonoOpening({ lines: [{ bono_type_id: Number(issueForm.bono_type_id), quantity: issueForm.quantity }] })
    toast.success('Opening balance recorded')
    await load()
  }
  catch (error: any) { toast.error(error.message) }
}

async function issue() {
  if (!issueForm.receiver_id) {
    toast.error('Choose who receives the bonos.')
    return
  }
  try {
    await api.createBonoHandover({
      kind: 'issue',
      receiver_id: Number(issueForm.receiver_id),
      lines: [{ bono_type_id: Number(issueForm.bono_type_id), quantity: issueForm.quantity }],
    })
    toast.success('Handover issued. Confirm it under Requests.')
    await load()
  }
  catch (error: any) { toast.error(error.message) }
}

const issueTargets = computed(() => (isAdmin.value ? cashiers.value : floorPeople.value))

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Bonos" description="Manage bono types, opening balances, and issue custody. Request, approve, and confirm live on Requests.">
      <template #actions>
        <NuxtLink to="/requests" class="btn-secondary">
          Open requests
        </NuxtLink>
      </template>
    </PageHeader>

    <form v-if="auth.can(PERMISSIONS.bonosTypesManage)" class="card mb-4 space-y-3 p-4" @submit.prevent="saveType">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-semibold">{{ editingTypeId ? 'Edit bono type' : 'New bono type' }}</h2>
        <button v-if="editingTypeId" class="btn-secondary" type="button" @click="resetTypeForm">Cancel edit</button>
      </div>
      <div class="grid gap-2 md:grid-cols-3">
        <input v-model="typeForm.name" class="input" placeholder="Bono type name (e.g. Beer)" required>
        <input v-model="typeForm.unit_value" class="input" placeholder="Unit value" required>
        <select v-model="typeForm.redemption_department" class="input">
          <option value="bar">Bar</option>
          <option value="kitchen">Kitchen</option>
          <option value="any">Any</option>
        </select>
      </div>
      <div class="space-y-2">
        <p class="text-sm text-slate-600">Menu items covered by this bono</p>
        <div class="flex flex-col gap-2 sm:flex-row">
          <select
            v-model="menuPickerId"
            class="input flex-1"
            :disabled="menuItemsLoading || !availableMenuOptions.length"
          >
            <option disabled value="">
              {{
                menuItemsLoading
                  ? 'Loading menu…'
                  : menuItemsError
                    ? 'Menu failed to load'
                    : !menuItems.length
                      ? 'No menu items yet — add them under Menu'
                      : !availableMenuOptions.length
                        ? 'All menu items already assigned'
                        : 'Select a menu item to assign…'
              }}
            </option>
            <option v-for="item in availableMenuOptions" :key="item.id" :value="String(item.id)">
              {{ item.name }} · {{ item.base_price }}{{ item.category?.name ? ` · ${item.category.name}` : '' }}
            </option>
          </select>
          <button class="btn-secondary" type="button" :disabled="!menuPickerId" @click="addMenuItemFromDropdown">
            Add to bono
          </button>
        </div>
        <p v-if="menuItemsError" class="text-sm text-red-600">
          {{ menuItemsError }}
          <button type="button" class="ml-2 underline" @click="loadMenuItems">Retry</button>
        </p>
        <ul v-if="selectedMenuItems.length" class="flex flex-wrap gap-2">
          <li
            v-for="item in selectedMenuItems"
            :key="item!.id"
            class="flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-sm"
          >
            <span>{{ item!.name }}</span>
            <button type="button" class="text-slate-500 hover:text-red-600" @click="removeMenuItem(item!.id)">Remove</button>
          </li>
        </ul>
        <NuxtLink
          v-if="auth.can(PERMISSIONS.menuManage) || auth.can(PERMISSIONS.menuView)"
          to="/menu"
          class="inline-block text-sm text-brand-700"
        >
          Manage menu catalogue
        </NuxtLink>
      </div>
      <button class="btn-primary" type="submit">{{ editingTypeId ? 'Update type' : 'Add type' }}</button>
    </form>

    <div v-if="auth.can(PERMISSIONS.bonosTypesManage)" class="card mb-4 p-4">
      <h2 class="font-semibold">Types</h2>
      <ul class="mt-3 space-y-2 text-sm">
        <li v-for="type in types" :key="type.id" class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-2 last:border-0">
          <div>
            <p class="font-medium">{{ type.name }} · {{ type.unit_value }}</p>
            <p class="text-slate-600">{{ linkedNames(type) }}</p>
          </div>
          <button class="text-sm text-brand-700" type="button" @click="editType(type)">Edit</button>
        </li>
        <li v-if="!types.length" class="text-slate-500">No bono types yet.</li>
      </ul>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <div class="card p-4">
        <h2 class="font-semibold">Balances</h2>
        <ul class="mt-3 space-y-1 text-sm">
          <li v-for="row in balances" :key="row.id">
            {{ row.bono_type?.name }} · {{ row.holder_type }} {{ row.holder_id || 'pool' }} · {{ row.quantity }}
          </li>
          <li v-if="!balances.length" class="text-slate-500">No custody yet.</li>
        </ul>
      </div>
      <div class="card space-y-4 p-4">
        <template v-if="auth.can(PERMISSIONS.bonosTypesManage)">
          <div class="space-y-2">
            <h2 class="font-semibold">Opening balance</h2>
            <select v-model="issueForm.bono_type_id" class="input">
              <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
            <input v-model="issueForm.quantity" class="input" placeholder="Quantity">
            <button class="btn-secondary" type="button" @click="opening">Record opening balance</button>
          </div>
        </template>
        <template v-if="auth.can(PERMISSIONS.bonosIssue)">
          <div class="space-y-2">
            <h2 class="font-semibold">{{ isAdmin ? 'Issue to cashier' : 'Issue to waiter / bar' }}</h2>
            <select v-model="issueForm.bono_type_id" class="input">
              <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
            <select v-model="issueForm.receiver_id" class="input" required>
              <option disabled value="">Select person</option>
              <option v-for="person in issueTargets" :key="person.id" :value="String(person.id)">
                {{ person.name }} · {{ person.role?.name || person.role?.slug }}
              </option>
            </select>
            <input v-model="issueForm.quantity" class="input" placeholder="Quantity">
            <button class="btn-primary" type="button" @click="issue">Issue</button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

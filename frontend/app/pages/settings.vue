<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
import type { Branch } from '~/types/auth'

definePageMeta({ permissions: [PERMISSIONS.settingsManage, PERMISSIONS.branchesView, PERMISSIONS.branchesManage] })

const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()

const branches = ref<Branch[]>([])
const selectedId = ref<number | null>(null)
const loading = ref(true)
const saving = ref(false)
const creating = ref(false)
const showCreate = ref(false)
const restaurantName = ref('')

const canManage = computed(() => auth.can(PERMISSIONS.branchesManage) || auth.can(PERMISSIONS.settingsManage))
const canCreate = computed(() => auth.can(PERMISSIONS.branchesManage))
const canSwitch = computed(() => auth.canSwitchBranch)

const form = reactive({
  name: '',
  phone: '',
  address: '',
  currency: 'ETB',
  tax_rate: '15',
  timezone: 'Africa/Addis_Ababa',
  tax_identification_number: '',
  active: true,
})

const createForm = reactive({
  name: '',
  phone: '',
  address: '',
  clone_menu: true,
})

const selected = computed(() => branches.value.find((branch) => branch.id === selectedId.value) ?? null)

function fillForm(branch: Branch) {
  Object.assign(form, {
    name: branch.name,
    phone: branch.phone ?? '',
    address: branch.address ?? '',
    currency: branch.currency,
    tax_rate: branch.tax_rate,
    timezone: branch.timezone,
    tax_identification_number: branch.tax_identification_number ?? '',
    active: branch.active,
  })
  restaurantName.value = branch.restaurant?.name ?? auth.branch?.restaurant?.name ?? ''
}

async function load() {
  loading.value = true
  try {
    branches.value = (await api.branches()).data
    const current = auth.branch?.id
    const pick = branches.value.find((branch) => branch.id === current) ?? branches.value[0]
    if (pick) {
      selectedId.value = pick.id
      fillForm(pick)
    }
  }
  catch (error: any) {
    toast.error(error?.message ?? 'Could not load branches')
  }
  finally {
    loading.value = false
  }
}

function selectBranch(branch: Branch) {
  selectedId.value = branch.id
  fillForm(branch)
}

async function save() {
  if (!selectedId.value) return
  saving.value = true
  try {
    const payload: Record<string, unknown> = { ...form }
    if (canCreate.value && restaurantName.value) {
      payload.restaurant_name = restaurantName.value
    }
    const updated = (await api.updateBranch(selectedId.value, payload)).data
    toast.success('Branch saved')
    await auth.fetchUser()
    await load()
    selectedId.value = updated.id
    const next = branches.value.find((branch) => branch.id === updated.id)
    if (next) fillForm(next)
  }
  catch (error: any) {
    toast.error(error?.message ?? 'Could not save branch')
  }
  finally {
    saving.value = false
  }
}

async function createBranch() {
  creating.value = true
  try {
    const source = auth.branch?.id
    const created = (await api.createBranch({
      name: createForm.name,
      phone: createForm.phone || null,
      address: createForm.address || null,
      clone_from_branch_id: createForm.clone_menu && source ? source : null,
    })).data
    showCreate.value = false
    createForm.name = ''
    createForm.phone = ''
    createForm.address = ''
    toast.success(`${created.name} opened`)
    await auth.fetchUser()
    await load()
    selectedId.value = created.id
    const next = branches.value.find((branch) => branch.id === created.id)
    if (next) fillForm(next)
  }
  catch (error: any) {
    toast.error(error?.message ?? 'Could not create branch')
  }
  finally {
    creating.value = false
  }
}

async function operateHere() {
  if (!selectedId.value || selectedId.value === auth.branch?.id) return
  try {
    await auth.switchBranch(selectedId.value)
    toast.success(`Now operating ${auth.branch?.name ?? 'this branch'}`)
    if (import.meta.client) window.location.reload()
  }
  catch (error: any) {
    toast.error(error?.message ?? 'Could not switch branch')
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="Branches"
      description="Each location has its own tables, menu, orders, kitchen, and stock. The owner works one branch at a time."
    >
      <template #actions>
        <button v-if="canCreate" class="btn-primary" type="button" @click="showCreate = true">
          New branch
        </button>
      </template>
    </PageHeader>

    <div v-if="loading" class="card p-8 text-sm text-slate-500">Loading branches…</div>

    <div v-else class="grid gap-6 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]">
      <div class="card divide-y divide-slate-100">
        <button
          v-for="branch in branches"
          :key="branch.id"
          type="button"
          class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50"
          :class="branch.id === selectedId ? 'bg-brand-50' : ''"
          @click="selectBranch(branch)"
        >
          <div class="min-w-0 flex-1">
            <p class="truncate font-medium text-slate-900">{{ branch.name }}</p>
            <p class="truncate text-xs text-slate-500">{{ branch.address || 'No address' }}</p>
          </div>
          <div class="flex flex-col items-end gap-1">
            <StatusBadge v-if="branch.id === auth.branch?.id" status="active" label="Current" />
            <span v-if="!branch.active" class="text-xs text-slate-400">Inactive</span>
          </div>
        </button>
      </div>

      <form class="card max-w-xl space-y-3 p-6" @submit.prevent="save">
        <p v-if="selected?.id === auth.branch?.id" class="text-sm text-slate-500">
          This is the branch you are operating now.
        </p>

        <FormField v-if="canCreate" label="Restaurant name">
          <input v-model="restaurantName" class="input" :disabled="!canManage">
        </FormField>
        <FormField label="Branch name" required>
          <input v-model="form.name" class="input" required :disabled="!canManage">
        </FormField>
        <FormField label="Phone"><input v-model="form.phone" class="input" :disabled="!canManage"></FormField>
        <FormField label="Address"><input v-model="form.address" class="input" :disabled="!canManage"></FormField>
        <FormField label="TIN"><input v-model="form.tax_identification_number" class="input" :disabled="!canManage"></FormField>
        <FormField label="Currency"><input v-model="form.currency" class="input" :disabled="!canManage"></FormField>
        <FormField label="Tax rate %"><input v-model="form.tax_rate" class="input" type="number" step="0.01" :disabled="!canManage"></FormField>
        <FormField label="Timezone"><input v-model="form.timezone" class="input" :disabled="!canManage"></FormField>
        <label v-if="canCreate" class="flex items-center gap-2 text-sm text-slate-700">
          <input v-model="form.active" type="checkbox" class="size-4 rounded border-slate-300">
          Active
        </label>

        <div class="flex flex-wrap gap-2 pt-2">
          <button v-if="canManage" class="btn-primary" type="submit" :disabled="saving">
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
          <button
            v-if="canSwitch && selected && selected.id !== auth.branch?.id && selected.active"
            class="btn-secondary"
            type="button"
            @click="operateHere"
          >
            Operate this branch
          </button>
        </div>
      </form>
    </div>

    <AppModal v-model="showCreate" title="Open a new branch" description="Tables and stock start empty. You can copy the current menu.">
      <form class="space-y-3" @submit.prevent="createBranch">
        <FormField label="Branch name" required>
          <input v-model="createForm.name" class="input" required placeholder="Piassa">
        </FormField>
        <FormField label="Phone"><input v-model="createForm.phone" class="input"></FormField>
        <FormField label="Address"><input v-model="createForm.address" class="input"></FormField>
        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input v-model="createForm.clone_menu" type="checkbox" class="size-4 rounded border-slate-300">
          Copy menu from {{ auth.branch?.name ?? 'the current branch' }}
        </label>
        <button class="btn-primary" type="submit" :disabled="creating">
          {{ creating ? 'Creating…' : 'Create branch' }}
        </button>
      </form>
    </AppModal>
  </div>
</template>

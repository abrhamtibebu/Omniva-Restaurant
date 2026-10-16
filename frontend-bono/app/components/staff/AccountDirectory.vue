<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

const props = defineProps<{
  directory: 'staff' | 'bono'
}>()

const { t } = useI18n()

const allowed = props.directory === 'staff'
  ? ['admin', 'manager']
  : ['cashier', 'waiter', 'kitchen', 'barman', 'storekeeper']

const title = computed(() => props.directory === 'staff' ? t('team.staffTitle') : t('team.title'))
const description = computed(() => props.directory === 'staff'
  ? t('team.staffDescription')
  : t('team.description'))

const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()

const users = ref<any[]>([])
const shifts = ref<any[]>([])
const roles = ref<any[]>([])
const show = ref(false)
const editingId = ref<number | null>(null)
const search = ref('')
const roleFilter = ref('')
const { busy: searching, run: runSearch } = useBusyAction()
const { busy: saving, run: runSave } = useBusyAction()
const clockActions = useBusyMap()
const rowActions = useBusyMap()
const form = reactive({
  name: '',
  email: '',
  password: 'Password123!',
  role_id: 0,
  phone: '',
  branch_id: 0,
  active: true,
})

const visibleRoles = computed(() => roles.value.filter((role) => allowed.includes(role.slug)))

const columns = computed(() => [
  { key: 'name', label: t('common.name') },
  { key: 'email', label: t('common.email') },
  { key: 'phone', label: t('common.phone') },
  { key: 'role', label: t('common.role') },
  { key: 'branch', label: t('common.branch') },
  { key: 'active', label: t('common.status') },
  { key: 'actions', label: '' },
])

function resetForm() {
  editingId.value = null
  Object.assign(form, {
    name: '',
    email: '',
    password: 'Password123!',
    role_id: visibleRoles.value[0]?.id ?? 0,
    phone: '',
    branch_id: auth.branch?.id ?? 0,
    active: true,
  })
}

async function load() {
  if (props.directory === 'staff') {
    const shiftResponse = await api.todayShifts()
    shifts.value = (shiftResponse as any).data
  }

  if (!auth.can(PERMISSIONS.usersView)) return

  const [u, r] = await Promise.all([
    api.users({
      search: search.value || undefined,
      role_id: roleFilter.value || undefined,
      directory: props.directory,
      per_page: 100,
    }),
    api.roles(),
  ])
  users.value = (u as any).data
  roles.value = (r as any).data
  if (!form.role_id && visibleRoles.value[0]) form.role_id = visibleRoles.value[0].id
  if (!form.branch_id) form.branch_id = auth.branch?.id ?? 0
}

async function searchUsers() {
  await runSearch(async () => {
    await load()
  })
}

function openCreate() {
  resetForm()
  show.value = true
}

function openEdit(row: any) {
  editingId.value = row.id
  Object.assign(form, {
    name: row.name,
    email: row.email,
    password: '',
    role_id: row.role?.id ?? 0,
    phone: row.phone ?? '',
    branch_id: row.branch?.id ?? auth.branch?.id ?? 0,
    active: row.active,
  })
  show.value = true
}

async function save() {
  await runSave(async () => {
    const payload: Record<string, unknown> = {
      directory: props.directory,
      name: form.name,
      email: form.email,
      phone: form.phone.trim(),
      role_id: Number(form.role_id),
      branch_id: Number(form.branch_id || auth.branch?.id),
      active: form.active,
    }
    if (form.password) payload.password = form.password

    try {
      if (editingId.value) await api.updateUser(editingId.value, payload)
      else await api.createUser(payload)
      show.value = false
      toast.success(editingId.value ? t('team.accountUpdated') : t('team.accountCreated'))
      await load()
    }
    catch (error: any) {
      toast.error(error?.message ?? t('team.couldNotSave'))
    }
  })
}

async function deactivate(row: any) {
  if (!window.confirm(t('team.deactivateConfirm', { name: row.name }))) return
  await rowActions.run(row.id, async () => {
    try {
      await api.deleteUser(row.id)
      toast.success(t('team.accountDeactivated'))
      await load()
    }
    catch (error: any) {
      toast.error(error?.message ?? t('team.couldNotDeactivate'))
    }
  })
}

async function clock(kind: 'in' | 'out') {
  await clockActions.run(kind, async () => {
    try {
      if (kind === 'in') await api.clockIn()
      else await api.clockOut()
      toast.success(kind === 'in' ? t('team.clockedIn') : t('team.clockedOut'))
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
    <PageHeader :title="title" :description="description">
      <template #actions>
        <AppButton v-if="directory === 'staff' && auth.can(PERMISSIONS.shiftsClock)" variant="secondary" :loading="clockActions.isBusy('in')" @click="clock('in')">{{ t('team.clockIn') }}</AppButton>
        <AppButton v-if="directory === 'staff' && auth.can(PERMISSIONS.shiftsClock)" variant="secondary" :loading="clockActions.isBusy('out')" @click="clock('out')">{{ t('team.clockOut') }}</AppButton>
        <button v-if="auth.can(PERMISSIONS.usersManage)" class="btn-primary" type="button" @click="openCreate">{{ t('team.addAccount') }}</button>
      </template>
    </PageHeader>

    <div v-if="auth.can(PERMISSIONS.usersView)" class="mb-4 flex flex-wrap gap-2">
      <input v-model="search" class="input max-w-xs" :placeholder="t('team.searchPlaceholder')" @keyup.enter="searchUsers">
      <select v-model="roleFilter" class="input max-w-xs" @change="searchUsers">
        <option value="">{{ t('team.allRoles') }}</option>
        <option v-for="role in visibleRoles" :key="role.id" :value="role.id">{{ role.name }}</option>
      </select>
      <AppButton variant="secondary" :loading="searching" @click="searchUsers">{{ t('common.search') }}</AppButton>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <div v-if="directory === 'staff'" class="card p-4">
        <h2 class="font-semibold">{{ t('team.todaysShifts') }}</h2>
        <ul class="mt-3 space-y-2 text-sm">
          <li v-for="shift in shifts" :key="shift.id">
            {{ shift.user?.name }} · {{ formatTime(shift.clock_in) }}
            → {{ shift.clock_out ? formatTime(shift.clock_out) : t('common.onDuty') }}
          </li>
          <li v-if="!shifts.length" class="text-slate-500">{{ t('team.noShifts') }}</li>
        </ul>
      </div>
      <div v-if="auth.can(PERMISSIONS.usersView)" class="card" :class="directory === 'bono' ? 'lg:col-span-2' : ''">
        <AppTable :rows="users" :columns="columns">
          <template #cell:phone="{ row }">{{ row.phone || t('common.dash') }}</template>
          <template #cell:role="{ row }">{{ row.role?.name ?? t('common.dash') }}</template>
          <template #cell:branch="{ row }">{{ row.branch?.name ?? t('common.dash') }}</template>
          <template #cell:active="{ row }"><StatusBadge :status="row.active ? 'active' : 'inactive'" /></template>
          <template #cell:actions="{ row }">
            <div v-if="auth.can(PERMISSIONS.usersManage)" class="flex gap-2">
              <button class="text-sm text-brand-700" type="button" @click="openEdit(row)">{{ t('common.edit') }}</button>
              <AppButton
                v-if="row.active && row.id !== auth.user?.id"
                variant="ghost"
                size="sm"
                class="text-sm text-red-700"
                :loading="rowActions.isBusy(row.id)"
                @click="deactivate(row)"
              >
                {{ t('team.deactivate') }}
              </AppButton>
            </div>
          </template>
        </AppTable>
      </div>
    </div>

    <AppModal v-model="show" :title="editingId ? t('team.editAccount') : t('team.newAccount')">
      <form class="space-y-3" @submit.prevent="save">
        <input v-model="form.name" class="input" :placeholder="t('common.name')" required>
        <input v-model="form.email" class="input" type="email" :placeholder="t('common.email')" required>
        <input
          v-model="form.phone"
          class="input"
          type="tel"
          inputmode="tel"
          autocomplete="tel"
          :placeholder="t('team.phoneForLogin')"
          required
        >
        <p class="text-xs text-[color:var(--tg-muted,#64748b)]">{{ t('team.phoneHelp') }}</p>
        <input v-model="form.password" class="input" type="password" :placeholder="editingId ? t('team.newPasswordOptional') : t('team.tempPassword')" :required="!editingId">
        <select v-model="form.role_id" class="input" required>
          <option v-for="role in visibleRoles" :key="role.id" :value="role.id">{{ role.name }}</option>
        </select>
        <select v-if="auth.canSwitchBranch && auth.availableBranches.length" v-model="form.branch_id" class="input">
          <option v-for="branch in auth.availableBranches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
        </select>
        <label v-if="editingId" class="flex items-center gap-2 text-sm">
          <input v-model="form.active" type="checkbox"> {{ t('common.active') }}
        </label>
        <AppButton type="submit" :loading="saving">{{ t('common.save') }}</AppButton>
      </form>
    </AppModal>
  </div>
</template>

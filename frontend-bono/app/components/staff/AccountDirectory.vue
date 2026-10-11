<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'

const props = defineProps<{
  directory: 'staff' | 'bono'
}>()

const allowed = props.directory === 'staff'
  ? ['admin', 'manager']
  : ['cashier', 'waiter', 'kitchen', 'barman', 'storekeeper']

const title = props.directory === 'staff' ? 'Staff' : 'Bono team'
const description = props.directory === 'staff'
  ? 'Administrator and manager accounts.'
  : 'Cashier, waiter, kitchen, bar, and store accounts for this restaurant.'

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
  const payload: Record<string, unknown> = {
    directory: props.directory,
    name: form.name,
    email: form.email,
    phone: form.phone || null,
    role_id: Number(form.role_id),
    branch_id: Number(form.branch_id || auth.branch?.id),
    active: form.active,
  }
  if (form.password) payload.password = form.password

  try {
    if (editingId.value) await api.updateUser(editingId.value, payload)
    else await api.createUser(payload)
    show.value = false
    toast.success(editingId.value ? 'Account updated' : 'Account created')
    await load()
  }
  catch (error: any) {
    toast.error(error?.message ?? 'Could not save the account')
  }
}

async function deactivate(row: any) {
  if (!window.confirm(`Deactivate ${row.name}? They will be signed out.`)) return
  try {
    await api.deleteUser(row.id)
    toast.success('Account deactivated')
    await load()
  }
  catch (error: any) {
    toast.error(error?.message ?? 'Could not deactivate that account')
  }
}

async function clock(kind: 'in' | 'out') {
  try {
    if (kind === 'in') await api.clockIn()
    else await api.clockOut()
    toast.success(kind === 'in' ? 'Clocked in' : 'Clocked out')
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
    <PageHeader :title="title" :description="description">
      <template #actions>
        <button v-if="directory === 'staff' && auth.can(PERMISSIONS.shiftsClock)" class="btn-secondary" type="button" @click="clock('in')">Clock in</button>
        <button v-if="directory === 'staff' && auth.can(PERMISSIONS.shiftsClock)" class="btn-secondary" type="button" @click="clock('out')">Clock out</button>
        <button v-if="auth.can(PERMISSIONS.usersManage)" class="btn-primary" type="button" @click="openCreate">Add account</button>
      </template>
    </PageHeader>

    <div v-if="auth.can(PERMISSIONS.usersView)" class="mb-4 flex flex-wrap gap-2">
      <input v-model="search" class="input max-w-xs" placeholder="Search name or email" @keyup.enter="load">
      <select v-model="roleFilter" class="input max-w-xs" @change="load">
        <option value="">All roles</option>
        <option v-for="role in visibleRoles" :key="role.id" :value="role.id">{{ role.name }}</option>
      </select>
      <button class="btn-secondary" type="button" @click="load">Search</button>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <div v-if="directory === 'staff'" class="card p-4">
        <h2 class="font-semibold">Today’s shifts</h2>
        <ul class="mt-3 space-y-2 text-sm">
          <li v-for="shift in shifts" :key="shift.id">
            {{ shift.user?.name }} · {{ formatTime(shift.clock_in) }}
            → {{ shift.clock_out ? formatTime(shift.clock_out) : 'on duty' }}
          </li>
          <li v-if="!shifts.length" class="text-slate-500">No one has clocked in yet.</li>
        </ul>
      </div>
      <div v-if="auth.can(PERMISSIONS.usersView)" class="card" :class="directory === 'bono' ? 'lg:col-span-2' : ''">
        <AppTable :rows="users" :columns="[
          { key: 'name', label: 'Name' },
          { key: 'email', label: 'Email' },
          { key: 'phone', label: 'Phone' },
          { key: 'role', label: 'Role' },
          { key: 'branch', label: 'Branch' },
          { key: 'active', label: 'Status' },
          { key: 'actions', label: '' },
        ]">
          <template #cell:phone="{ row }">{{ row.phone || '—' }}</template>
          <template #cell:role="{ row }">{{ row.role?.name ?? '—' }}</template>
          <template #cell:branch="{ row }">{{ row.branch?.name ?? '—' }}</template>
          <template #cell:active="{ row }"><StatusBadge :status="row.active ? 'active' : 'inactive'" /></template>
          <template #cell:actions="{ row }">
            <div v-if="auth.can(PERMISSIONS.usersManage)" class="flex gap-2">
              <button class="text-sm text-brand-700" type="button" @click="openEdit(row)">Edit</button>
              <button v-if="row.active && row.id !== auth.user?.id" class="text-sm text-red-700" type="button" @click="deactivate(row)">Deactivate</button>
            </div>
          </template>
        </AppTable>
      </div>
    </div>

    <AppModal v-model="show" :title="editingId ? 'Edit account' : 'New account'">
      <form class="space-y-3" @submit.prevent="save">
        <input v-model="form.name" class="input" placeholder="Name" required>
        <input v-model="form.email" class="input" type="email" placeholder="Email" required>
        <input v-model="form.phone" class="input" placeholder="Phone">
        <input v-model="form.password" class="input" type="password" :placeholder="editingId ? 'New password (optional)' : 'Temporary password'" :required="!editingId">
        <select v-model="form.role_id" class="input" required>
          <option v-for="role in visibleRoles" :key="role.id" :value="role.id">{{ role.name }}</option>
        </select>
        <select v-if="auth.canSwitchBranch && auth.availableBranches.length" v-model="form.branch_id" class="input">
          <option v-for="branch in auth.availableBranches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
        </select>
        <label v-if="editingId" class="flex items-center gap-2 text-sm">
          <input v-model="form.active" type="checkbox"> Active
        </label>
        <button class="btn-primary" type="submit">Save</button>
      </form>
    </AppModal>
  </div>
</template>

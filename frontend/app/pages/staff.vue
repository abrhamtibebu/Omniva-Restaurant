<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
definePageMeta({ permissions: [PERMISSIONS.usersView, PERMISSIONS.shiftsView] })
const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const users = ref<any[]>([])
const shifts = ref<any[]>([])
const show = ref(false)
const roles = ref<any[]>([])
const form = reactive({ name: '', email: '', password: 'Password123!', role_id: 0, phone: '', branch_id: 0 })

async function load() {
  const [u, s, r] = await Promise.all([api.users(), api.todayShifts(), api.roles()])
  users.value = (u as any).data
  shifts.value = (s as any).data
  roles.value = (r as any).data
  if (!form.role_id && roles.value[0]) form.role_id = roles.value[0].id
  if (!form.branch_id) form.branch_id = auth.branch?.id ?? 0
}
async function create() {
  await api.createUser({ ...form, branch_id: form.branch_id || auth.branch?.id })
  show.value = false
  toast.success('Staff created')
  await load()
}
async function clock(kind: 'in' | 'out') {
  try {
    if (kind === 'in') await api.clockIn()
    else await api.clockOut()
    toast.success(kind === 'in' ? 'Clocked in' : 'Clocked out')
    await load()
  }
  catch (e: any) { toast.error(e.message) }
}
onMounted(load)
</script>
<template>
  <div>
    <PageHeader title="Staff">
      <template #actions>
        <button class="btn-secondary" type="button" @click="clock('in')">Clock in</button>
        <button class="btn-secondary" type="button" @click="clock('out')">Clock out</button>
        <button v-if="auth.can(PERMISSIONS.usersManage)" class="btn-primary" type="button" @click="show = true">Add staff</button>
      </template>
    </PageHeader>
    <div class="grid gap-4 lg:grid-cols-2">
      <div class="card p-4">
        <h2 class="font-semibold">Today’s shifts</h2>
        <ul class="mt-3 space-y-2 text-sm">
          <li v-for="shift in shifts" :key="shift.id">
            {{ shift.user?.name }} · {{ formatTime(shift.clock_in) }}
            → {{ shift.clock_out ? formatTime(shift.clock_out) : 'on duty' }}
          </li>
        </ul>
      </div>
      <div class="card">
        <AppTable :rows="users" :columns="[
          { key: 'name', label: 'Name' },
          { key: 'email', label: 'Email' },
          { key: 'branch', label: 'Branch' },
          { key: 'active', label: 'Status' },
        ]">
          <template #cell:branch="{ row }">{{ row.branch?.name ?? '—' }}</template>
          <template #cell:active="{ row }"><StatusBadge :status="row.active ? 'active' : 'inactive'" /></template>
        </AppTable>
      </div>
    </div>
    <AppModal v-model="show" title="New staff">
      <form class="space-y-3" @submit.prevent="create">
        <input v-model="form.name" class="input" placeholder="Name" required>
        <input v-model="form.email" class="input" type="email" required>
        <input v-model="form.phone" class="input" placeholder="Phone">
        <input v-model="form.password" class="input" type="password" required>
        <select v-model="form.role_id" class="input">
          <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
        </select>
        <select v-if="auth.canSwitchBranch && auth.availableBranches.length" v-model="form.branch_id" class="input">
          <option v-for="branch in auth.availableBranches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
        </select>
        <button class="btn-primary" type="submit">Save</button>
      </form>
    </AppModal>
  </div>
</template>

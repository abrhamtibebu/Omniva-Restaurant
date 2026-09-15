<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
definePageMeta({ permissions: [PERMISSIONS.reservationsView] })
const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const rows = ref<any[]>([])
const show = ref(false)
const form = reactive({
  customer_name: '',
  phone: '',
  reservation_date: formatDateForInput(),
  reservation_time: '19:00',
  guest_count: 2,
})
async function load() { rows.value = (await api.reservations()).data }
async function create() {
  await api.createReservation(form)
  show.value = false
  toast.success('Reservation saved')
  await load()
}
onMounted(load)
</script>
<template>
  <div>
    <PageHeader title="Reservations">
      <template #actions>
        <button v-if="auth.can(PERMISSIONS.reservationsManage)" class="btn-primary" type="button" @click="show = true">Add</button>
      </template>
    </PageHeader>
    <div class="card">
      <AppTable :rows="rows" :columns="[
        { key: 'customer_name', label: 'Name' },
        { key: 'phone', label: 'Phone' },
        { key: 'reservation_date', label: 'Date' },
        { key: 'reservation_time', label: 'Time' },
        { key: 'guest_count', label: 'Guests' },
        { key: 'status', label: 'Status' },
      ]">
        <template #cell:status="{ row }"><StatusBadge :status="row.status" /></template>
      </AppTable>
    </div>
    <AppModal v-model="show" title="New reservation">
      <form class="space-y-3" @submit.prevent="create">
        <input v-model="form.customer_name" class="input" placeholder="Guest name" required>
        <input v-model="form.phone" class="input" placeholder="Phone" required>
        <input v-model="form.reservation_date" class="input" type="date" required>
        <input v-model="form.reservation_time" class="input" type="time" required>
        <input v-model.number="form.guest_count" class="input" type="number" min="1">
        <button class="btn-primary" type="submit">Save</button>
      </form>
    </AppModal>
  </div>
</template>

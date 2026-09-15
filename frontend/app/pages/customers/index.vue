<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
definePageMeta({ permissions: [PERMISSIONS.customersView] })
const api = useRestaurantApi()
const auth = useAuthStore()
const toast = useToast()
const rows = ref<any[]>([])
const show = ref(false)
const form = reactive({ name: '', phone: '', email: '' })
async function load() { rows.value = (await api.customers()).data }
async function create() {
  await api.createCustomer(form)
  show.value = false
  toast.success('Customer saved')
  await load()
}
onMounted(load)
</script>
<template>
  <div>
    <PageHeader title="Customers">
      <template #actions>
        <button v-if="auth.can(PERMISSIONS.customersManage)" class="btn-primary" type="button" @click="show = true">Add</button>
      </template>
    </PageHeader>
    <div class="card">
      <AppTable
        :rows="rows"
        clickable
        :columns="[
          { key: 'name', label: 'Name' },
          { key: 'phone', label: 'Phone' },
          { key: 'email', label: 'Email', hideBelow: 'md' },
          { key: 'orders_count', label: 'Orders' },
        ]"
        @row-click="(row: any) => navigateTo(`/customers/${row.id}`)"
      />
    </div>
    <AppModal v-model="show" title="New customer">
      <form class="space-y-3" @submit.prevent="create">
        <input v-model="form.name" class="input" placeholder="Name" required>
        <input v-model="form.phone" class="input" placeholder="Phone">
        <input v-model="form.email" class="input" placeholder="Email">
        <button class="btn-primary" type="submit">Save</button>
      </form>
    </AppModal>
  </div>
</template>

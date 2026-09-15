<script setup lang="ts">
import { PERMISSIONS } from '~/utils/permissions'
definePageMeta({ permissions: [PERMISSIONS.suppliersView] })
const api = useRestaurantApi()
const toast = useToast()
const auth = useAuthStore()
const rows = ref<any[]>([])
const show = ref(false)
const form = reactive({ name: '', phone: '', email: '', contact_person: '' })
async function load() {
  rows.value = (await api.suppliers()).data
}
async function create() {
  await api.createSupplier(form)
  show.value = false
  toast.success('Supplier added')
  await load()
}
onMounted(load)
</script>
<template>
  <div>
    <PageHeader title="Suppliers">
      <template #actions>
        <button v-if="auth.can(PERMISSIONS.suppliersManage)" class="btn-primary" type="button" @click="show = true">Add</button>
      </template>
    </PageHeader>
    <div class="card">
      <AppTable :rows="rows" :columns="[
        { key: 'name', label: 'Name' },
        { key: 'contact_person', label: 'Contact' },
        { key: 'phone', label: 'Phone' },
        { key: 'email', label: 'Email', hideBelow: 'md' },
      ]" />
    </div>
    <AppModal v-model="show" title="New supplier">
      <form class="space-y-3" @submit.prevent="create">
        <input v-model="form.name" class="input" placeholder="Name" required>
        <input v-model="form.contact_person" class="input" placeholder="Contact">
        <input v-model="form.phone" class="input" placeholder="Phone">
        <input v-model="form.email" class="input" placeholder="Email" type="email">
        <button class="btn-primary" type="submit">Save</button>
      </form>
    </AppModal>
  </div>
</template>

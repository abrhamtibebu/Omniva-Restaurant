<script setup lang="ts">
const auth = useAuthStore()
const toast = useToast()
const api = useApi()
const form = reactive({ current_password: '', password: '', password_confirmation: '' })
async function save() {
  try {
    await api.patch('/me/password', form)
    toast.success('Password updated')
    form.current_password = form.password = form.password_confirmation = ''
  }
  catch (e: any) {
    toast.error(e.message)
  }
}
</script>
<template>
  <div>
    <PageHeader title="My account" :description="auth.user?.email" />
    <form class="card max-w-md space-y-3 p-6" @submit.prevent="save">
      <FormField label="Current password"><input v-model="form.current_password" class="input" type="password" required></FormField>
      <FormField label="New password"><input v-model="form.password" class="input" type="password" required></FormField>
      <FormField label="Confirm"><input v-model="form.password_confirmation" class="input" type="password" required></FormField>
      <button class="btn-primary" type="submit">Update password</button>
    </form>
  </div>
</template>

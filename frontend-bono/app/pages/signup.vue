<script setup lang="ts">
import { ApiError } from '~/types/api'

definePageMeta({
  layout: 'auth',
  public: true,
})

const auth = useAuthStore()
const toast = useToast()

const form = reactive({
  restaurant_name: '',
  owner_name: '',
  email: '',
  password: '',
  phone: '',
})
const errors = ref<Record<string, string[]>>({})
const generalError = ref('')
const submitting = ref(false)

async function submit() {
  submitting.value = true
  errors.value = {}
  generalError.value = ''

  try {
    const user = await auth.register({
      restaurant_name: form.restaurant_name,
      owner_name: form.owner_name,
      email: form.email,
      password: form.password,
      phone: form.phone || undefined,
    })
    toast.success(`Welcome, ${user.name.split(' ')[0]}`)
    await navigateTo(auth.homeRoute)
  }
  catch (error) {
    if (error instanceof ApiError) {
      errors.value = error.errors
      if (!error.isValidation) generalError.value = error.message
    }
    else {
      generalError.value = 'Unexpected error. Please try again.'
    }
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="w-full max-w-md">
    <div class="mb-8 text-center">
      <div class="mx-auto flex size-14 items-center justify-center rounded-xl bg-brand-600 text-lg font-bold text-white">
        ቤ
      </div>
      <h1 class="mt-4 text-2xl font-bold text-white">Start a restaurant</h1>
      <p class="mt-1 text-sm text-slate-400">Bono control includes a 14-day trial.</p>
    </div>

    <div class="card p-6 sm:p-8">
      <form class="space-y-5" novalidate @submit.prevent="submit">
        <div
          v-if="generalError"
          class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200 ring-inset"
          role="alert"
        >
          {{ generalError }}
        </div>

        <FormField label="Restaurant" for="restaurant_name" required :error="errors.restaurant_name?.[0]">
          <input id="restaurant_name" v-model="form.restaurant_name" class="input" required placeholder="Restaurant name">
        </FormField>
        <FormField label="Your name" for="owner_name" required :error="errors.owner_name?.[0]">
          <input id="owner_name" v-model="form.owner_name" class="input" required placeholder="Owner name">
        </FormField>
        <FormField label="Email" for="email" required :error="errors.email?.[0]">
          <input id="email" v-model="form.email" type="email" class="input" autocomplete="email" required placeholder="you@restaurant.com">
        </FormField>
        <FormField label="Phone" for="phone" :error="errors.phone?.[0]">
          <input id="phone" v-model="form.phone" class="input" placeholder="Optional">
        </FormField>
        <FormField label="Password" for="password" required :error="errors.password?.[0]">
          <input id="password" v-model="form.password" type="password" class="input" autocomplete="new-password" required placeholder="At least 8 characters">
        </FormField>

        <button type="submit" class="btn-primary btn-touch w-full" :disabled="submitting">
          <AppSpinner v-if="submitting" class="size-5" />
          {{ submitting ? 'Creating...' : 'Create restaurant' }}
        </button>
      </form>
    </div>

    <p class="mt-6 text-center text-sm text-slate-400">
      Already registered?
      <NuxtLink to="/login" class="text-white underline">Sign in</NuxtLink>
    </p>
  </div>
</template>

<script setup lang="ts">
import { ApiError } from '~/types/api'

definePageMeta({
  layout: 'auth',
  public: true,
})

const auth = useAuthStore()
const route = useRoute()
const toast = useToast()

const form = reactive({ email: '', password: '' })
const errors = ref<Record<string, string[]>>({})
const generalError = ref('')
const submitting = ref(false)

async function submit() {
  submitting.value = true
  errors.value = {}
  generalError.value = ''

  try {
    const user = await auth.login({ email: form.email, password: form.password })
    toast.success(`Welcome back, ${user.name.split(' ')[0]}`)

    const redirect = route.query.redirect
    await navigateTo(typeof redirect === 'string' && redirect !== '/login' ? redirect : auth.homeRoute)
  }
  catch (error) {
    if (error instanceof ApiError) {
      errors.value = error.errors
      // 422 messages are shown inline under the fields instead.
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
      <h1 class="mt-4 text-2xl font-bold text-white">Betedesta</h1>
      <p class="mt-1 text-sm text-slate-400">Bar &amp; Restaurant · Sign in</p>
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

        <FormField label="Email" for="email" required :error="errors.email?.[0]">
          <input
            id="email"
            v-model="form.email"
            type="email"
            class="input"
            autocomplete="email"
            autofocus
            required
            placeholder="you@restaurant.com"
          >
        </FormField>

        <FormField label="Password" for="password" required :error="errors.password?.[0]">
          <input
            id="password"
            v-model="form.password"
            type="password"
            class="input"
            autocomplete="current-password"
            required
            placeholder="••••••••"
          >
        </FormField>

        <button type="submit" class="btn-primary btn-touch w-full" :disabled="submitting">
          <AppSpinner v-if="submitting" class="size-5" />
          {{ submitting ? 'Signing in...' : 'Sign in' }}
        </button>
      </form>
    </div>

    <p class="mt-6 text-center text-xs text-slate-500">
      Betedesta Bar &amp; Restaurant
    </p>
  </div>
</template>

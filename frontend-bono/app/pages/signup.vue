<script setup lang="ts">
import { ApiError } from '~/types/api'

definePageMeta({
  layout: 'auth',
  public: true,
})

const { t } = useI18n()
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
    toast.success(t('auth.welcome', { name: user.name.split(' ')[0] }))
    await navigateTo(auth.homeRoute)
  }
  catch (error) {
    if (error instanceof ApiError) {
      errors.value = error.errors
      if (!error.isValidation) generalError.value = error.message
    }
    else {
      generalError.value = t('common.unexpectedError')
    }
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="w-full max-w-md">
    <div class="mb-4 flex justify-end">
      <LanguageSelector />
    </div>

    <div class="mb-8 text-center">
      <div class="mx-auto flex size-14 items-center justify-center rounded-xl bg-brand-600 text-lg font-bold text-white">
        ቤ
      </div>
      <h1 class="mt-4 text-2xl font-bold text-white">{{ t('auth.startRestaurant') }}</h1>
      <p class="mt-1 text-sm text-slate-400">{{ t('auth.signupTagline') }}</p>
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

        <FormField :label="t('auth.restaurantName')" for="restaurant_name" required :error="errors.restaurant_name?.[0]">
          <input id="restaurant_name" v-model="form.restaurant_name" class="input" required :placeholder="t('auth.restaurantPlaceholder')">
        </FormField>
        <FormField :label="t('auth.yourName')" for="owner_name" required :error="errors.owner_name?.[0]">
          <input id="owner_name" v-model="form.owner_name" class="input" required :placeholder="t('auth.ownerPlaceholder')">
        </FormField>
        <FormField :label="t('common.email')" for="email" required :error="errors.email?.[0]">
          <input id="email" v-model="form.email" type="email" class="input" autocomplete="email" required :placeholder="t('auth.loginPlaceholder')">
        </FormField>
        <FormField :label="t('common.phone')" for="phone" :error="errors.phone?.[0]">
          <input id="phone" v-model="form.phone" class="input" :placeholder="t('auth.phoneOptional')">
        </FormField>
        <FormField :label="t('common.password')" for="password" required :error="errors.password?.[0]">
          <input id="password" v-model="form.password" type="password" class="input" autocomplete="new-password" required :placeholder="t('auth.passwordMin')">
        </FormField>

        <AppButton type="submit" size="touch" block :loading="submitting">
          {{ submitting ? t('auth.creating') : t('auth.createRestaurant') }}
        </AppButton>
      </form>
    </div>

    <p class="mt-6 text-center text-sm text-slate-400">
      {{ t('auth.alreadyRegistered') }}
      <NuxtLink to="/login" class="text-white underline">{{ t('auth.signIn') }}</NuxtLink>
    </p>
  </div>
</template>

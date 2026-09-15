<script setup lang="ts">
/** Bare `/` just forwards each role to the screen it actually works from. */
const auth = useAuthStore()

definePageMeta({ layout: 'fullscreen' })

onMounted(() => navigateTo(auth.homeRoute, { replace: true }))
</script>

<template>
  <div class="flex min-h-screen items-center justify-center">
    <AppSpinner class="size-8 text-brand-600" />
  </div>
</template>

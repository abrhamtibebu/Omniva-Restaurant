<script setup lang="ts">
const props = withDefaults(defineProps<{
  title?: string
  message?: string
  retrying?: boolean
}>(), {
  retrying: false,
})

const emit = defineEmits<{ retry: [] }>()
const { t } = useI18n()

const displayTitle = computed(() => props.title ?? t('error.couldNotLoad'))
const displayMessage = computed(() => props.message ?? t('error.fetchFailed'))
</script>

<template>
  <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
    <div class="rounded-full bg-red-50 p-3">
      <svg class="size-7 text-red-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"
        />
      </svg>
    </div>

    <h3 class="mt-4 text-sm font-semibold text-slate-900">{{ displayTitle }}</h3>
    <p class="mt-1 max-w-sm text-sm text-slate-500">{{ displayMessage }}</p>

    <AppButton variant="secondary" class="mt-5" :loading="retrying" @click="emit('retry')">
      {{ t('error.tryAgain') }}
    </AppButton>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  title?: string
  message?: string
  retrying?: boolean
}>(), {
  title: 'Could not load this',
  message: 'Something went wrong while fetching data.',
  retrying: false,
})

const emit = defineEmits<{ retry: [] }>()
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

    <h3 class="mt-4 text-sm font-semibold text-slate-900">{{ title }}</h3>
    <p class="mt-1 max-w-sm text-sm text-slate-500">{{ message }}</p>

    <button type="button" class="btn-secondary mt-5" :disabled="retrying" @click="emit('retry')">
      <AppSpinner v-if="retrying" class="size-4" />
      Try again
    </button>
  </div>
</template>

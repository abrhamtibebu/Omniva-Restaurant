<script setup lang="ts">
/** Replaces `confirm()` for destructive or irreversible actions. */
withDefaults(defineProps<{
  modelValue: boolean
  title: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  tone?: 'danger' | 'primary'
  loading?: boolean
}>(), {
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  tone: 'danger',
  loading: false,
})

const emit = defineEmits<{
  'update:modelValue': [boolean]
  confirm: []
}>()
</script>

<template>
  <AppModal
    :model-value="modelValue"
    size="sm"
    :persistent="loading"
    :title="title"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <p class="text-sm text-slate-600">{{ message }}</p>

    <template #footer>
      <button
        type="button"
        class="btn-secondary w-full sm:w-auto"
        :disabled="loading"
        @click="emit('update:modelValue', false)"
      >
        {{ cancelLabel }}
      </button>
      <button
        type="button"
        class="w-full sm:w-auto"
        :class="tone === 'danger' ? 'btn-danger' : 'btn-primary'"
        :disabled="loading"
        @click="emit('confirm')"
      >
        <AppSpinner v-if="loading" class="size-4" />
        {{ confirmLabel }}
      </button>
    </template>
  </AppModal>
</template>

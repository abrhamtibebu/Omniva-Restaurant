<script setup lang="ts">
/** Replaces `confirm()` for destructive or irreversible actions. */
const props = withDefaults(defineProps<{
  modelValue: boolean
  title: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  tone?: 'danger' | 'primary'
  loading?: boolean
}>(), {
  tone: 'danger',
  loading: false,
})

const emit = defineEmits<{
  'update:modelValue': [boolean]
  confirm: []
}>()

const { t } = useI18n()
const resolvedConfirm = computed(() => props.confirmLabel ?? t('common.confirm'))
const resolvedCancel = computed(() => props.cancelLabel ?? t('common.cancel'))
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
      <AppButton
        variant="secondary"
        class="w-full sm:w-auto"
        :disabled="loading"
        @click="emit('update:modelValue', false)"
      >
        {{ resolvedCancel }}
      </AppButton>
      <AppButton
        :variant="tone === 'danger' ? 'danger' : 'primary'"
        class="w-full sm:w-auto"
        :loading="loading"
        @click="emit('confirm')"
      >
        {{ resolvedConfirm }}
      </AppButton>
    </template>
  </AppModal>
</template>

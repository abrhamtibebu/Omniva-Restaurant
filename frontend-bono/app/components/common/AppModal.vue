<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue: boolean
  title?: string
  description?: string
  size?: 'sm' | 'md' | 'lg' | 'xl'
  /** Blocks backdrop and Escape dismissal while a request is in flight. */
  persistent?: boolean
}>(), {
  size: 'md',
  persistent: false,
})

const emit = defineEmits<{ 'update:modelValue': [boolean] }>()
const { t } = useI18n()

const SIZES = {
  sm: 'max-w-sm',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
  xl: 'max-w-4xl',
} as const

function close() {
  if (props.persistent) return
  emit('update:modelValue', false)
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && props.modelValue) close()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

watch(() => props.modelValue, (open) => {
  document.body.classList.toggle('overflow-hidden', open)
})

onBeforeUnmount(() => document.body.classList.remove('overflow-hidden'))
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-100 ease-in"
      leave-to-class="opacity-0"
    >
      <div v-if="modelValue" class="app-modal fixed inset-0 z-50 overflow-y-auto">
        <div class="app-modal-backdrop fixed inset-0" @click="close" />

        <div class="flex min-h-full items-end justify-center p-0 sm:items-center sm:p-4">
          <div
            class="app-modal-panel relative w-full rounded-t-2xl shadow-xl sm:rounded-2xl"
            :class="[SIZES[size], 'max-h-[90dvh] pb-[env(safe-area-inset-bottom,0px)]']"
            role="dialog"
            aria-modal="true"
          >
            <div v-if="title || $slots.header" class="app-modal-header border-b px-5 py-4">
              <slot name="header">
                <h2 class="app-modal-title text-lg font-semibold">{{ title }}</h2>
                <p v-if="description" class="app-modal-desc mt-1 text-sm">{{ description }}</p>
              </slot>
            </div>

            <div class="max-h-[65dvh] overflow-y-auto px-5 py-4 sm:max-h-[70vh]">
              <slot />
            </div>

            <div
              v-if="$slots.footer"
              class="app-modal-footer flex flex-col-reverse gap-2 border-t px-5 py-4 sm:flex-row sm:justify-end"
            >
              <slot name="footer" />
            </div>

            <button
              v-if="!persistent"
              type="button"
              class="app-modal-close absolute top-3.5 right-4 rounded-md p-1.5 transition-colors"
              :aria-label="t('common.close')"
              @click="close"
            >
              <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

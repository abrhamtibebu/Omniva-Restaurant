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

// Keep the page behind the modal from scrolling.
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
      <div v-if="modelValue" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50" @click="close" />

        <div class="flex min-h-full items-end justify-center p-0 sm:items-center sm:p-4">
          <div
            class="relative w-full rounded-t-2xl bg-white shadow-xl sm:rounded-2xl"
            :class="SIZES[size]"
            role="dialog"
            aria-modal="true"
          >
            <div v-if="title || $slots.header" class="border-b border-slate-200 px-5 py-4">
              <slot name="header">
                <h2 class="text-lg font-semibold text-slate-900">{{ title }}</h2>
                <p v-if="description" class="mt-1 text-sm text-slate-600">{{ description }}</p>
              </slot>
            </div>

            <div class="max-h-[70vh] overflow-y-auto px-5 py-4">
              <slot />
            </div>

            <div
              v-if="$slots.footer"
              class="flex flex-col-reverse gap-2 border-t border-slate-200 px-5 py-4 sm:flex-row sm:justify-end"
            >
              <slot name="footer" />
            </div>

            <button
              v-if="!persistent"
              type="button"
              class="absolute top-3.5 right-4 rounded-md p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
              aria-label="Close"
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

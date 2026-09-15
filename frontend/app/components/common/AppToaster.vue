<script setup lang="ts">
import type { ToastKind } from '~/composables/useToast'

const { toasts, dismiss } = useToast()

const STYLES: Record<ToastKind, { wrapper: string; icon: string; path: string }> = {
  success: {
    wrapper: 'border-emerald-200 bg-emerald-50',
    icon: 'text-emerald-600',
    path: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
  },
  error: {
    wrapper: 'border-red-200 bg-red-50',
    icon: 'text-red-600',
    path: 'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z',
  },
  warning: {
    wrapper: 'border-amber-200 bg-amber-50',
    icon: 'text-amber-600',
    path: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
  },
  info: {
    wrapper: 'border-sky-200 bg-sky-50',
    icon: 'text-sky-600',
    path: 'm11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z',
  },
}
</script>

<template>
  <div
    class="pointer-events-none fixed inset-x-0 top-0 z-100 flex flex-col items-center gap-2 p-4 sm:items-end"
    role="region"
    aria-label="Notifications"
  >
    <TransitionGroup
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-2 opacity-0 sm:translate-x-2 sm:translate-y-0"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border p-4 shadow-lg"
        :class="STYLES[toast.kind].wrapper"
        role="alert"
      >
        <svg
          class="mt-0.5 size-5 shrink-0"
          :class="STYLES[toast.kind].icon"
          fill="none"
          stroke="currentColor"
          stroke-width="1.7"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path stroke-linecap="round" stroke-linejoin="round" :d="STYLES[toast.kind].path" />
        </svg>

        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-slate-900">{{ toast.title }}</p>
          <p v-if="toast.description" class="mt-0.5 text-sm text-slate-600">
            {{ toast.description }}
          </p>
        </div>

        <button
          type="button"
          class="shrink-0 rounded-md p-1 text-slate-400 transition-colors hover:bg-black/5 hover:text-slate-600"
          aria-label="Dismiss"
          @click="dismiss(toast.id)"
        >
          <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

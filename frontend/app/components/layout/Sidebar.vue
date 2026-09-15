<script setup lang="ts">
import { NAV_SECTIONS } from '~/utils/navigation'

const auth = useAuthStore()
const route = useRoute()

const emit = defineEmits<{ navigate: [] }>()

/** Only sections with at least one permitted item are rendered. */
const sections = computed(() =>
  NAV_SECTIONS
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => auth.canAny(item.permissions)),
    }))
    .filter((section) => section.items.length > 0),
)

function isActive(to: string): boolean {
  return route.path === to || route.path.startsWith(`${to}/`)
}
</script>

<template>
  <div class="flex h-full flex-col bg-slate-900">
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
      <div class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">
        ቤ
      </div>
      <div class="min-w-0">
        <p class="truncate text-sm font-semibold text-white">
          {{ auth.branch?.restaurant?.name ?? 'Betedesta' }}
        </p>
        <p class="truncate text-xs text-slate-400">{{ auth.branch?.name ?? 'Bar & Restaurant' }}</p>
      </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4">
      <div v-for="section in sections" :key="section.label" class="mb-5 last:mb-0">
        <p class="px-2 pb-2 text-xs font-semibold tracking-wider text-slate-500 uppercase">
          {{ section.label }}
        </p>

        <ul class="space-y-0.5">
          <li v-for="item in section.items" :key="item.to">
            <NuxtLink
              :to="item.to"
              class="flex items-center gap-3 rounded-lg px-2.5 py-2.5 text-sm font-medium transition-colors"
              :class="isActive(item.to)
                ? 'bg-brand-600 text-white'
                : 'text-slate-300 hover:bg-white/5 hover:text-white'"
              @click="emit('navigate')"
            >
              <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
              </svg>
              {{ item.label }}
            </NuxtLink>
          </li>
        </ul>
      </div>
    </nav>

    <div class="shrink-0 border-t border-white/10 p-3">
      <div class="flex items-center gap-3 rounded-lg px-2 py-2">
        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-sm font-semibold text-white">
          {{ auth.user?.name?.charAt(0).toUpperCase() }}
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-white">{{ auth.user?.name }}</p>
          <p class="truncate text-xs text-slate-400 capitalize">{{ auth.user?.role.name }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

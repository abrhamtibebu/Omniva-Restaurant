<script setup lang="ts">
const emit = defineEmits<{ toggleSidebar: [] }>()

const auth = useAuthStore()
const toast = useToast()

const menuOpen = ref(false)
const loggingOut = ref(false)
const switching = ref(false)
const menuRoot = ref<HTMLElement | null>(null)

useClickOutside(menuRoot, () => { menuOpen.value = false })

const switchableBranches = computed(() =>
  (auth.availableBranches ?? []).filter((branch) => branch.active),
)

const showBranchSwitcher = computed(() =>
  auth.canSwitchBranch && switchableBranches.value.length > 1,
)

async function onSwitchBranch(event: Event) {
  const id = Number((event.target as HTMLSelectElement).value)
  if (!id || id === auth.branch?.id) return

  switching.value = true
  try {
    await auth.switchBranch(id)
    toast.success(`Now operating ${auth.branch?.name ?? 'this branch'}`)
    if (import.meta.client) {
      window.location.reload()
    }
  }
  catch (error: any) {
    toast.error(error?.message ?? 'Could not switch branch')
  }
  finally {
    switching.value = false
  }
}

async function logout() {
  loggingOut.value = true
  try {
    await auth.logout()
    toast.success('Signed out')
    await navigateTo('/login')
  }
  finally {
    loggingOut.value = false
    menuOpen.value = false
  }
}
</script>

<template>
  <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-4 sm:px-6">
    <button
      type="button"
      class="btn-ghost -ml-2 px-2 py-2 lg:hidden"
      aria-label="Open navigation"
      @click="emit('toggleSidebar')"
    >
      <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
      </svg>
    </button>

    <div class="min-w-0 flex-1">
      <slot name="title" />
    </div>

    <label v-if="showBranchSwitcher" class="hidden min-w-0 sm:block">
      <span class="sr-only">Working branch</span>
      <select
        class="input max-w-52 py-1.5 text-sm"
        :value="auth.branch?.id"
        :disabled="switching"
        @change="onSwitchBranch"
      >
        <option v-for="branch in switchableBranches" :key="branch.id" :value="branch.id">
          {{ branch.name }}
        </option>
      </select>
    </label>

    <div ref="menuRoot" class="relative shrink-0">
      <button
        type="button"
        class="flex items-center gap-2 rounded-lg px-2 py-1.5 transition-colors hover:bg-slate-100"
        :aria-expanded="menuOpen"
        aria-haspopup="menu"
        @click="menuOpen = !menuOpen"
      >
        <div class="flex size-8 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">
          {{ auth.user?.name?.charAt(0).toUpperCase() }}
        </div>
        <div class="hidden text-left sm:block">
          <p class="text-sm font-medium text-slate-900">{{ auth.user?.name }}</p>
          <p class="text-xs text-slate-500">{{ auth.user?.role.name }}</p>
        </div>
        <svg class="size-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
      </button>

      <Transition
        enter-active-class="transition duration-100 ease-out"
        enter-from-class="scale-95 opacity-0"
        leave-active-class="transition duration-75 ease-in"
        leave-to-class="scale-95 opacity-0"
      >
        <div
          v-if="menuOpen"
          class="absolute right-0 z-40 mt-2 w-56 origin-top-right rounded-xl bg-white py-1.5 shadow-lg ring-1 ring-slate-200"
          role="menu"
        >
          <div class="border-b border-slate-100 px-4 py-2.5">
            <p class="truncate text-sm font-medium text-slate-900">{{ auth.user?.name }}</p>
            <p class="truncate text-xs text-slate-500">{{ auth.user?.email }}</p>
          </div>

          <NuxtLink
            to="/account"
            class="block px-4 py-2.5 text-sm text-slate-700 transition-colors hover:bg-slate-50"
            role="menuitem"
            @click="menuOpen = false"
          >
            My account
          </NuxtLink>

          <button
            type="button"
            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50"
            role="menuitem"
            :disabled="loggingOut"
            @click="logout"
          >
            <AppSpinner v-if="loggingOut" class="size-4" />
            Sign out
          </button>
        </div>
      </Transition>
    </div>
  </header>
</template>

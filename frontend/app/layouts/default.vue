<script setup lang="ts">
const sidebarOpen = ref(false)
const route = useRoute()

// Close the mobile drawer whenever the route changes.
watch(() => route.fullPath, () => { sidebarOpen.value = false })
</script>

<template>
  <div class="min-h-screen">
    <!-- Desktop sidebar -->
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 lg:block">
      <LayoutSidebar />
    </aside>

    <!-- Mobile drawer -->
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      leave-active-class="transition-opacity duration-150"
      leave-to-class="opacity-0"
    >
      <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" @click="sidebarOpen = false" />
    </Transition>

    <Transition
      enter-active-class="transition-transform duration-200 ease-out"
      enter-from-class="-translate-x-full"
      leave-active-class="transition-transform duration-150 ease-in"
      leave-to-class="-translate-x-full"
    >
      <aside v-if="sidebarOpen" class="fixed inset-y-0 left-0 z-50 w-64 lg:hidden">
        <LayoutSidebar @navigate="sidebarOpen = false" />
      </aside>
    </Transition>

    <div class="flex min-h-screen flex-col lg:pl-64">
      <LayoutTopbar @toggle-sidebar="sidebarOpen = !sidebarOpen" />

      <main class="flex-1 p-4 sm:p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

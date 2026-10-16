<script setup lang="ts">
const { isTelegram } = useTelegramWebApp()
</script>

<template>
  <div
    class="flex min-h-screen justify-center p-4"
    :class="isTelegram ? 'tg-auth-shell items-start pt-2' : 'items-center bg-slate-900'"
  >
    <slot />
  </div>
</template>

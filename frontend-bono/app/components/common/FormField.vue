<script setup lang="ts">
/** Label, hint and server-side validation message for a single input. */
defineProps<{
  label?: string
  error?: string
  hint?: string
  required?: boolean
  for?: string
}>()
</script>

<template>
  <div>
    <label v-if="label" :for="$props.for" class="label">
      {{ label }}
      <span v-if="required" class="text-red-500" aria-hidden="true">*</span>
    </label>

    <slot />

    <p v-if="error" class="mt-1.5 text-sm text-red-600">{{ error }}</p>
    <p v-else-if="hint" class="mt-1.5 text-sm text-slate-500">{{ hint }}</p>
  </div>
</template>

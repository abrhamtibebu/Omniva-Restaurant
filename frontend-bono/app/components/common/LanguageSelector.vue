<script setup lang="ts">
withDefaults(defineProps<{
  compact?: boolean
}>(), {
  compact: false,
})

const { locale, setLocale, t } = useI18n()

async function onChange(event: Event) {
  const value = (event.target as HTMLSelectElement).value
  if (value === 'en' || value === 'am') {
    await setLocale(value)
  }
}
</script>

<template>
  <label class="inline-flex items-center gap-1.5" :class="compact ? '' : 'text-sm'">
    <span v-if="!compact" class="sr-only">{{ t('language.label') }}</span>
    <select
      class="input py-1.5 text-sm"
      :class="compact ? 'min-w-0 max-w-[6.5rem] px-2' : 'max-w-[8.5rem]'"
      :value="locale"
      :aria-label="t('language.label')"
      @change="onChange"
    >
      <option value="en">{{ t('language.en') }}</option>
      <option value="am">{{ t('language.am') }}</option>
    </select>
  </label>
</template>

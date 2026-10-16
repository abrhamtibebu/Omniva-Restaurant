<script setup lang="ts">
/**
 * Shared action button with built-in loading spinner.
 * Prefer this over raw <button class="btn-*"> for any async action.
 */
const props = withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost'
  type?: 'button' | 'submit' | 'reset'
  loading?: boolean
  disabled?: boolean
  block?: boolean
  size?: 'sm' | 'md' | 'touch'
  /** Optional aria-label when the visible label is replaced while loading. */
  ariaLabel?: string
}>(), {
  variant: 'primary',
  type: 'button',
  loading: false,
  disabled: false,
  block: false,
  size: 'md',
})

const variantClass: Record<NonNullable<typeof props.variant>, string> = {
  primary: 'btn-primary',
  secondary: 'btn-secondary',
  danger: 'btn-danger',
  ghost: 'btn-ghost',
}

const sizeClass: Record<NonNullable<typeof props.size>, string> = {
  sm: 'px-3 py-1.5 text-sm',
  md: '',
  touch: 'btn-touch',
}
</script>

<template>
  <button
    :type="type"
    class="relative"
    :class="[
      variantClass[variant],
      sizeClass[size],
      block ? 'w-full' : '',
    ]"
    :disabled="disabled || loading"
    :aria-busy="loading || undefined"
    :aria-label="ariaLabel"
  >
    <AppSpinner
      v-if="loading"
      class="size-4 shrink-0"
      :class="size === 'touch' ? 'size-5' : 'size-4'"
    />
    <slot />
  </button>
</template>

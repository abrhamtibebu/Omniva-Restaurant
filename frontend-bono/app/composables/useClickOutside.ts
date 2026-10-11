import type { Ref } from 'vue'

/** Closes popovers and dropdowns when the user clicks or taps elsewhere. */
export function useClickOutside(
  target: Ref<HTMLElement | null>,
  handler: () => void,
) {
  function onPointerDown(event: MouseEvent | TouchEvent) {
    const element = target.value
    if (!element) return

    if (!element.contains(event.target as Node)) {
      handler()
    }
  }

  onMounted(() => {
    document.addEventListener('mousedown', onPointerDown)
    document.addEventListener('touchstart', onPointerDown, { passive: true })
  })

  onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onPointerDown)
    document.removeEventListener('touchstart', onPointerDown)
  })
}

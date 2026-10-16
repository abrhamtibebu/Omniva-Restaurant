/**
 * Bind Telegram MainButton to a primary action; no-op outside Mini App.
 */
export function useTelegramMainButton(
  text: MaybeRefOrGetter<string>,
  onClick: () => void | Promise<void>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  const { isTelegram, setMainButton, haptic } = useTelegramWebApp()

  async function handle() {
    try {
      await onClick()
      haptic('success')
    }
    catch {
      haptic('error')
    }
  }

  function sync() {
    if (!isTelegram.value) {
      setMainButton(null)
      return
    }
    setMainButton({
      text: toValue(text),
      enabled: toValue(enabled),
      visible: true,
      onClick: handle,
    })
  }

  watch([() => toValue(text), () => toValue(enabled), isTelegram], sync, { immediate: true })
  onBeforeUnmount(() => setMainButton(null))
}

const STORAGE_KEY = 'omniva-bono-locale'

export default defineNuxtPlugin(async () => {
  const i18n = useI18nContext()
  const codes = (i18n.locales.value as Array<{ code: string }>).map((l) => l.code)

  function isSupported(code: string | null | undefined): code is 'en' | 'am' {
    return !!code && codes.includes(code)
  }

  const saved = localStorage.getItem(STORAGE_KEY)
  if (isSupported(saved) && saved !== i18n.locale.value) {
    await i18n.setLocale(saved)
  }
  else if (!saved) {
    const tgLang = window.Telegram?.WebApp?.initDataUnsafe?.user?.language_code
    const normalized = tgLang?.toLowerCase().startsWith('am') ? 'am' : null
    if (isSupported(normalized) && normalized !== i18n.locale.value) {
      await i18n.setLocale(normalized)
      localStorage.setItem(STORAGE_KEY, normalized)
    }
  }

  watch(i18n.locale, (value) => {
    localStorage.setItem(STORAGE_KEY, value)
    document.documentElement.setAttribute('lang', value === 'am' ? 'am' : 'en')
  }, { immediate: true })
})

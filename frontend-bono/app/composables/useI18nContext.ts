/**
 * vue-i18n's useI18n() throws MUST_BE_CALL_SETUP_TOP (code 26) outside setup().
 * Plugins, Pinia actions, and route middleware often have no component instance —
 * use the Nuxt global composer instead.
 */
export function useI18nContext() {
  const instance = getCurrentInstance()
  if (instance) {
    return useI18n()
  }
  return useNuxtApp().$i18n
}

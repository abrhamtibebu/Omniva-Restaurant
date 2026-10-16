/**
 * Bono control route gate.
 * Entitlement is required for every page except public auth and billing.
 * Telegram linking is optional — unauthenticated users always go to /login.
 */
export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore()
  const isPublic = to.meta.public === true
  const isBilling = to.path === '/billing'
  const isAuthScreen = to.path === '/login' || to.path === '/signup' || to.path === '/telegram-link'

  if (!auth.isAuthenticated) {
    if (isPublic || isAuthScreen) return
    return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
  }

  // Already signed in: leave auth screens (telegram-link stays reachable only via Account).
  if (to.path === '/login' || to.path === '/signup') {
    return navigateTo(auth.homeRoute)
  }

  if (!auth.bonoEntitled && !isBilling) {
    const { t } = useI18nContext()
    useToast().error(t('middleware.entitlementInactive'))
    return navigateTo('/billing')
  }

  const required = (to.meta.permissions ?? []) as string[]

  if (required.length > 0 && !auth.canAny(required)) {
    const { t } = useI18nContext()
    useToast().error(t('middleware.accessDenied'), t('middleware.accessDeniedDetail'))
    return to.path === auth.homeRoute ? abortNavigation() : navigateTo(auth.homeRoute)
  }
})

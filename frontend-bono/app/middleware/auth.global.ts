/**
 * Bono control route gate.
 * Entitlement is required for every page except public auth and billing.
 */
export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore()
  const isPublic = to.meta.public === true
  const isBilling = to.path === '/billing'

  if (!auth.isAuthenticated) {
    if (isPublic) return

    return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
  }

  if (to.path === '/login' || to.path === '/signup') {
    return navigateTo(auth.homeRoute)
  }

  if (!auth.bonoEntitled && !isBilling) {
    useToast().error('Bono control is not active for this restaurant.')
    return navigateTo('/billing')
  }

  const required = (to.meta.permissions ?? []) as string[]

  if (required.length > 0 && !auth.canAny(required)) {
    useToast().error('Access denied', 'You do not have permission to open that page.')
    return to.path === auth.homeRoute ? abortNavigation() : navigateTo(auth.homeRoute)
  }
})

/**
 * Route-level gate.
 *
 * Pages declare what they need via `definePageMeta({ permissions: [...] })`;
 * anything unlisted requires only a session. This stops a user from reaching a
 * page by typing its URL, but it is not the security boundary — the API
 * re-checks every permission server side.
 */
export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore()
  const isPublic = to.meta.public === true

  if (!auth.isAuthenticated) {
    if (isPublic) return

    return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
  }

  // Signed-in users have no business on the login screen.
  if (to.path === '/login') {
    return navigateTo(auth.homeRoute)
  }

  const required = to.meta.permissions ?? []

  if (required.length > 0 && !auth.canAny(required)) {
    useToast().error('Access denied', 'You do not have permission to open that page.')

    // Each role's home route is always within its own permissions, so this
    // cannot bounce forever; the guard is only here for misconfiguration.
    return to.path === auth.homeRoute ? abortNavigation() : navigateTo(auth.homeRoute)
  }
})

/**
 * Resolves the stored session before the first route guard runs, so middleware
 * never has to guess whether the user is authenticated.
 */
export default defineNuxtPlugin(async () => {
  await useAuthStore().init()
})

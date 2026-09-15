/**
 * Single source for the Sanctum bearer token.
 *
 * Kept in a cookie rather than localStorage so it survives reloads and is sent
 * with a sane expiry. `useApi` reads it directly instead of going through the
 * auth store, which keeps the two from depending on each other.
 */
export function useAuthToken() {
  return useCookie<string | null>('omniva_token', {
    default: () => null,
    maxAge: 60 * 60 * 24 * 14,
    sameSite: 'lax',
    path: '/',
  })
}

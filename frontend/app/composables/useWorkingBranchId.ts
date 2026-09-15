/**
 * Working location for this browser session.
 *
 * Admin/owner may switch branches; the value is sent as `X-Branch-Id` so POS,
 * kitchen, and reports stay on the chosen location even before `/me` refreshes.
 */
export function useWorkingBranchId() {
  return useCookie<string | null>('omniva_branch_id', {
    default: () => null,
    maxAge: 60 * 60 * 24 * 14,
    sameSite: 'lax',
    path: '/',
  })
}

import { defineStore } from 'pinia'
import type { AuthenticatedUser, LoginCredentials, LoginResponse, RoleSlug } from '~/types/auth'
import type { ApiResource } from '~/types/api'

/** Where each role lands after signing in, since not every role sees a dashboard. */
const HOME_BY_ROLE: Record<RoleSlug, string> = {
  admin: '/dashboard',
  manager: '/dashboard',
  cashier: '/orders',
  waiter: '/tables',
  kitchen: '/kitchen',
}

export const useAuthStore = defineStore('auth', () => {
  const token = useAuthToken()
  const workingBranchId = useWorkingBranchId()

  const user = ref<AuthenticatedUser | null>(null)
  /** True once we've settled whether the stored token is usable. */
  const ready = ref(false)

  const isAuthenticated = computed(() => Boolean(user.value && token.value))
  const permissions = computed(() => user.value?.permissions ?? [])
  const role = computed<RoleSlug | null>(() => user.value?.role.slug ?? null)
  const branch = computed(() => user.value?.branch ?? null)
  const availableBranches = computed(() => user.value?.available_branches ?? [])
  const canSwitchBranch = computed(() => Boolean(user.value?.can_switch_branch))
  const currency = computed(() => user.value?.branch?.currency ?? 'ETB')
  const homeRoute = computed(() => (role.value ? HOME_BY_ROLE[role.value] : '/login'))

  function can(permission: string): boolean {
    return permissions.value.includes(permission)
  }

  function canAny(required: string[]): boolean {
    return required.length === 0 || required.some(can)
  }

  function rememberWorkingBranch(next: AuthenticatedUser) {
    if (next.branch?.id) {
      workingBranchId.value = String(next.branch.id)
    }
  }

  function clear() {
    user.value = null
    token.value = null
    workingBranchId.value = null
  }

  async function fetchUser() {
    const response = await useApi().get<ApiResource<AuthenticatedUser>>('/me')
    user.value = response.data
    rememberWorkingBranch(response.data)
    return response.data
  }

  async function login(credentials: LoginCredentials) {
    const response = await useApi().post<LoginResponse>('/login', {
      ...credentials,
      device_name: credentials.device_name ?? 'web',
    })

    token.value = response.token
    user.value = response.user
    rememberWorkingBranch(response.user)
    ready.value = true

    return response.user
  }

  async function switchBranch(branchId: number) {
    workingBranchId.value = String(branchId)
    const response = await useRestaurantApi().switchBranch(branchId)
    user.value = response.data
    rememberWorkingBranch(response.data)
    return response.data
  }

  async function logout() {
    try {
      // Best effort: the local session must end even if the request fails.
      await useApi().post('/logout')
    }
    catch {
      // Intentionally ignored.
    }
    finally {
      clear()
    }
  }

  /**
   * Resolves the session held in the cookie. Runs once on app start; a dead or
   * revoked token simply results in a signed-out state.
   */
  async function init() {
    if (ready.value) return

    if (!token.value) {
      ready.value = true
      return
    }

    try {
      await fetchUser()
    }
    catch {
      clear()
    }
    finally {
      ready.value = true
    }
  }

  return {
    user,
    ready,
    isAuthenticated,
    permissions,
    role,
    branch,
    availableBranches,
    canSwitchBranch,
    currency,
    homeRoute,
    can,
    canAny,
    clear,
    init,
    login,
    logout,
    fetchUser,
    switchBranch,
  }
})

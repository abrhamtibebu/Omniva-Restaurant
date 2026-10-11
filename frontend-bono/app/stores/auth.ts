import { defineStore } from 'pinia'
import type { AuthenticatedUser, LoginCredentials, LoginResponse, RoleSlug } from '~/types/auth'
import type { ApiResource } from '~/types/api'

/** Landing routes for Bono control roles. */
const HOME_BY_ROLE: Record<RoleSlug, string> = {
  admin: '/operations',
  manager: '/requests',
  cashier: '/requests',
  waiter: '/requests',
  kitchen: '/stock-requests',
  barman: '/stock-requests',
  storekeeper: '/purchases',
}

export const useAuthStore = defineStore('auth', () => {
  const token = useAuthToken()
  const workingBranchId = useWorkingBranchId()

  const user = ref<AuthenticatedUser | null>(null)
  const ready = ref(false)

  const isAuthenticated = computed(() => Boolean(user.value && token.value))
  const permissions = computed(() => user.value?.permissions ?? [])
  const role = computed<RoleSlug | null>(() => user.value?.role.slug ?? null)
  const branch = computed(() => user.value?.branch ?? null)
  const availableBranches = computed(() => user.value?.available_branches ?? [])
  const canSwitchBranch = computed(() => Boolean(user.value?.can_switch_branch))
  const currency = computed(() => user.value?.branch?.currency ?? 'ETB')
  const bonoEntitled = computed(() => Boolean(user.value?.bono_control?.entitled))
  const isPlatform = computed(() => Boolean(user.value?.is_platform))
  const homeRoute = computed(() => {
    if (!role.value) return '/login'
    return HOME_BY_ROLE[role.value] ?? '/account'
  })

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
      device_name: credentials.device_name ?? 'bono-web',
    })

    token.value = response.token
    user.value = response.user
    rememberWorkingBranch(response.user)
    ready.value = true

    return response.user
  }

  async function register(body: { restaurant_name: string, owner_name: string, email: string, password: string, phone?: string }) {
    const response = await useApi().post<LoginResponse>('/register', body)
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
      await useApi().post('/logout')
    }
    catch {
      // Intentionally ignored.
    }
    finally {
      clear()
    }
  }

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
    bonoEntitled,
    isPlatform,
    homeRoute,
    can,
    canAny,
    clear,
    init,
    login,
    register,
    logout,
    fetchUser,
    switchBranch,
  }
})

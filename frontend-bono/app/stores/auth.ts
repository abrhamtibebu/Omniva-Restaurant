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

const USER_CACHE_KEY = 'omniva_bono_user_cache'

function readCachedUser(): AuthenticatedUser | null {
  if (!import.meta.client) return null
  try {
    const raw = localStorage.getItem(USER_CACHE_KEY)
    return raw ? JSON.parse(raw) as AuthenticatedUser : null
  }
  catch {
    return null
  }
}

function writeCachedUser(next: AuthenticatedUser | null) {
  if (!import.meta.client) return
  try {
    if (next) localStorage.setItem(USER_CACHE_KEY, JSON.stringify(next))
    else localStorage.removeItem(USER_CACHE_KEY)
  }
  catch {
    // ignore quota / private mode
  }
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
    writeCachedUser(null)
  }

  function setSessionUser(next: AuthenticatedUser) {
    user.value = next
    rememberWorkingBranch(next)
    writeCachedUser(next)
  }

  async function fetchUser() {
    const response = await useApi().get<ApiResource<AuthenticatedUser>>('/me')
    setSessionUser(response.data)
    return response.data
  }

  async function login(credentials: LoginCredentials) {
    const login = credentials.login.trim()
    const looksLikeEmail = login.includes('@')
    const response = await useApi().post<LoginResponse>('/login', {
      login,
      // Older API builds require `email`; only send it when the identifier is an email
      // so phone login is not rejected as an invalid email address.
      ...(looksLikeEmail ? { email: login } : {}),
      password: credentials.password,
      device_name: credentials.device_name ?? 'bono-web',
    })

    token.value = response.token
    setSessionUser(response.user)
    ready.value = true

    return response.user
  }

  async function loginWithTelegram(initData: string) {
    const response = await useApi().post<LoginResponse>('/telegram/auth', {
      init_data: initData,
      device_name: 'telegram-miniapp',
    })
    token.value = response.token
    setSessionUser(response.user)
    ready.value = true
    return response.user
  }

  async function linkTelegram(initData: string) {
    const response = await useApi().post<{ message: string, user: AuthenticatedUser }>('/telegram/link', {
      init_data: initData,
    })
    setSessionUser(response.user)
    return response.user
  }

  async function unlinkTelegram() {
    const response = await useApi().destroy<{ message: string, user: AuthenticatedUser }>('/telegram/link')
    setSessionUser(response.user)
    return response.user
  }

  async function register(body: { restaurant_name: string, owner_name: string, email: string, password: string, phone?: string }) {
    const response = await useApi().post<LoginResponse>('/register', body)
    token.value = response.token
    setSessionUser(response.user)
    ready.value = true
    return response.user
  }

  async function switchBranch(branchId: number) {
    workingBranchId.value = String(branchId)
    const response = await useRestaurantApi().switchBranch(branchId)
    setSessionUser(response.data)
    return response.data
  }

  async function logout() {
    try {
      await useApi().post('/logout')
    }
    catch {
      // Local session must end even if the network call fails.
    }
    finally {
      clear()
      ready.value = true
    }
  }

  async function init() {
    if (ready.value) return

    if (!token.value) {
      ready.value = true
      return
    }

    const cached = readCachedUser()
    if (cached) {
      user.value = cached
      rememberWorkingBranch(cached)
      ready.value = true
      // Refresh in the background so cold opens feel instant.
      fetchUser().catch(() => clear())
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
    loginWithTelegram,
    linkTelegram,
    unlinkTelegram,
    register,
    logout,
    fetchUser,
    switchBranch,
  }
})

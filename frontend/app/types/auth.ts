export type RoleSlug = 'admin' | 'manager' | 'cashier' | 'waiter' | 'kitchen'

export interface Role {
  id: number
  slug: RoleSlug
  name: string
}

export interface Branch {
  id: number
  restaurant_id: number
  name: string
  phone: string | null
  address: string | null
  currency: string
  timezone: string
  tax_rate: string
  tax_identification_number: string | null
  active: boolean
  restaurant?: Restaurant
}

export interface Restaurant {
  id: number
  name: string
  phone: string | null
  address: string | null
  tax_identification_number: string | null
  currency: string
  timezone: string
  active: boolean
}

export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  active: boolean
  role: Role
  branch: Branch | null
  created_at: string
}

/** The authenticated user plus everything the shell needs to render itself. */
export interface BranchSummary {
  id: number
  name: string
  address: string | null
  phone: string | null
  active: boolean
}

export interface AuthenticatedUser extends User {
  permissions: string[]
  available_branches?: BranchSummary[]
  can_switch_branch?: boolean
}

export interface LoginCredentials {
  email: string
  password: string
  device_name?: string
}

export interface LoginResponse {
  token: string
  user: AuthenticatedUser
}

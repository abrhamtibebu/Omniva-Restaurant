import type { ApiResource, Paginated } from '~/types/api'
import type { AuthenticatedUser, Branch } from '~/types/auth'
import type { MenuCategory, MenuItem, Order, RestaurantTable } from '~/types/domain'

function asPaginated<T>(payload: any): Paginated<T> {
  if (payload?.meta) return payload as Paginated<T>
  return {
    data: payload.data ?? payload,
    meta: {
      current_page: payload.current_page ?? 1,
      from: payload.from ?? null,
      last_page: payload.last_page ?? 1,
      per_page: payload.per_page ?? (payload.data?.length ?? 0),
      to: payload.to ?? null,
      total: payload.total ?? (payload.data?.length ?? 0),
    },
  }
}

export function useRestaurantApi() {
  const api = useApi()

  return {
    tables: () => api.get<{ data: RestaurantTable[] }>('/tables'),
    createTable: (body: object) => api.post('/tables', body),
    updateTable: (id: number, body: object) => api.patch(`/tables/${id}`, body),
    updateTableStatus: (id: number, status: string) => api.patch(`/tables/${id}/status`, { status }),

    categories: () => api.get<{ data: MenuCategory[] }>('/menu/categories'),
    items: (params?: Record<string, unknown>) => api.get<Paginated<MenuItem>>('/menu/items', { query: params }),
    createCategory: (body: object) => api.post('/menu/categories', body),
    createItem: (body: object) => api.post('/menu/items', body),
    updateItem: (id: number, body: object) => api.patch(`/menu/items/${id}`, body),
    modifiers: () => api.get<{ data: MenuModifierRow[] }>('/menu/modifiers'),
    createModifier: (body: object) => api.post('/menu/modifiers', body),
    addVariant: (itemId: number, body: object) => api.post(`/menu/items/${itemId}/variants`, body),

    orders: (params?: Record<string, unknown>) =>
      api.get<Paginated<Order>>('/orders', { query: params }),
    order: (id: number) => api.get<{ data: Order }>(`/orders/${id}`),
    createOrder: (body: object) => api.post<{ data: Order }>('/orders', body),
    addItem: (orderId: number, body: object) => api.post(`/orders/${orderId}/items`, body),
    updateItemQty: (orderId: number, itemId: number, body: object) =>
      api.patch(`/orders/${orderId}/items/${itemId}`, body),
    removeItem: (orderId: number, itemId: number) => api.destroy(`/orders/${orderId}/items/${itemId}`),
    submitOrder: (orderId: number) => api.post<{ data: Order }>(`/orders/${orderId}/submit`),
    serveOrder: (orderId: number) => api.post<{ data: Order }>(`/orders/${orderId}/serve`),
    cancelOrder: (orderId: number, reason?: string) =>
      api.post<{ data: Order }>(`/orders/${orderId}/cancel`, { reason }),
    applyDiscount: (orderId: number, amount: string) =>
      api.post<{ data: Order }>(`/orders/${orderId}/discount`, { amount }),
    pay: (orderId: number, body: object) => api.post(`/orders/${orderId}/payments`, body),

    kitchenOrders: () => api.get<{ data: Order[] }>('/kitchen/orders'),
    kitchenStatus: (itemId: number, status: string) =>
      api.patch(`/order-items/${itemId}/kitchen-status`, { status }),

    dashboard: (params?: Record<string, unknown>) => api.get('/dashboard', { query: params }),
    report: (name: string, params?: Record<string, unknown>) =>
      api.get(`/reports/${name}`, { query: params }),

    inventory: (params?: Record<string, unknown>) =>
      api.get('/inventory', { query: params }).then(asPaginated),
    createInventory: (body: object) => api.post('/inventory', body),
    adjustInventory: (id: number, body: object) => api.post(`/inventory/${id}/adjust`, body),

    suppliers: (params?: Record<string, unknown>) =>
      api.get('/suppliers', { query: params }).then(asPaginated),
    createSupplier: (body: object) => api.post('/suppliers', body),
    purchases: (params?: Record<string, unknown>) =>
      api.get('/purchases', { query: params }).then(asPaginated),
    createPurchase: (body: object) => api.post('/purchases', body),
    receivePurchase: (id: number) => api.post(`/purchases/${id}/receive`),

    expenses: (params?: Record<string, unknown>) =>
      api.get('/expenses', { query: params }).then(asPaginated),
    createExpense: (body: object) => api.post('/expenses', body),
    deleteExpense: (id: number) => api.destroy(`/expenses/${id}`),

    customers: (params?: Record<string, unknown>) =>
      api.get('/customers', { query: params }).then(asPaginated),
    createCustomer: (body: object) => api.post('/customers', body),
    customer: (id: number) => api.get(`/customers/${id}`),

    reservations: (params?: Record<string, unknown>) =>
      api.get('/reservations', { query: params }).then(asPaginated),
    createReservation: (body: object) => api.post('/reservations', body),
    updateReservation: (id: number, body: object) => api.patch(`/reservations/${id}`, body),

    users: (params?: Record<string, unknown>) => api.get('/users', { query: params }),
    createUser: (body: object) => api.post('/users', body),
    updateUser: (id: number, body: object) => api.patch(`/users/${id}`, body),
    roles: () => api.get('/roles'),
    clockIn: () => api.post('/shifts/clock-in'),
    clockOut: () => api.post('/shifts/clock-out'),
    todayShifts: () => api.get('/shifts/today'),

    settings: () => api.get('/settings'),
    updateSettings: (body: object) => api.patch('/settings', body),
    branches: () => api.get<ApiResource<Branch[]>>('/branches'),
    createBranch: (body: object) => api.post<ApiResource<Branch>>('/branches', body),
    updateBranch: (id: number, body: object) => api.patch<ApiResource<Branch>>(`/branches/${id}`, body),
    switchBranch: (branchId: number) => api.post<ApiResource<AuthenticatedUser>>('/me/branch', { branch_id: branchId }),
    auditLogs: (params?: Record<string, unknown>) =>
      api.get('/audit-logs', { query: params }).then(asPaginated),
  }
}

interface MenuModifierRow {
  id: number
  name: string
  price: string
  active: boolean
}

import type { ApiResource, Paginated } from '~/types/api'
import type { AuthenticatedUser, Branch } from '~/types/auth'
import type { MenuCategory, MenuItem } from '~/types/domain'

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

/** Bono SPA API surface — entitlement routes live under /bono/*. */
export function useRestaurantApi() {
  const api = useApi()

  return {
    categories: (params?: Record<string, unknown>) =>
      api.get<{ data: MenuCategory[] }>('/menu/categories', { query: params }),
    items: (params?: Record<string, unknown>) => api.get<Paginated<MenuItem>>('/menu/items', { query: params }),
    createCategory: (body: object) => api.post('/menu/categories', body),
    updateCategory: (id: number, body: object) => api.patch(`/menu/categories/${id}`, body),
    createItem: (body: object) => api.post('/menu/items', body),
    updateItem: (id: number, body: object) => api.patch(`/menu/items/${id}`, body),
    itemRecipes: (id: number) => api.get<{ data: any[] }>(`/menu/items/${id}/recipes`),
    saveRecipe: (body: object) => api.post('/recipes', body),
    modifiers: () => api.get<{ data: MenuModifierRow[] }>('/menu/modifiers'),
    createModifier: (body: object) => api.post('/menu/modifiers', body),
    addVariant: (itemId: number, body: object) => api.post(`/menu/items/${itemId}/variants`, body),

    inventory: (params?: Record<string, unknown>) =>
      api.get('/inventory', { query: params }).then(asPaginated),

    users: (params?: Record<string, unknown>) => api.get('/users', { query: params }),
    createUser: (body: object) => api.post('/users', body),
    updateUser: (id: number, body: object) => api.patch(`/users/${id}`, body),
    deleteUser: (id: number) => api.destroy(`/users/${id}`),

    bonoTypes: () => api.get('/bono/types'),
    createBonoType: (body: object) => api.post('/bono/types', body),
    updateBonoType: (id: number, body: object) => api.patch(`/bono/types/${id}`, body),
    bonoBalances: () => api.get('/bono/balances'),
    bonoLedger: () => api.get('/bono/ledger'),
    bonoOpening: (body: object) => api.post('/bono/opening-balances', body),
    bonoPeople: (params?: Record<string, unknown>) => api.get('/bono/people', { query: params }),
    transferReceipts: () => api.get('/bono/transfer-receipts'),
    uploadTransferReceipt: (body: FormData) => api.post('/bono/transfer-receipts', body),
    updateTransferReceipt: (id: number, body: object) => api.patch(`/bono/transfer-receipts/${id}`, body),
    verifyTransferReceipt: (id: number, body: { verification_status: 'verified' | 'rejected' }) =>
      api.post(`/bono/transfer-receipts/${id}/verify`, body),
    transferReceiptFile: (id: number) =>
      api.get<Blob>(`/bono/transfer-receipts/${id}/file`, {
        responseType: 'blob',
        headers: { Accept: '*/*' },
      } as any),
    bonoHandovers: () => api.get('/bono/handovers'),
    createBonoHandover: (body: object) => api.post('/bono/handovers', body),
    approveBonoHandover: (id: number, body: object) => api.post(`/bono/handovers/${id}/approve`, body),
    rejectBonoHandover: (id: number, body: object) => api.post(`/bono/handovers/${id}/reject`, body),
    issueBonoHandover: (id: number, body: object) => api.post(`/bono/handovers/${id}/issue`, body),
    approveAndIssueBonoHandover: (id: number, body: object) => api.post(`/bono/handovers/${id}/approve-and-issue`, body),
    confirmBonoHandover: (id: number, body: object) => api.post(`/bono/handovers/${id}/confirm`, body),

    stockRequests: () => api.get('/bono/stock-requests'),
    createStockRequest: (body: object) => api.post('/bono/stock-requests', body),
    approveStockRequest: (id: number, body: object) => api.post(`/bono/stock-requests/${id}/approve`, body),
    issueStockRequest: (id: number, body: object) => api.post(`/bono/stock-requests/${id}/issue`, body),
    confirmStockRequest: (id: number, body: object) => api.post(`/bono/stock-requests/${id}/confirm`, body),
    redeemBono: (body: object) => api.post('/bono/bar-redemptions', body),
    recordWaste: (body: object) => api.post('/bono/stock-waste', body),
    recordCount: (body: object) => api.post('/bono/stock-counts', body),
    kitchenSales: (body: object) => api.post('/bono/kitchen-sales-summaries', body),

    reconciliations: () => api.get('/bono/reconciliations'),
    submitReconciliation: (body: object) => api.post('/bono/reconciliations', body),
    verifyReconciliation: (id: number, body: object) => api.post(`/bono/reconciliations/${id}/verify`, body),
    acceptReconciliation: (id: number) => api.post(`/bono/reconciliations/${id}/accept`),
    flagReconciliation: (id: number, body: object) => api.post(`/bono/reconciliations/${id}/flag`, body),
    closeReconciliation: (id: number) => api.post(`/bono/reconciliations/${id}/close`),
    discrepancies: () => api.get('/bono/discrepancies'),
    resolveDiscrepancy: (id: number, body: object) => api.post(`/bono/discrepancies/${id}/resolve`, body),
    operationsInbox: () => api.get('/bono/operations/inbox'),
    operationsReports: () => api.get('/bono/operations/reports'),

    suppliers: (params?: Record<string, unknown>) =>
      api.get('/suppliers', { query: params }).then(asPaginated),
    purchases: (params?: Record<string, unknown>) =>
      api.get('/purchases', { query: params }).then(asPaginated),
    createPurchase: (body: object) => api.post('/purchases', body),
    receivePurchase: (id: number, body?: object) => api.post(`/purchases/${id}/receive`, body ?? {}),
    decidePurchase: (id: number, body: object) => api.post(`/purchases/${id}/decide`, body),
    billing: () => api.get('/billing'),
    payInvoice: (id: number) => api.post(`/billing/invoices/${id}/pay`),
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
  }
}

interface MenuModifierRow {
  id: number
  name: string
  price: string
  active: boolean
}

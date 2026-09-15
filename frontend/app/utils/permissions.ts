/**
 * Mirrors `App\Enums\Permission` on the backend.
 *
 * These constants exist only so navigation and UI affordances can be hidden.
 * The backend is the sole authority: every endpoint re-checks the same
 * permission through a policy, so a user who forges a permission list client
 * side still gets a 403.
 */
export const PERMISSIONS = {
  dashboardView: 'dashboard.view',

  usersView: 'users.view',
  usersManage: 'users.manage',

  branchesView: 'branches.view',
  branchesManage: 'branches.manage',
  settingsManage: 'settings.manage',

  tablesView: 'tables.view',
  tablesManage: 'tables.manage',
  tablesUpdateStatus: 'tables.update_status',

  menuView: 'menu.view',
  menuManage: 'menu.manage',

  ordersView: 'orders.view',
  ordersViewOwn: 'orders.view_own',
  ordersCreate: 'orders.create',
  ordersUpdate: 'orders.update',
  ordersSubmit: 'orders.submit',
  ordersServe: 'orders.serve',
  ordersCancel: 'orders.cancel',
  ordersDiscount: 'orders.discount',

  kitchenView: 'kitchen.view',
  kitchenUpdateStatus: 'kitchen.update_status',

  paymentsView: 'payments.view',
  paymentsCreate: 'payments.create',
  paymentsRefund: 'payments.refund',
  paymentsCancel: 'payments.cancel',
  receiptsPrint: 'receipts.print',

  inventoryView: 'inventory.view',
  inventoryManage: 'inventory.manage',
  inventoryAdjust: 'inventory.adjust',

  recipesView: 'recipes.view',
  recipesManage: 'recipes.manage',

  suppliersView: 'suppliers.view',
  suppliersManage: 'suppliers.manage',

  purchasesView: 'purchases.view',
  purchasesManage: 'purchases.manage',

  expensesView: 'expenses.view',
  expensesManage: 'expenses.manage',

  customersView: 'customers.view',
  customersManage: 'customers.manage',

  reservationsView: 'reservations.view',
  reservationsManage: 'reservations.manage',

  shiftsView: 'shifts.view',
  shiftsClock: 'shifts.clock',

  reportsView: 'reports.view',

  auditLogsView: 'audit_logs.view',
} as const

export type Permission = (typeof PERMISSIONS)[keyof typeof PERMISSIONS]

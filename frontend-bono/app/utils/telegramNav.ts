import { PERMISSIONS } from '~/utils/permissions'
import type { RoleSlug } from '~/types/auth'

/** Compact heroicons-style paths for tab / More icons. */
export const TG_ICONS = {
  requests: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z',
  ops: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75',
  bonos: 'M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
  purchases: 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z',
  receipts: 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
  account: 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
  stock: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z',
  bar: 'M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.611L5 14.5',
  close: 'M9.75 9.75l4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
  menu: 'M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 0 1-.923 1.785A5.969 5.969 0 0 0 6 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337Z',
  team: 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
  reconcile: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
  audit: 'M9 12h3.75M9.75 15h1.5m3.75-6H18m-6 3h.008v.008H12V12Zm0 3h.008v.008H12V15Zm0 3h.008v.008H12V18Zm-3.75 3h7.5a2.25 2.25 0 0 0 2.25-2.25V5.25a2.25 2.25 0 0 0-2.25-2.25h-7.5A2.25 2.25 0 0 0 6 5.25v13.5A2.25 2.25 0 0 0 8.25 21Z',
  billing: 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
  more: 'M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z',
  plus: 'M12 4.5v15m7.5-7.5h-15',
} as const

export interface TgTab {
  labelKey: string
  to: string
  icon: string
  permissions: string[]
}

export interface TgMoreSection {
  labelKey: string
  items: TgTab[]
}

/** Primary bottom tabs per role; everything else goes under More. */
const TABS_BY_ROLE: Record<RoleSlug, TgTab[]> = {
  admin: [
    { labelKey: 'nav.requests', to: '/requests', icon: TG_ICONS.requests, permissions: [PERMISSIONS.bonosRequest, PERMISSIONS.bonosIssue] },
    { labelKey: 'nav.ops', to: '/operations', icon: TG_ICONS.ops, permissions: [PERMISSIONS.operationsView] },
    { labelKey: 'nav.bonos', to: '/bonos', icon: TG_ICONS.bonos, permissions: [PERMISSIONS.bonosIssue, PERMISSIONS.bonosTypesManage] },
  ],
  manager: [
    { labelKey: 'nav.requests', to: '/requests', icon: TG_ICONS.requests, permissions: [PERMISSIONS.bonosIssue, PERMISSIONS.bonosRequest] },
    { labelKey: 'nav.ops', to: '/operations', icon: TG_ICONS.ops, permissions: [PERMISSIONS.operationsView] },
    { labelKey: 'nav.purchases', to: '/purchases', icon: TG_ICONS.purchases, permissions: [PERMISSIONS.purchasesApprove, PERMISSIONS.purchasesView] },
  ],
  cashier: [
    { labelKey: 'nav.requests', to: '/requests', icon: TG_ICONS.requests, permissions: [PERMISSIONS.bonosRequest, PERMISSIONS.bonosIssue, PERMISSIONS.bonosConfirm] },
    { labelKey: 'nav.bonos', to: '/bonos', icon: TG_ICONS.bonos, permissions: [PERMISSIONS.bonosIssue, PERMISSIONS.bonosTypesManage] },
    { labelKey: 'nav.receipts', to: '/receipts', icon: TG_ICONS.receipts, permissions: [PERMISSIONS.bonosReconcile, PERMISSIONS.bonosSubmit] },
  ],
  waiter: [
    { labelKey: 'nav.requests', to: '/requests', icon: TG_ICONS.requests, permissions: [PERMISSIONS.bonosRequest, PERMISSIONS.bonosConfirm] },
    { labelKey: 'nav.receipts', to: '/receipts', icon: TG_ICONS.receipts, permissions: [PERMISSIONS.bonosSubmit, PERMISSIONS.bonosReconcile] },
    { labelKey: 'nav.account', to: '/account', icon: TG_ICONS.account, permissions: [] },
  ],
  barman: [
    { labelKey: 'nav.stock', to: '/stock-requests', icon: TG_ICONS.stock, permissions: [PERMISSIONS.stockRequest, PERMISSIONS.stockConfirm] },
    { labelKey: 'nav.bar', to: '/bar', icon: TG_ICONS.bar, permissions: [PERMISSIONS.barRedeem, PERMISSIONS.barClose] },
    { labelKey: 'nav.requests', to: '/requests', icon: TG_ICONS.requests, permissions: [PERMISSIONS.bonosRequest, PERMISSIONS.bonosConfirm] },
  ],
  kitchen: [
    { labelKey: 'nav.stock', to: '/stock-requests', icon: TG_ICONS.stock, permissions: [PERMISSIONS.stockRequest, PERMISSIONS.stockConfirm] },
    { labelKey: 'nav.close', to: '/kitchen-ops', icon: TG_ICONS.close, permissions: [PERMISSIONS.kitchenReconcile] },
  ],
  storekeeper: [
    { labelKey: 'nav.stock', to: '/stock-requests', icon: TG_ICONS.stock, permissions: [PERMISSIONS.stockIssue] },
    { labelKey: 'nav.purchases', to: '/purchases', icon: TG_ICONS.purchases, permissions: [PERMISSIONS.purchasesManage, PERMISSIONS.purchasesView] },
  ],
}

/** Grouped More sheet (items already on the tab bar are filtered out at render time). */
export const TG_MORE_SECTIONS: TgMoreSection[] = [
  {
    labelKey: 'nav.moreWork',
    items: [
      { labelKey: 'nav.operations', to: '/operations', icon: TG_ICONS.ops, permissions: [PERMISSIONS.operationsView] },
      { labelKey: 'nav.bonos', to: '/bonos', icon: TG_ICONS.bonos, permissions: [PERMISSIONS.bonosIssue, PERMISSIONS.bonosTypesManage] },
      { labelKey: 'nav.requests', to: '/requests', icon: TG_ICONS.requests, permissions: [PERMISSIONS.bonosRequest, PERMISSIONS.bonosConfirm, PERMISSIONS.bonosIssue] },
      { labelKey: 'nav.reconciliation', to: '/reconciliation', icon: TG_ICONS.reconcile, permissions: [PERMISSIONS.bonosReconcile, PERMISSIONS.bonosSubmit, PERMISSIONS.barClose, PERMISSIONS.kitchenReconcile] },
      { labelKey: 'nav.receipts', to: '/receipts', icon: TG_ICONS.receipts, permissions: [PERMISSIONS.bonosReconcile, PERMISSIONS.bonosSubmit] },
      { labelKey: 'nav.bar', to: '/bar', icon: TG_ICONS.bar, permissions: [PERMISSIONS.barRedeem, PERMISSIONS.barClose] },
      { labelKey: 'nav.kitchenClose', to: '/kitchen-ops', icon: TG_ICONS.close, permissions: [PERMISSIONS.kitchenReconcile] },
    ],
  },
  {
    labelKey: 'nav.moreStock',
    items: [
      { labelKey: 'nav.stockRequests', to: '/stock-requests', icon: TG_ICONS.stock, permissions: [PERMISSIONS.stockRequest, PERMISSIONS.stockIssue, PERMISSIONS.stockConfirm] },
      { labelKey: 'nav.purchases', to: '/purchases', icon: TG_ICONS.purchases, permissions: [PERMISSIONS.purchasesView, PERMISSIONS.purchasesManage, PERMISSIONS.purchasesApprove] },
      { labelKey: 'nav.menu', to: '/menu', icon: TG_ICONS.menu, permissions: [PERMISSIONS.menuView, PERMISSIONS.menuManage] },
    ],
  },
  {
    labelKey: 'nav.moreAdmin',
    items: [
      { labelKey: 'nav.bonoTeam', to: '/bono-team', icon: TG_ICONS.team, permissions: [PERMISSIONS.usersView, PERMISSIONS.usersManage] },
      { labelKey: 'nav.discrepancies', to: '/discrepancies', icon: TG_ICONS.audit, permissions: [PERMISSIONS.discrepanciesReview] },
      { labelKey: 'nav.billing', to: '/billing', icon: TG_ICONS.billing, permissions: [PERMISSIONS.settingsManage, PERMISSIONS.usersManage] },
    ],
  },
]

/** Flat list kept for page-title lookup. */
export const TG_MORE_ITEMS: TgTab[] = TG_MORE_SECTIONS.flatMap((section) => section.items)

export function telegramTabsForRole(role: RoleSlug | null): TgTab[] {
  if (!role) return [{ labelKey: 'nav.account', to: '/account', icon: TG_ICONS.account, permissions: [] }]
  return TABS_BY_ROLE[role] ?? [{ labelKey: 'nav.account', to: '/account', icon: TG_ICONS.account, permissions: [] }]
}

export function isTelegramRootTab(path: string, tabs: TgTab[]): boolean {
  return tabs.some((tab) => path === tab.to || path.startsWith(`${tab.to}/`))
}

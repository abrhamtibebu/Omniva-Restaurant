<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every distinct capability in the system.
 *
 * Roles are mapped to these in config/permissions.php. Policies and routes
 * check these values, never role names, so adding a role never requires
 * touching authorization logic.
 */
enum Permission: string
{
    case DashboardView = 'dashboard.view';

    case UsersView = 'users.view';
    case UsersManage = 'users.manage';

    case BranchesView = 'branches.view';
    /** Create, update, deactivate, and switch the working branch. Admin-only in the default map. */
    case BranchesManage = 'branches.manage';
    case SettingsManage = 'settings.manage';

    case TablesView = 'tables.view';
    case TablesManage = 'tables.manage';
    case TablesUpdateStatus = 'tables.update_status';

    case MenuView = 'menu.view';
    case MenuManage = 'menu.manage';

    case OrdersView = 'orders.view';
    /** Restricted to orders the user opened themselves. */
    case OrdersViewOwn = 'orders.view_own';
    case OrdersCreate = 'orders.create';
    case OrdersUpdate = 'orders.update';
    case OrdersSubmit = 'orders.submit';
    case OrdersServe = 'orders.serve';
    case OrdersCancel = 'orders.cancel';
    case OrdersDiscount = 'orders.discount';

    case KitchenView = 'kitchen.view';
    case KitchenUpdateStatus = 'kitchen.update_status';

    case PaymentsView = 'payments.view';
    case PaymentsCreate = 'payments.create';
    case PaymentsRefund = 'payments.refund';
    case PaymentsCancel = 'payments.cancel';
    case ReceiptsPrint = 'receipts.print';

    case InventoryView = 'inventory.view';
    case InventoryManage = 'inventory.manage';
    case InventoryAdjust = 'inventory.adjust';

    case RecipesView = 'recipes.view';
    case RecipesManage = 'recipes.manage';

    case SuppliersView = 'suppliers.view';
    case SuppliersManage = 'suppliers.manage';

    case PurchasesView = 'purchases.view';
    case PurchasesManage = 'purchases.manage';

    case ExpensesView = 'expenses.view';
    case ExpensesManage = 'expenses.manage';

    case CustomersView = 'customers.view';
    case CustomersManage = 'customers.manage';

    case ReservationsView = 'reservations.view';
    case ReservationsManage = 'reservations.manage';

    case ShiftsView = 'shifts.view';
    /** Clock the authenticated user in and out. */
    case ShiftsClock = 'shifts.clock';

    case ReportsView = 'reports.view';

    case AuditLogsView = 'audit_logs.view';

    case BonosTypesManage = 'bonos.types.manage';
    case BonosIssue = 'bonos.issue';
    case BonosRequest = 'bonos.request';
    case BonosConfirm = 'bonos.confirm';
    case BonosSubmit = 'bonos.submit';
    case BonosReconcile = 'bonos.reconcile';

    case StockRequest = 'stock.request';
    case StockIssue = 'stock.issue';
    case StockConfirm = 'stock.confirm';

    case BarRedeem = 'bar.redeem';
    case BarClose = 'bar.close';
    case KitchenReconcile = 'kitchen.reconcile';

    case DiscrepanciesReview = 'discrepancies.review';
    case OperationsView = 'operations.view';
    case PurchasesApprove = 'purchases.approve';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}

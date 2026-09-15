<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\RoleSlug;

/**
 * The single authoritative role-to-permission map.
 *
 * Admin is intentionally absent: it is granted everything through
 * Gate::before(), so new permissions never have to be remembered here.
 *
 * Values are stored as strings so `config:cache` remains safe.
 */
return [

    RoleSlug::Manager->value => [
        Permission::DashboardView->value,

        Permission::UsersView->value,
        Permission::UsersManage->value,
        Permission::BranchesView->value,

        Permission::TablesView->value,
        Permission::TablesManage->value,
        Permission::TablesUpdateStatus->value,

        Permission::MenuView->value,
        Permission::MenuManage->value,

        Permission::OrdersView->value,
        Permission::OrdersCreate->value,
        Permission::OrdersUpdate->value,
        Permission::OrdersSubmit->value,
        Permission::OrdersServe->value,
        Permission::OrdersCancel->value,
        Permission::OrdersDiscount->value,

        Permission::KitchenView->value,

        Permission::PaymentsView->value,
        Permission::PaymentsCreate->value,
        Permission::PaymentsRefund->value,
        Permission::PaymentsCancel->value,
        Permission::ReceiptsPrint->value,

        Permission::InventoryView->value,
        Permission::InventoryManage->value,
        Permission::InventoryAdjust->value,
        Permission::RecipesView->value,
        Permission::RecipesManage->value,

        Permission::SuppliersView->value,
        Permission::SuppliersManage->value,
        Permission::PurchasesView->value,
        Permission::PurchasesManage->value,

        Permission::ExpensesView->value,
        Permission::ExpensesManage->value,
        Permission::CustomersView->value,
        Permission::CustomersManage->value,
        Permission::ReservationsView->value,
        Permission::ReservationsManage->value,

        Permission::ShiftsView->value,
        Permission::ShiftsClock->value,

        Permission::ReportsView->value,
        Permission::AuditLogsView->value,
    ],

    RoleSlug::Cashier->value => [
        Permission::TablesView->value,
        Permission::MenuView->value,
        Permission::OrdersView->value,
        Permission::PaymentsView->value,
        Permission::PaymentsCreate->value,
        Permission::ReceiptsPrint->value,
        Permission::CustomersView->value,
        Permission::CustomersManage->value,
        Permission::ShiftsClock->value,
    ],

    RoleSlug::Waiter->value => [
        Permission::TablesView->value,
        Permission::TablesUpdateStatus->value,
        Permission::MenuView->value,
        Permission::OrdersViewOwn->value,
        Permission::OrdersCreate->value,
        Permission::OrdersUpdate->value,
        Permission::OrdersSubmit->value,
        Permission::OrdersServe->value,
        Permission::CustomersView->value,
        Permission::ReservationsView->value,
        Permission::ReservationsManage->value,
        Permission::ShiftsClock->value,
    ],

    RoleSlug::Kitchen->value => [
        Permission::KitchenView->value,
        Permission::KitchenUpdateStatus->value,
        Permission::ShiftsClock->value,
    ],

];

<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BonoController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\KitchenController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\OperationsController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProcurementController;
use App\Http\Controllers\Api\V1\ReconciliationController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\StockControlController;
use App\Http\Controllers\Api\V1\TableController;
use App\Http\Controllers\Api\V1\TelegramAuthController;
use App\Http\Controllers\Api\V1\TransferReceiptController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:login');
Route::post('/telegram/auth', [TelegramAuthController::class, 'auth'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me/password', [AuthController::class, 'updatePassword']);
    Route::post('/me/branch', [AuthController::class, 'switchBranch']);
    Route::post('/telegram/link', [TelegramAuthController::class, 'link']);
    Route::delete('/telegram/link', [TelegramAuthController::class, 'unlink']);

    Route::get('/roles', [MetaController::class, 'roles']);
    Route::get('/settings', [MetaController::class, 'settings']);
    Route::patch('/settings', [MetaController::class, 'updateSettings']);
    Route::get('/branches', [BranchController::class, 'index']);
    Route::post('/branches', [BranchController::class, 'store']);
    Route::patch('/branches/{branch}', [BranchController::class, 'update']);
    Route::get('/audit-logs', [MetaController::class, 'auditLogs']);
    Route::get('/dashboard', DashboardController::class);

    Route::apiResource('users', UserController::class);

    Route::get('/tables', [TableController::class, 'index']);
    Route::post('/tables', [TableController::class, 'store']);
    Route::patch('/tables/{table}', [TableController::class, 'update']);
    Route::patch('/tables/{table}/status', [TableController::class, 'updateStatus']);
    Route::delete('/tables/{table}', [TableController::class, 'destroy']);

    Route::get('/menu/categories', [MenuController::class, 'categories']);
    Route::post('/menu/categories', [MenuController::class, 'storeCategory']);
    Route::patch('/menu/categories/{category}', [MenuController::class, 'updateCategory']);
    Route::delete('/menu/categories/{category}', [MenuController::class, 'destroyCategory']);
    Route::get('/menu/items', [MenuController::class, 'items']);
    Route::post('/menu/items', [MenuController::class, 'storeItem']);
    Route::patch('/menu/items/{item}', [MenuController::class, 'updateItem']);
    Route::get('/menu/items/{item}/recipes', [MenuController::class, 'itemRecipes']);
    Route::post('/menu/items/{item}/variants', [MenuController::class, 'storeVariant']);
    Route::patch('/menu/variants/{variant}', [MenuController::class, 'updateVariant']);
    Route::get('/menu/modifiers', [MenuController::class, 'modifiers']);
    Route::post('/menu/modifiers', [MenuController::class, 'storeModifier']);
    Route::patch('/menu/modifiers/{modifier}', [MenuController::class, 'updateModifier']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/items', [OrderController::class, 'addItem']);
    Route::patch('/orders/{order}/items/{item}', [OrderController::class, 'updateItem']);
    Route::delete('/orders/{order}/items/{item}', [OrderController::class, 'removeItem']);
    Route::post('/orders/{order}/submit', [OrderController::class, 'submit']);
    Route::post('/orders/{order}/serve', [OrderController::class, 'serve']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{order}/discount', [OrderController::class, 'discount']);
    Route::post('/orders/{order}/payments', [OrderController::class, 'pay']);

    Route::get('/kitchen/orders', [KitchenController::class, 'orders']);
    Route::patch('/order-items/{item}/kitchen-status', [KitchenController::class, 'updateItemStatus']);

    Route::post('/payments/{payment}/cancel', [PaymentController::class, 'cancel']);
    Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund']);

    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory', [InventoryController::class, 'store']);
    Route::patch('/inventory/{inventory}', [InventoryController::class, 'update']);
    Route::post('/inventory/{inventory}/adjust', [InventoryController::class, 'adjust']);
    Route::post('/recipes', [InventoryController::class, 'storeRecipe']);

    Route::get('/suppliers', [ProcurementController::class, 'suppliers']);
    Route::post('/suppliers', [ProcurementController::class, 'storeSupplier']);
    Route::get('/suppliers/{supplier}', [ProcurementController::class, 'showSupplier']);
    Route::patch('/suppliers/{supplier}', [ProcurementController::class, 'updateSupplier']);
    Route::get('/purchases', [ProcurementController::class, 'purchases']);
    Route::post('/purchases', [ProcurementController::class, 'storePurchase']);
    Route::get('/purchases/{purchase}', [ProcurementController::class, 'showPurchase']);
    Route::post('/purchases/{purchase}/receive', [ProcurementController::class, 'receivePurchase']);

    Route::get('/expenses', [OperationsController::class, 'expenses']);
    Route::post('/expenses', [OperationsController::class, 'storeExpense']);
    Route::patch('/expenses/{expense}', [OperationsController::class, 'updateExpense']);
    Route::delete('/expenses/{expense}', [OperationsController::class, 'destroyExpense']);

    Route::get('/customers', [OperationsController::class, 'customers']);
    Route::post('/customers', [OperationsController::class, 'storeCustomer']);
    Route::get('/customers/{customer}', [OperationsController::class, 'showCustomer']);
    Route::patch('/customers/{customer}', [OperationsController::class, 'updateCustomer']);

    Route::get('/reservations', [OperationsController::class, 'reservations']);
    Route::post('/reservations', [OperationsController::class, 'storeReservation']);
    Route::patch('/reservations/{reservation}', [OperationsController::class, 'updateReservation']);

    Route::post('/shifts/clock-in', [OperationsController::class, 'clockIn']);
    Route::post('/shifts/clock-out', [OperationsController::class, 'clockOut']);
    Route::get('/shifts/today', [OperationsController::class, 'todayShifts']);

    Route::get('/reports/sales', [ReportController::class, 'sales']);
    Route::get('/reports/products', [ReportController::class, 'products']);
    Route::get('/reports/payments', [ReportController::class, 'payments']);
    Route::get('/reports/expenses', [ReportController::class, 'expenses']);
    Route::get('/reports/inventory', [ReportController::class, 'inventory']);

    Route::get('/billing', [BillingController::class, 'show']);
    Route::post('/billing/invoices/{invoice}/pay', [BillingController::class, 'pay']);

    // Bono control product surface — entitlement required.
    Route::prefix('bono')->middleware('bono')->group(function () {
        Route::get('/types', [BonoController::class, 'types']);
        Route::post('/types', [BonoController::class, 'storeType']);
        Route::patch('/types/{bonoType}', [BonoController::class, 'updateType']);
        Route::get('/balances', [BonoController::class, 'balances']);
        Route::get('/ledger', [BonoController::class, 'ledger']);
        Route::post('/opening-balances', [BonoController::class, 'opening']);
        Route::get('/people', [BonoController::class, 'people']);
        Route::get('/transfer-receipts', [TransferReceiptController::class, 'index']);
        Route::post('/transfer-receipts', [TransferReceiptController::class, 'store']);
        Route::get('/transfer-receipts/{paymentEvidence}/file', [TransferReceiptController::class, 'file']);
        Route::patch('/transfer-receipts/{paymentEvidence}', [TransferReceiptController::class, 'update']);
        Route::post('/transfer-receipts/{paymentEvidence}/verify', [TransferReceiptController::class, 'verify']);
        Route::get('/handovers', [BonoController::class, 'handovers']);
        Route::post('/handovers', [BonoController::class, 'storeHandover']);
        Route::post('/handovers/{bonoHandover}/approve', [BonoController::class, 'approve']);
        Route::post('/handovers/{bonoHandover}/reject', [BonoController::class, 'reject']);
        Route::post('/handovers/{bonoHandover}/issue', [BonoController::class, 'issue']);
        Route::post('/handovers/{bonoHandover}/approve-and-issue', [BonoController::class, 'approveAndIssue']);
        Route::post('/handovers/{bonoHandover}/confirm', [BonoController::class, 'confirm']);

        Route::get('/stock-locations', [StockControlController::class, 'locations']);
        Route::get('/stock-requests', [StockControlController::class, 'index']);
        Route::post('/stock-requests', [StockControlController::class, 'store']);
        Route::post('/stock-requests/{stockRequest}/approve', [StockControlController::class, 'approve']);
        Route::post('/stock-requests/{stockRequest}/reject', [StockControlController::class, 'reject']);
        Route::post('/stock-requests/{stockRequest}/issue', [StockControlController::class, 'issue']);
        Route::post('/stock-requests/{stockRequest}/confirm', [StockControlController::class, 'confirm']);
        Route::post('/bar-redemptions', [StockControlController::class, 'redeem']);
        Route::post('/stock-waste', [StockControlController::class, 'waste']);
        Route::post('/stock-counts', [StockControlController::class, 'count']);
        Route::post('/kitchen-sales-summaries', [StockControlController::class, 'kitchenSales']);

        Route::get('/reconciliations', [ReconciliationController::class, 'index']);
        Route::post('/reconciliations', [ReconciliationController::class, 'store']);
        Route::post('/reconciliations/{reconciliation}/verify', [ReconciliationController::class, 'verify']);
        Route::post('/reconciliations/{reconciliation}/accept', [ReconciliationController::class, 'accept']);
        Route::post('/reconciliations/{reconciliation}/flag', [ReconciliationController::class, 'flag']);
        Route::post('/reconciliations/{reconciliation}/return', [ReconciliationController::class, 'returnForCorrection']);
        Route::post('/reconciliations/{reconciliation}/close', [ReconciliationController::class, 'close']);
        Route::get('/discrepancies', [ReconciliationController::class, 'discrepancies']);
        Route::post('/discrepancies/{discrepancy}/resolve', [ReconciliationController::class, 'resolve']);
        Route::get('/operations/inbox', [ReconciliationController::class, 'inbox']);
        Route::get('/operations/reports', [ReconciliationController::class, 'reports']);
    });

    Route::post('/purchases/{purchase}/decide', [ProcurementController::class, 'decidePurchase']);
});

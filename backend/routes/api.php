<?php

use App\Http\Controllers\Api\V1\AuthController;
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
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\TableController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me/password', [AuthController::class, 'updatePassword']);
    Route::post('/me/branch', [AuthController::class, 'switchBranch']);

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
});

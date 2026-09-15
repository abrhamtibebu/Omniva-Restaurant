<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ExpenseCategory;
use App\Enums\Permission;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Api\Concerns\ResolvesBranch;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Reservation;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationsController extends Controller
{
    use ResolvesBranch;

    public function expenses(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ExpensesView), 403);

        $expenses = Expense::query()
            ->with('creator')
            ->where('branch_id', $this->branchId())
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('expense_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('expense_date', '<=', $request->date('to')))
            ->latest('expense_date')
            ->paginate(min($request->integer('per_page', 20), 100));

        return response()->json($expenses);
    }

    public function storeExpense(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ExpensesManage), 403);

        $expense = Expense::query()->create([
            'branch_id' => $this->branchId(),
            'created_by' => $request->user()->id,
            ...$request->validate([
                'category' => ['required', Rule::enum(ExpenseCategory::class)],
                'description' => ['required', 'string', 'max:255'],
                'amount' => ['required', 'numeric', 'min:0'],
                'expense_date' => ['required', 'date'],
                'reference' => ['nullable', 'string', 'max:120'],
            ]),
        ]);

        return response()->json(['data' => $expense], 201);
    }

    public function updateExpense(Request $request, Expense $expense): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ExpensesManage), 403);
        abort_unless($expense->branch_id === $this->branchId(), 404);

        $expense->update($request->validate([
            'category' => ['sometimes', Rule::enum(ExpenseCategory::class)],
            'description' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'expense_date' => ['sometimes', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]));

        return response()->json(['data' => $expense]);
    }

    public function destroyExpense(Request $request, Expense $expense): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ExpensesManage), 403);
        abort_unless($expense->branch_id === $this->branchId(), 404);
        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }

    public function customers(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::CustomersView), 403);

        $customers = Customer::query()
            ->withCount('orders')
            ->where('branch_id', $this->branchId())
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 20), 100));

        return response()->json($customers);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::CustomersManage), 403);

        $customer = Customer::query()->create([
            'branch_id' => $this->branchId(),
            ...$request->validate([
                'name' => ['required', 'string', 'max:160'],
                'phone' => ['nullable', 'string', 'max:40'],
                'email' => ['nullable', 'email'],
                'notes' => ['nullable', 'string'],
            ]),
        ]);

        return response()->json(['data' => $customer], 201);
    }

    public function showCustomer(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::CustomersView), 403);
        abort_unless($customer->branch_id === $this->branchId(), 404);

        $orders = $customer->orders()->latest()->limit(50)->get(['id', 'order_number', 'total', 'status', 'created_at']);
        $spent = $customer->orders()->where('status', 'completed')->sum('total');

        return response()->json([
            'data' => [
                ...$customer->toArray(),
                'order_count' => $customer->orders()->count(),
                'total_spending' => $spent,
                'orders' => $orders,
            ],
        ]);
    }

    public function updateCustomer(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::CustomersManage), 403);
        abort_unless($customer->branch_id === $this->branchId(), 404);

        $customer->update($request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email'],
            'notes' => ['nullable', 'string'],
        ]));

        return response()->json(['data' => $customer]);
    }

    public function reservations(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ReservationsView), 403);

        $reservations = Reservation::query()
            ->with('table')
            ->where('branch_id', $this->branchId())
            ->when($request->filled('date'), fn ($q) => $q->whereDate('reservation_date', $request->date('date')))
            ->latest('reservation_date')
            ->paginate(min($request->integer('per_page', 20), 100));

        return response()->json($reservations);
    }

    public function storeReservation(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ReservationsManage), 403);

        $reservation = Reservation::query()->create([
            'branch_id' => $this->branchId(),
            ...$request->validate([
                'customer_name' => ['required', 'string', 'max:160'],
                'phone' => ['required', 'string', 'max:40'],
                'table_id' => ['nullable', 'exists:restaurant_tables,id'],
                'reservation_date' => ['required', 'date'],
                'reservation_time' => ['required', 'date_format:H:i'],
                'guest_count' => ['required', 'integer', 'min:1'],
                'notes' => ['nullable', 'string'],
            ]),
        ]);

        return response()->json(['data' => $reservation->load('table')], 201);
    }

    public function updateReservation(Request $request, Reservation $reservation): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ReservationsManage), 403);
        abort_unless($reservation->branch_id === $this->branchId(), 404);

        $reservation->update($request->validate([
            'customer_name' => ['sometimes', 'string', 'max:160'],
            'phone' => ['sometimes', 'string', 'max:40'],
            'table_id' => ['nullable', 'exists:restaurant_tables,id'],
            'reservation_date' => ['sometimes', 'date'],
            'reservation_time' => ['sometimes'],
            'guest_count' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::enum(ReservationStatus::class)],
            'notes' => ['nullable', 'string'],
        ]));

        return response()->json(['data' => $reservation->fresh('table')]);
    }

    public function clockIn(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ShiftsClock), 403);

        $open = Shift::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('clock_out')
            ->first();

        if ($open) {
            return response()->json(['message' => 'You already have an open shift.', 'data' => $open], 422);
        }

        $shift = Shift::query()->create([
            'user_id' => $request->user()->id,
            'branch_id' => $this->branchId(),
            'clock_in' => now(),
            'notes' => $request->input('notes'),
        ]);

        return response()->json(['data' => $shift], 201);
    }

    public function clockOut(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ShiftsClock), 403);

        $shift = Shift::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        if (! $shift) {
            return response()->json(['message' => 'No open shift to clock out.'], 422);
        }

        $shift->update(['clock_out' => now()]);

        return response()->json(['data' => $shift]);
    }

    public function todayShifts(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission(Permission::ShiftsView)
            || $request->user()->hasPermission(Permission::ShiftsClock),
            403,
        );

        $shifts = Shift::query()
            ->with('user')
            ->where('branch_id', $this->branchId())
            ->whereDate('clock_in', now()->toDateString())
            ->orderBy('clock_in')
            ->get();

        return response()->json(['data' => $shifts]);
    }
}

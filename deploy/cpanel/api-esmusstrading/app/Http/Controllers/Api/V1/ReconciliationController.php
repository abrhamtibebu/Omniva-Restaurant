<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\PurchaseApprovalStatus;
use App\Http\Controllers\Api\Concerns\ResolvesBranch;
use App\Http\Controllers\Controller;
use App\Models\BonoBalance;
use App\Models\BonoHandover;
use App\Models\DepartmentSalesSummary;
use App\Models\DiscrepancyCase;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Reconciliation;
use App\Models\StockBalance;
use App\Models\StockRequest;
use App\Services\ReconciliationService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    use ResolvesBranch;

    public function __construct(private ReconciliationService $reconciliations) {}

    public function index(Request $request): JsonResponse
    {
        $this->canAny($request, [
            Permission::BonosReconcile, Permission::BonosSubmit, Permission::BarClose, Permission::KitchenReconcile, Permission::DiscrepanciesReview,
        ]);

        $query = Reconciliation::query()
            ->with('lines.bonoType', 'lines.item', 'evidence', 'submitter:id,name')
            ->where('branch_id', $this->branchId())
            ->latest();

        $user = $request->user();
        if (! $user->hasPermission(Permission::BonosReconcile) && ! $user->hasPermission(Permission::DiscrepanciesReview) && ! $user->isAdmin()) {
            $query->where('submitted_by', $user->id);
        }

        return response()->json(['data' => $query->limit(100)->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $department = $request->string('department')->toString();
        $this->assertSubmit($request, $department);

        $data = $request->validate([
            'department' => ['required', 'in:waiter,bar,kitchen'],
            'notes' => ['nullable', 'string'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.bono_type_id' => ['nullable', 'integer'],
            'lines.*.inventory_item_id' => ['nullable', 'integer'],
            'lines.*.sold_quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.returned_quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.physical_quantity' => ['nullable', 'numeric', 'min:0'],
            'transfers' => ['sometimes', 'array'],
            'transfers.*.method' => ['required_with:transfers', 'string'],
            'transfers.*.amount' => ['required_with:transfers', 'numeric', 'gt:0'],
            'transfers.*.reference' => ['nullable', 'string'],
        ]);

        $reconciliation = $this->reconciliations->submit(
            $request->user(),
            $this->branchId(),
            $data['department'],
            $data['lines'],
            (string) ($data['cash_amount'] ?? '0'),
            $data['transfers'] ?? [],
            $data['notes'] ?? null,
        );

        return response()->json(['data' => $reconciliation], 201);
    }

    public function verify(Request $request, Reconciliation $reconciliation): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::BonosReconcile), 403);
        abort_unless($reconciliation->branch_id === $this->branchId(), 404);

        $data = $request->validate([
            'verified_cash' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['sometimes', 'array'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.verified_quantity' => ['required', 'numeric', 'min:0'],
            'transfers' => ['sometimes', 'array'],
            'transfers.*.id' => ['required', 'integer'],
            'transfers.*.verification_status' => ['required', 'in:pending,verified,rejected'],
        ]);

        return response()->json([
            'data' => $this->reconciliations->verify(
                $reconciliation->load('lines', 'evidence'),
                $request->user(),
                $data['lines'] ?? [],
                isset($data['verified_cash']) ? (string) $data['verified_cash'] : null,
                $data['transfers'] ?? [],
            ),
        ]);
    }

    public function accept(Request $request, Reconciliation $reconciliation): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::BonosReconcile), 403);
        abort_unless($reconciliation->branch_id === $this->branchId(), 404);

        return response()->json(['data' => $this->reconciliations->accept($reconciliation, $request->user())]);
    }

    public function flag(Request $request, Reconciliation $reconciliation): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::BonosReconcile), 403);
        abort_unless($reconciliation->branch_id === $this->branchId(), 404);
        $data = $request->validate(['reason' => ['required', 'string']]);

        return response()->json(['data' => $this->reconciliations->flag($reconciliation->load('lines'), $request->user(), $data['reason'])]);
    }

    public function returnForCorrection(Request $request, Reconciliation $reconciliation): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::BonosReconcile), 403);
        abort_unless($reconciliation->branch_id === $this->branchId(), 404);
        $data = $request->validate(['reason' => ['required', 'string']]);

        return response()->json(['data' => $this->reconciliations->returnForCorrection($reconciliation, $request->user(), $data['reason'])]);
    }

    public function close(Request $request, Reconciliation $reconciliation): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission(Permission::BonosReconcile)
            || $request->user()->hasPermission(Permission::DiscrepanciesReview),
            403,
        );
        abort_unless($reconciliation->branch_id === $this->branchId(), 404);

        return response()->json(['data' => $this->reconciliations->close($reconciliation, $request->user())]);
    }

    public function discrepancies(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission(Permission::DiscrepanciesReview)
            || $request->user()->hasPermission(Permission::BonosReconcile)
            || $request->user()->hasPermission(Permission::OperationsView),
            403,
        );

        return response()->json([
            'data' => DiscrepancyCase::query()
                ->with('opener:id,name', 'resolver:id,name')
                ->where('branch_id', $this->branchId())
                ->latest()
                ->limit(100)
                ->get(),
        ]);
    }

    public function resolve(Request $request, DiscrepancyCase $discrepancy): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::DiscrepanciesReview), 403);
        abort_unless($discrepancy->branch_id === $this->branchId(), 404);

        $data = $request->validate([
            'resolution' => ['required', 'string'],
            'adjustment' => ['sometimes', 'array'],
            'adjustment.bono_type_id' => ['nullable', 'integer'],
            'adjustment.holder_type' => ['nullable', 'in:central,user'],
            'adjustment.holder_id' => ['nullable', 'integer'],
            'adjustment.inventory_item_id' => ['nullable', 'integer'],
            'adjustment.location_id' => ['nullable', 'integer'],
            'adjustment.direction' => ['nullable', 'in:in,out'],
            'adjustment.quantity' => ['nullable', 'numeric', 'gt:0'],
        ]);

        return response()->json([
            'data' => $this->reconciliations->resolve(
                $discrepancy,
                $request->user(),
                $data['resolution'],
                $data['adjustment'] ?? null,
            ),
        ]);
    }

    public function inbox(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::OperationsView) || $request->user()->isAdmin(), 403);

        $branchId = $this->branchId();

        return response()->json(['data' => [
            'handovers_awaiting_confirmation' => BonoHandover::query()->where('branch_id', $branchId)->where('status', 'awaiting_confirmation')->count(),
            'bono_requests' => BonoHandover::query()->where('branch_id', $branchId)->where('status', 'submitted')->count(),
            'stock_requests' => StockRequest::query()->where('branch_id', $branchId)->where('status', 'submitted')->count(),
            'stock_awaiting_confirmation' => StockRequest::query()->where('branch_id', $branchId)->where('status', 'awaiting_confirmation')->count(),
            'reconciliations' => Reconciliation::query()->where('branch_id', $branchId)->whereIn('status', ['submitted', 'under_review', 'discrepancy'])->count(),
            'open_discrepancies' => DiscrepancyCase::query()->where('branch_id', $branchId)->where('status', 'open')->count(),
        ]]);
    }

    public function reports(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission(Permission::ReportsView)
            || $request->user()->hasPermission(Permission::OperationsView),
            403,
        );

        $branchId = $this->branchId();
        $posSales = Order::query()
            ->where('branch_id', $branchId)
            ->where('status', OrderStatus::Completed)
            ->sum('total');

        $summaries = DepartmentSalesSummary::query()->where('branch_id', $branchId)->latest()->limit(100)->get();
        $bonoBalances = BonoBalance::query()->with('bonoType')->where('branch_id', $branchId)->get();
        $discrepancies = DiscrepancyCase::query()->where('branch_id', $branchId)->latest()->limit(50)->get();
        $pendingApprovals = Purchase::query()
            ->where('branch_id', $branchId)
            ->where('approval_status', PurchaseApprovalStatus::Pending)
            ->count();

        $inbox = [
            'handovers_awaiting_confirmation' => BonoHandover::query()->where('branch_id', $branchId)->where('status', 'awaiting_confirmation')->count(),
            'bono_requests' => BonoHandover::query()->where('branch_id', $branchId)->where('status', 'submitted')->count(),
            'stock_requests' => StockRequest::query()->where('branch_id', $branchId)->where('status', 'submitted')->count(),
            'stock_awaiting_confirmation' => StockRequest::query()->where('branch_id', $branchId)->where('status', 'awaiting_confirmation')->count(),
            'reconciliations' => Reconciliation::query()->where('branch_id', $branchId)->whereIn('status', ['submitted', 'under_review', 'discrepancy'])->count(),
            'open_discrepancies' => DiscrepancyCase::query()->where('branch_id', $branchId)->where('status', 'open')->count(),
        ];

        $custodyByType = $bonoBalances
            ->groupBy('bono_type_id')
            ->map(function ($rows) {
                $qty = $rows->reduce(fn ($sum, $row) => bcadd((string) $sum, (string) $row->quantity, 3), '0');

                return [
                    'bono_type_id' => $rows->first()->bono_type_id,
                    'name' => $rows->first()->bonoType?->name ?? 'Bono',
                    'quantity' => $qty,
                ];
            })
            ->values()
            ->sortByDesc(fn ($row) => (float) $row['quantity'])
            ->values()
            ->take(8);

        $custodyByHolder = $bonoBalances
            ->groupBy(fn ($row) => $row->holder_type.':'.$row->holder_id)
            ->map(function ($rows) {
                $qty = $rows->reduce(fn ($sum, $row) => bcadd((string) $sum, (string) $row->quantity, 3), '0');
                $first = $rows->first();

                return [
                    'holder_type' => $first->holder_type,
                    'holder_id' => $first->holder_id,
                    'label' => $first->holder_type === 'central' ? 'Central' : 'User #'.$first->holder_id,
                    'quantity' => $qty,
                ];
            })
            ->values()
            ->sortByDesc(fn ($row) => (float) $row['quantity'])
            ->values()
            ->take(8);

        $reconTrend = Reconciliation::query()
            ->where('branch_id', $branchId)
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->get(['id', 'status', 'created_at'])
            ->groupBy(fn ($row) => $row->created_at?->toDateString())
            ->map(fn ($rows, $date) => [
                'date' => $date,
                'count' => $rows->count(),
            ])
            ->values();

        // Fill missing days so the sparkline is always 7 points.
        $trendFilled = collect(range(6, 0))->map(function (int $ago) use ($reconTrend) {
            $date = now()->subDays($ago)->toDateString();
            $hit = $reconTrend->firstWhere('date', $date);

            return ['date' => $date, 'count' => (int) ($hit['count'] ?? 0)];
        });

        return response()->json(['data' => [
            'inbox' => $inbox,
            'action_items' => array_sum($inbox) + $pendingApprovals,
            'charts' => [
                'action_queue' => collect($inbox)->map(fn ($value, $key) => [
                    'key' => $key,
                    'value' => (int) $value,
                ])->values(),
                'custody_by_type' => $custodyByType,
                'custody_by_holder' => $custodyByHolder,
                'reconciliation_trend' => $trendFilled,
            ],
            'bono_balances' => $bonoBalances,
            'reconciliations' => Reconciliation::query()->where('branch_id', $branchId)->latest()->limit(50)->get(),
            'stock_balances' => StockBalance::query()->with('item', 'location')->whereHas('location', fn ($q) => $q->where('branch_id', $branchId))->get(),
            'transfers' => StockRequest::query()->with('lines')->where('branch_id', $branchId)->latest()->limit(50)->get(),
            'discrepancies' => $discrepancies,
            'purchases' => Purchase::query()->where('branch_id', $branchId)->latest()->limit(50)->get(['id', 'purchase_number', 'total', 'payment_status', 'approval_status', 'received_at']),
            'pos_sales' => Money::of((string) $posSales),
            'department_summaries' => $summaries,
            'department_summaries_are_revenue' => false,
            'pending_purchase_approvals' => $pendingApprovals,
        ]]);
    }

    private function assertSubmit(Request $request, string $department): void
    {
        $allowed = match ($department) {
            'waiter' => $request->user()->hasPermission(Permission::BonosSubmit),
            'bar' => $request->user()->hasPermission(Permission::BarClose),
            'kitchen' => $request->user()->hasPermission(Permission::KitchenReconcile),
            default => false,
        };

        abort_unless($allowed, 403);
    }

    /**
     * @param  list<Permission>  $permissions
     */
    private function canAny(Request $request, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($request->user()->hasPermission($permission)) {
                return;
            }
        }

        abort(403);
    }
}

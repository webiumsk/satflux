<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\PosOrder;
use App\Models\PosTerminal;
use App\Models\Store;
use App\Models\User;
use App\Models\WalletConnection;
use App\Services\StatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Platform-wide stats for admin dashboard.
     */
    public function stats(): \Illuminate\Http\JsonResponse
    {
        $data = Cache::remember('admin.platform_stats.v5', 600, fn () => $this->getPlatformStats());

        return response()->json($data);
    }

    /**
     * Export platform stats as CSV.
     */
    public function statsExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = Cache::remember('admin.platform_stats.v5', 600, fn () => $this->getPlatformStats());

        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Metric', 'Value']);
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    continue;
                }
                fputcsv($out, [$key, $value]);
            }
            $trends = $data['trends_30d'] ?? $data['trends_7d'] ?? [];
            if (! empty($trends)) {
                fputcsv($out, []);
                fputcsv($out, ['date', 'users', 'stores', 'pos_orders']);
                foreach ($trends as $row) {
                    fputcsv($out, [
                        $row['date'] ?? '',
                        $row['users'] ?? 0,
                        $row['stores'] ?? 0,
                        $row['pos_orders'] ?? 0,
                    ]);
                }
            }
            fclose($out);
        });

        $filename = 'platform-stats-' . now()->format('Y-m-d-His') . '.csv';
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * Build platform stats array.
     */
    private function getPlatformStats(): array
    {
        $sevenDaysAgo = now()->subDays(7);
        $thirtyDaysAgo = now()->subDays(30);

        $merchantRoles = ['free', 'pro', 'enterprise'];

        $usersTotal = User::count();
        $users7d = User::where('created_at', '>=', $sevenDaysAgo)->count();
        $users30d = User::where('created_at', '>=', $thirtyDaysAgo)->count();

        $merchantsTotal = User::whereIn('role', $merchantRoles)->count();
        $usersByRole = User::selectRaw('role, count(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role')
            ->toArray();

        $storesTotal = Store::count();
        $stores7d = Store::where('created_at', '>=', $sevenDaysAgo)->count();
        $stores30d = Store::where('created_at', '>=', $thirtyDaysAgo)->count();

        $posTerminalsTotal = PosTerminal::count();
        $appsTotal = App::count();

        $posOrdersPaidTotal = PosOrder::where('status', PosOrder::STATUS_PAID)->count();
        $posOrdersPaid30d = PosOrder::where('status', PosOrder::STATUS_PAID)
            ->where('paid_at', '>=', $thirtyDaysAgo)
            ->count();

        // SATS: explicit SATS only (case-insensitive). EUR: everything else (fiat).
        $posOrdersAmount30dSats = (float) PosOrder::where('status', PosOrder::STATUS_PAID)
            ->where('paid_at', '>=', $thirtyDaysAgo)
            ->whereRaw("UPPER(TRIM(COALESCE(currency, ''))) = ?", ['SATS'])
            ->sum('amount');
        $posOrdersAmount30dEur = (float) PosOrder::where('status', PosOrder::STATUS_PAID)
            ->where('paid_at', '>=', $thirtyDaysAgo)
            ->whereRaw("UPPER(TRIM(COALESCE(currency, ''))) != ?", ['SATS'])
            ->sum('amount');
        $posOrdersAmountTotalSats = (float) PosOrder::where('status', PosOrder::STATUS_PAID)
            ->whereRaw("UPPER(TRIM(COALESCE(currency, ''))) = ?", ['SATS'])
            ->sum('amount');
        $posOrdersAmountTotalEur = (float) PosOrder::where('status', PosOrder::STATUS_PAID)
            ->whereRaw("UPPER(TRIM(COALESCE(currency, ''))) != ?", ['SATS'])
            ->sum('amount');

        $walletConnectionsNeedsSupport = WalletConnection::where('status', 'needs_support')->count();

        $paidPlanCount = User::whereIn('role', ['pro', 'enterprise'])->count();

        // Trend data: users and stores created per day (last 30 days)
        $dateExpression = DB::getDriverName() === 'sqlite'
            ? 'date(created_at)'
            : 'CAST(created_at AS DATE)';

        $usersByDay = User::selectRaw("{$dateExpression} as day, count(*) as count")
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day')
            ->toArray();

        $storesByDay = Store::selectRaw("{$dateExpression} as day, count(*) as count")
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day')
            ->toArray();

        $dateExprPaid = DB::getDriverName() === 'sqlite'
            ? 'date(paid_at)'
            : 'CAST(paid_at AS DATE)';
        $posOrdersByDay = PosOrder::selectRaw("{$dateExprPaid} as day, count(*) as count")
            ->where('status', PosOrder::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $thirtyDaysAgo)
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day')
            ->toArray();

        $posOrdersAmountByDaySats = PosOrder::selectRaw("{$dateExprPaid} as day, COALESCE(SUM(amount), 0) as total")
            ->where('status', PosOrder::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $thirtyDaysAgo)
            ->whereRaw("UPPER(TRIM(COALESCE(currency, ''))) = ?", ['SATS'])
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->toArray();
        $posOrdersAmountByDayEur = PosOrder::selectRaw("{$dateExprPaid} as day, COALESCE(SUM(amount), 0) as total")
            ->where('status', PosOrder::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $thirtyDaysAgo)
            ->whereRaw("UPPER(TRIM(COALESCE(currency, ''))) != ?", ['SATS'])
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->toArray();

        $days30 = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->format('Y-m-d');
            $days30[] = [
                'date' => $day,
                'users' => (int) ($usersByDay[$day] ?? 0),
                'stores' => (int) ($storesByDay[$day] ?? 0),
                'pos_orders' => (int) ($posOrdersByDay[$day] ?? 0),
                'pos_amount_sats' => round((float) ($posOrdersAmountByDaySats[$day] ?? 0), 0),
                'pos_amount_eur' => round((float) ($posOrdersAmountByDayEur[$day] ?? 0), 2),
            ];
        }

        // Trends 7 days
        $usersByDay7d = User::selectRaw("{$dateExpression} as day, count(*) as count")
            ->where('created_at', '>=', $sevenDaysAgo)
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day')
            ->toArray();
        $storesByDay7d = Store::selectRaw("{$dateExpression} as day, count(*) as count")
            ->where('created_at', '>=', $sevenDaysAgo)
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day')
            ->toArray();
        $posOrdersByDay7d = PosOrder::selectRaw("{$dateExprPaid} as day, count(*) as count")
            ->where('status', PosOrder::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $sevenDaysAgo)
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day')
            ->toArray();
        $posOrdersAmountByDay7dSats = PosOrder::selectRaw("{$dateExprPaid} as day, COALESCE(SUM(amount), 0) as total")
            ->where('status', PosOrder::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $sevenDaysAgo)
            ->whereRaw("UPPER(TRIM(COALESCE(currency, ''))) = ?", ['SATS'])
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->toArray();
        $posOrdersAmountByDay7dEur = PosOrder::selectRaw("{$dateExprPaid} as day, COALESCE(SUM(amount), 0) as total")
            ->where('status', PosOrder::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $sevenDaysAgo)
            ->whereRaw("UPPER(TRIM(COALESCE(currency, ''))) != ?", ['SATS'])
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->toArray();

        $days7 = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->format('Y-m-d');
            $days7[] = [
                'date' => $day,
                'users' => (int) ($usersByDay7d[$day] ?? 0),
                'stores' => (int) ($storesByDay7d[$day] ?? 0),
                'pos_orders' => (int) ($posOrdersByDay7d[$day] ?? 0),
                'pos_amount_sats' => round((float) ($posOrdersAmountByDay7dSats[$day] ?? 0), 0),
                'pos_amount_eur' => round((float) ($posOrdersAmountByDay7dEur[$day] ?? 0), 2),
            ];
        }

        // Top stores by paid PoS orders (with amounts by currency), only stores with at least 1 order
        $topStoresByPosOrders = Store::whereHas('posOrders', fn ($q) => $q->where('status', PosOrder::STATUS_PAID))
            ->withCount(['posOrders' => fn ($q) => $q->where('status', PosOrder::STATUS_PAID)])
            ->withSum(['posOrders as pos_amount_sats' => fn ($q) => $q->where('status', PosOrder::STATUS_PAID)->whereRaw("UPPER(TRIM(COALESCE(currency, ''))) = ?", ['SATS'])], 'amount')
            ->withSum(['posOrders as pos_amount_eur' => fn ($q) => $q->where('status', PosOrder::STATUS_PAID)->whereRaw("UPPER(TRIM(COALESCE(currency, ''))) != ?", ['SATS'])], 'amount')
            ->with('user:id,email')
            ->orderByDesc('pos_orders_count')
            ->take(10)
            ->get(['id', 'name', 'user_id'])
            ->map(fn ($s) => [
                'store_id' => $s->id,
                'store_name' => $s->name,
                'user_email' => $s->user?->email ?? null,
                'pos_orders_paid' => (int) $s->pos_orders_count,
                'pos_amount_sats' => round((float) ($s->pos_amount_sats ?? 0), 0),
                'pos_amount_eur' => round((float) ($s->pos_amount_eur ?? 0), 2),
            ])
            ->values()
            ->toArray();

        return [
            'users_total' => $usersTotal,
            'users_7d' => $users7d,
            'users_30d' => $users30d,
            'merchants_total' => $merchantsTotal,
            'users_by_role' => $usersByRole,
            'stores_total' => $storesTotal,
            'stores_7d' => $stores7d,
            'stores_30d' => $stores30d,
            'pos_terminals_total' => $posTerminalsTotal,
            'apps_total' => $appsTotal,
            'pos_orders_paid_total' => $posOrdersPaidTotal,
            'pos_orders_paid_30d' => $posOrdersPaid30d,
            'pos_orders_amount_30d_sats' => round($posOrdersAmount30dSats, 0),
            'pos_orders_amount_30d_eur' => round($posOrdersAmount30dEur, 2),
            'pos_orders_amount_total_sats' => round($posOrdersAmountTotalSats, 0),
            'pos_orders_amount_total_eur' => round($posOrdersAmountTotalEur, 2),
            'wallet_connections_needs_support' => $walletConnectionsNeedsSupport,
            'paid_plan_count' => $paidPlanCount,
            'trends_7d' => $days7,
            'trends_30d' => $days30,
            'top_stores_by_pos_orders' => $topStoresByPosOrders,
        ];
    }

    /**
     * List all users.
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Optional search by email
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('email', 'like', "%{$search}%");
        }

        // Optional filter by role
        if ($request->has('role')) {
            $role = $request->get('role');
            if ($role !== 'all') {
                $query->where('role', $role);
            }
        }

        $users = $query->withCount('stores')
            ->orderBy('created_at', 'desc')
            ->select('id', 'email', 'role', 'email_verified_at', 'created_at', 'updated_at', 'last_login_at')
            ->paginate(20);

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Get a specific user with stores, subscription, and stats.
     */
    public function show(Request $request, User $user)
    {
        $user->load(['stores.walletConnection', 'stores.posTerminals']);

        $stores = $user->stores->map(function (Store $store) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'created_at' => $store->created_at,
                'wallet_type' => $store->wallet_type,
                'pos_terminal_count' => $store->posTerminals->count(),
                'wallet_connection_status' => $store->walletConnection?->status ?? null,
            ];
        })->toArray();

        $subscription = $user->currentSubscription();
        $plan = $user->currentSubscriptionPlan();
        $subscriptionData = [
            'plan' => $plan?->code ?? $plan?->name ?? 'free',
            'status' => $subscription ? ($subscription->isActive() ? 'active' : ($subscription->isInGracePeriod() ? 'grace' : 'expired')) : 'none',
            'expires_at' => $subscription?->expires_at?->toIso8601String(),
        ];

        $stats = null;
        try {
            $stats = app(StatsService::class)->getAdvancedStats($user);
        } catch (\Throwable $e) {
            Log::warning('Admin user stats failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        $posOrdersTotal = PosOrder::whereIn('store_id', $user->stores->pluck('id'))->where('status', PosOrder::STATUS_PAID)->count();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role ?? 'free',
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'stores' => $stores,
                'subscription' => $subscriptionData,
                'stats' => $stats,
                'pos_orders_paid_total' => $posOrdersTotal,
            ],
        ]);
    }

    /**
     * Update a user.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'role' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['free', 'support', 'admin', 'pro', 'enterprise']),
            ],
        ]);

        DB::transaction(function () use ($request, $user) {
            $updated = [];

            if ($request->has('email')) {
                $oldEmail = $user->email;
                $user->email = $request->email;
                $updated['email'] = $oldEmail;
            }

            if ($request->has('role')) {
                $oldRole = $user->role ?? 'free';
                $user->role = $request->role;
                $updated['role'] = $oldRole;
            }

            $user->save();

            Log::info('Admin updated user', [
                'admin_id' => $request->user()->id,
                'user_id' => $user->id,
                'updated_fields' => array_keys($updated),
                'old_values' => $updated,
            ]);
        });

        return response()->json([
            'message' => 'User updated successfully',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role ?? 'free',
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, User $user)
    {
        // Prevent deleting yourself
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 403);
        }

        $userId = $user->id;
        $userEmail = $user->email;

        DB::transaction(function () use ($user) {
            // Delete user's stores (cascade will handle related records)
            $user->stores()->delete();
            
            // Delete the user
            $user->delete();
        });

        Log::info('Admin deleted user', [
            'admin_id' => $request->user()->id,
            'deleted_user_id' => $userId,
            'deleted_user_email' => $userEmail,
        ]);

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}








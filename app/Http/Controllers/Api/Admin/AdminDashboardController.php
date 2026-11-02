<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    /**
     * Get admin dashboard summary stats.
     */
    public function stats(): JsonResponse
    {
        $totalRevenue    = Order::whereNotIn('status', ['Cancelled'])->sum('total');
        $totalOrders     = Order::count();
        $pendingOrders   = Order::where('status', 'Pending')->count();
        $deliveredOrders = Order::where('status', 'Delivered')->count();
        $totalProducts   = Product::count();
        $categoriesCount = Category::count();
        $customersCount  = User::where('role', '!=', 'admin')->count();

        return response()->json([
            'categories_count' => $categoriesCount,
            'products_count'   => $totalProducts,
            'orders_count'     => $totalOrders,
            'pending_orders'   => $pendingOrders,
            'delivered_orders' => $deliveredOrders,
            'customers_count'  => $customersCount,
            'total_revenue'    => (float) round($totalRevenue, 2),
            'users_count'      => User::where('role', 'user')->count(),
            'admins_count'     => User::where('role', 'admin')->count(),
        ]);
    }

    /**
     * Get real monthly sales and orders breakdown from database.
     */
    public function monthlySales(Request $request): JsonResponse
    {
        $year  = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $salesData  = array_fill(0, 12, 0.0);
        $ordersData = array_fill(0, 12, 0);

        // Fetch non-cancelled orders for the requested year
        $orders = Order::whereYear('created_at', $year)
            ->whereNotIn('status', ['Cancelled'])
            ->get(['id', 'total', 'status', 'created_at']);

        foreach ($orders as $order) {
            $orderDate  = Carbon::parse($order->created_at);
            $monthIndex = $orderDate->month - 1; // 0-based
            if ($monthIndex >= 0 && $monthIndex < 12) {
                $salesData[$monthIndex] += (float) $order->total;
                $ordersData[$monthIndex] += 1;
            }
        }

        // Round values
        $salesData = array_map(fn ($val) => round($val, 2), $salesData);

        $totalRevenue = array_sum($salesData);
        $totalOrders  = array_sum($ordersData);

        // Best performing month
        $maxSalesIndex = 0;
        $maxSalesVal   = 0;
        foreach ($salesData as $idx => $val) {
            if ($val > $maxSalesVal) {
                $maxSalesVal   = $val;
                $maxSalesIndex = $idx;
            }
        }
        $topMonth = $maxSalesVal > 0 ? $monthNames[$maxSalesIndex] : $monthNames[now()->month - 1];

        // Daily breakdown for the selected/current month
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $dailyCategories = [];
        $dailySales = [];
        $dailyOrders = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dailyCategories[] = (string) $d;
            $dailySales[]      = 0.0;
            $dailyOrders[]     = 0;
        }

        $monthOrders = Order::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->whereNotIn('status', ['Cancelled'])
            ->get(['id', 'total', 'status', 'created_at']);

        foreach ($monthOrders as $order) {
            $dayIndex = Carbon::parse($order->created_at)->day - 1;
            if ($dayIndex >= 0 && $dayIndex < $daysInMonth) {
                $dailySales[$dayIndex]  += (float) $order->total;
                $dailyOrders[$dayIndex] += 1;
            }
        }
        $dailySales = array_map(fn ($val) => round($val, 2), $dailySales);

        // Available years in database
        $years = Order::pluck('created_at')
            ->map(fn ($d) => Carbon::parse($d)->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        if (empty($years)) {
            $years = [$year];
        }

        return response()->json([
            'year'            => $year,
            'month'           => $month,
            'available_years' => $years,
            'months'          => $monthNames,
            'sales'           => $salesData,
            'orders_count'    => $ordersData,
            'daily'           => [
                'categories'   => $dailyCategories,
                'sales'        => $dailySales,
                'orders_count' => $dailyOrders,
            ],
            'summary'         => [
                'total_revenue'           => (float) round($totalRevenue, 2),
                'total_orders'            => (int) $totalOrders,
                'average_monthly_revenue' => (float) round($totalRevenue / 12, 2),
                'top_month'               => $topMonth,
                'top_month_sales'         => (float) $maxSalesVal,
                'currency'                => 'EGP',
            ],
        ]);
    }
}

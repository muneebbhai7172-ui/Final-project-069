<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $today = date('Y-m-d');
        $thisMonth = date('Y-m');

        $stats = [
            'total_medicines' => Medicine::count(),
            'total_customers' => Customer::count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'Pending')->count(),
            'low_stock_count' => Medicine::where('quantity', '<', 20)->where('quantity', '>', 0)->count(),
            'out_of_stock_count' => Medicine::where('quantity', '<=', 0)->count(),
            'today_sales' => (float) Bill::whereDate('created_at', $today)->sum('total'),
            'today_orders' => Order::whereDate('created_at', $today)->count(),
            'month_sales' => (float) Bill::where('created_at', 'LIKE', "{$thisMonth}%")->sum('total'),
            'month_orders' => Order::where('created_at', 'LIKE', "{$thisMonth}%")->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function recentSales(Request $request)
    {
        $limit = $request->get('limit', 10);
        $bills = Bill::orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $bills
        ]);
    }

    public function lowStock(Request $request)
    {
        $threshold = $request->get('threshold', 20);
        $medicines = Medicine::where('quantity', '<', $threshold)
            ->where('quantity', '>', 0)
            ->orderBy('quantity', 'ASC')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $medicines
        ]);
    }

    public function salesChart(Request $request)
    {
        $days = $request->get('days', 7);
        $sales = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $total = Bill::whereDate('created_at', $date)->sum('total');
            $sales[] = [
                'date' => $date,
                'total' => (float) $total
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $sales
        ]);
    }

    public function topMedicines(Request $request)
    {
        $limit = $request->get('limit', 5);
        $period = $request->get('period', 'month'); // week, month, year

        // Determine date constraint based on period
        if ($period === 'week') {
            $dateConstraint = date('Y-m-d', strtotime('-7 days'));
        } elseif ($period === 'year') {
            $dateConstraint = date('Y-m-d', strtotime('-1 year'));
        } else {
            $dateConstraint = date('Y-m-01'); // First day of current month
        }

        // Get bills within the period
        $bills = Bill::where('created_at', '>=', $dateConstraint)->get();

        // Process items from bills
        $medicineStats = [];
        foreach ($bills as $bill) {
            if (is_array($bill->items)) {
                foreach ($bill->items as $item) {
                    $medicineName = $item['name'] ?? $item['medicine_name'] ?? 'Unknown';
                    $quantity = floatval($item['quantity'] ?? 0);

                    if (!isset($medicineStats[$medicineName])) {
                        $medicineStats[$medicineName] = [
                            'medicine_name' => $medicineName,
                            'total_quantity' => 0,
                            'sales_count' => 0
                        ];
                    }

                    $medicineStats[$medicineName]['total_quantity'] += $quantity;
                    $medicineStats[$medicineName]['sales_count']++;
                }
            }
        }

        // Sort by total quantity and limit
        usort($medicineStats, function($a, $b) {
            return $b['total_quantity'] <=> $a['total_quantity'];
        });

        $topMedicines = array_slice($medicineStats, 0, $limit);

        return response()->json([
            'success' => true,
            'data' => $topMedicines
        ]);
    }
}


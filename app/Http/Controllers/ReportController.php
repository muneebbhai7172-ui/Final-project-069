<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function sales(Request $request)
    {
        $query = Bill::query();

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $bills = $query->orderBy('created_at', 'DESC')->get();

        $summary = [
            'total_sales' => $bills->sum('total'),
            'total_transactions' => $bills->count(),
            'average_sale' => $bills->count() > 0 ? $bills->avg('total') : 0,
            'total_discount' => $bills->sum('discount'),
            'total_tax' => $bills->sum('tax')
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $bills
        ]);
    }

    public function inventory(Request $request)
    {
        $medicines = Medicine::all();

        $summary = [
            'total_items' => $medicines->count(),
            'total_value' => $medicines->sum(function($m) {
                return $m->quantity * $m->price;
            }),
            'low_stock' => $medicines->where('quantity', '<', 20)->where('quantity', '>', 0)->count(),
            'out_of_stock' => $medicines->where('quantity', '<=', 0)->count(),
            'in_stock' => $medicines->where('quantity', '>', 0)->count()
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $medicines
        ]);
    }

    public function customers(Request $request)
    {
        $customers = Customer::all();

        // Calculate orders count manually since the relationship uses phone instead of id
        $customersWithOrders = $customers->map(function($customer) {
            $ordersCount = Order::where('phone', $customer->phone)->count();
            $customer->orders_count = $ordersCount;
            return $customer;
        })->sortByDesc('purchases');

        $summary = [
            'total_customers' => $customers->count(),
            'total_loyalty_points' => $customers->sum('points'),
            'average_points' => $customers->count() > 0 ? $customers->avg('points') : 0
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $customersWithOrders->values()
        ]);
    }

    public function export(Request $request)
    {
        $type = $request->get('type', 'sales');

        switch ($type) {
            case 'sales':
                return $this->exportSales($request);
            case 'inventory':
                return $this->exportInventory($request);
            case 'customers':
                return $this->exportCustomers($request);
            default:
                return response()->json(['success' => false, 'message' => 'Invalid report type'], 400);
        }
    }

    private function exportSales($request)
    {
        $query = Bill::query();

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $bills = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sales_report_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($bills) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Invoice', 'Date', 'Customer', 'Phone', 'Subtotal', 'Discount', 'Tax', 'Total', 'Payment Method']);

            foreach ($bills as $bill) {
                fputcsv($file, [
                    $bill->invoice_number,
                    $bill->created_at->format('Y-m-d H:i:s'),
                    $bill->customer_name,
                    $bill->customer_phone,
                    $bill->subtotal,
                    $bill->discount,
                    $bill->tax,
                    $bill->total,
                    $bill->payment_method
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportInventory($request)
    {
        $medicines = Medicine::all();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="inventory_report_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($medicines) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Category', 'Quantity', 'Price', 'Total Value', 'Status']);

            foreach ($medicines as $medicine) {
                $status = $medicine->quantity <= 0 ? 'Out of Stock' :
                         ($medicine->quantity < 20 ? 'Low Stock' : 'In Stock');

                fputcsv($file, [
                    $medicine->id,
                    $medicine->name,
                    $medicine->category,
                    $medicine->quantity,
                    $medicine->price,
                    $medicine->quantity * $medicine->price,
                    $status
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportCustomers($request)
    {
        $customers = Customer::all();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="customers_report_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($customers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Phone', 'Email', 'Points', 'Purchases', 'Last Purchase']);

            foreach ($customers as $customer) {
                fputcsv($file, [
                    $customer->id,
                    $customer->name,
                    $customer->phone,
                    $customer->email,
                    $customer->points,
                    $customer->purchases,
                    $customer->last_purchase_date
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

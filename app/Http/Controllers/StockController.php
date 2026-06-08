<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $query = Medicine::query();

        if ($request->has('low_stock') && $request->low_stock) {
            $query->where('quantity', '<', 20);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('category', 'LIKE', "%{$search}%");
            });
        }

        $query->orderBy('quantity', 'ASC');

        $medicines = $query->get();

        return response()->json([
            'success' => true,
            'data' => $medicines
        ]);
    }

    public function update(Request $request, $id)
    {
        $medicine = Medicine::find($id);

        if (!$medicine) {
            return response()->json([
                'success' => false,
                'message' => 'Medicine not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:0',
            'action' => 'required|in:set,add,subtract'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        switch ($request->action) {
            case 'set':
                $medicine->quantity = $request->quantity;
                break;
            case 'add':
                $medicine->increment('quantity', $request->quantity);
                break;
            case 'subtract':
                $medicine->decrement('quantity', $request->quantity);
                break;
        }

        $medicine->save();

        return response()->json([
            'success' => true,
            'message' => 'Stock updated successfully',
            'data' => $medicine
        ]);
    }

    public function lowStock(Request $request)
    {
        $threshold = $request->get('threshold', 20);
        $medicines = Medicine::where('quantity', '<', $threshold)
            ->where('quantity', '>', 0)
            ->orderBy('quantity', 'ASC')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $medicines,
            'count' => $medicines->count()
        ]);
    }

    public function export()
    {
        $medicines = Medicine::all();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="stock_report_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($medicines) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Category', 'Stock Quantity', 'Price', 'Status']);

            foreach ($medicines as $medicine) {
                $status = $medicine->quantity <= 0 ? 'Out of Stock' :
                         ($medicine->quantity < 20 ? 'Low Stock' : 'In Stock');

                fputcsv($file, [
                    $medicine->id,
                    $medicine->name,
                    $medicine->category,
                    $medicine->quantity,
                    $medicine->price,
                    $status
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

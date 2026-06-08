<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        $query = Bill::query();

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                  ->orWhere('customer_name', 'LIKE', "%{$search}%")
                  ->orWhere('customer_phone', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'DESC');
        $query->orderBy($sortBy, $sortOrder);

        $limit = $request->get('limit', 50);
        $offset = $request->get('offset', 0);

        $total = $query->count();
        $bills = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $bills,
            'total' => $total
        ]);
    }

    public function show($id)
    {
        $bill = Bill::find($id);

        if (!$bill) {
            return response()->json([
                'success' => false,
                'message' => 'Bill not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $bill
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer' => 'required|array',
            'items' => 'required|array|min:1',
            'subtotal' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $customer = $request->customer;
            $customerType = $customer['type'] ?? 'walk-in';
            $customerId = null;
            $customerName = 'Walk-in Customer';
            $customerPhone = null;
            $customerAddress = null;

            if ($customerType === 'walk-in') {
                $customerName = $customer['name'] ?? 'Walk-in Customer';
                $customerPhone = $customer['phone'] ?? null;
                $customerAddress = $customer['address'] ?? null;
            } else {
                $customerId = $customer['customer_id'] ?? null;
                if ($customerId) {
                    $customerData = Customer::find($customerId);
                    if ($customerData) {
                        $customerName = $customerData->name;
                        $customerPhone = $customerData->phone;
                        $customerAddress = $customerData->address;
                    }
                }
            }

            // Validate stock availability before creating bill
            foreach ($request->items as $item) {
                $medicine = Medicine::where('id', $item['id'])->first();
                if (!$medicine) {
                    throw new \Exception("Medicine with ID {$item['id']} not found");
                }
                if ($medicine->quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$medicine->name}. Available: {$medicine->quantity}, Required: {$item['quantity']}");
                }
            }

            $invoiceNumber = 'INV-' . rand(10000000, 99999999);

            $bill = Bill::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_address' => $customerAddress,
                'customer_type' => $customerType,
                'subtotal' => $request->subtotal,
                'discount' => $request->discount ?? 0,
                'tax' => $request->tax ?? 0,
                'total' => $request->total,
                'items' => json_encode($request->items),
                'payment_method' => $request->payment_method ?? 'cash',
                'payment_status' => 'paid',
                'cashier_id' => 1
            ]);

            // Deduct stock for each item
            foreach ($request->items as $item) {
                Medicine::where('id', $item['id'])
                    ->decrement('quantity', $item['quantity']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bill created successfully',
                'invoice_number' => $invoiceNumber,
                'data' => $bill
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function search(Request $request)
    {
        $query = Bill::query();

        if ($request->has('invoice_number')) {
            $query->where('invoice_number', 'LIKE', "%{$request->invoice_number}%");
        }

        if ($request->has('phone')) {
            $query->where('customer_phone', 'LIKE', "%{$request->phone}%");
        }

        if ($request->has('customer_name')) {
            $query->where('customer_name', 'LIKE', "%{$request->customer_name}%");
        }

        $bills = $query->limit(20)->get();

        return response()->json([
            'success' => true,
            'data' => $bills
        ]);
    }
}

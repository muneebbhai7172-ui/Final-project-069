<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderLog;
use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Display a listing of orders
     */
    public function index(Request $request)
    {
        $query = Order::with('items');

        // Filter by status
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Filter by customer phone
        if ($request->has('phone') && !empty($request->phone)) {
            $query->where('phone', 'LIKE', "%{$request->phone}%");
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('order_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('order_date', '<=', $request->date_to);
        }

        // Search by order number or customer name
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('order_number', 'LIKE', "%{$search}%")
                  ->orWhere('customer_name', 'LIKE', "%{$search}%")
                  ->orWhere('order_id', 'LIKE', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'DESC');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $limit = $request->get('limit', 50);
        $offset = $request->get('offset', 0);

        $total = $query->count();
        $orders = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
            'total' => $total
        ]);
    }

    /**
     * Display the specified order with details
     */
    public function show($id)
    {
        $order = Order::with(['items.medicine', 'logs'])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Format order for frontend
        $formattedOrder = [
            'id' => $order->id,
            'order_id' => $order->order_id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'email' => $order->email,
            'phone' => $order->phone,
            'address' => $order->address,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'payment_method' => $order->payment_method,
            'transaction_id' => $order->transaction_id,
            'notes' => $order->notes,
            'created_at' => $order->created_at,
            'items' => $order->items->map(function ($item) {
                return [
                    'medicine_id' => $item->medicine_id,
                    'medicine_name' => $item->medicine ? $item->medicine->name : 'Unknown',
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'order' => $formattedOrder
        ]);
    }

    /**
     * Create a new order
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'cart_items' => 'required|array|min:1',
            'cart_items.*.medicine_id' => 'required|integer|exists:medicines,id',
            'cart_items.*.quantity' => 'required|integer|min:1',
            'cart_items.*.price' => 'required|numeric|min:0',
            'payment_method' => 'required|string|in:cash_on_delivery,easypaisa,jazzcash,bank_transfer',
            'notes' => 'nullable|string'
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
            $totalAmount = 0;
            $cartItems = $request->cart_items;

            // Validate stock and calculate total
            foreach ($cartItems as $item) {
                $medicine = Medicine::find($item['medicine_id']);

                if (!$medicine) {
                    throw new \Exception('Medicine not found: ID ' . $item['medicine_id']);
                }

                $availableQuantity = $medicine->quantity - $medicine->reserved_quantity;
                if ($availableQuantity < $item['quantity']) {
                    throw new \Exception('Insufficient stock for ' . $medicine->name . '. Available: ' . $availableQuantity . ', Required: ' . $item['quantity']);
                }

                $totalAmount += $item['price'] * $item['quantity'];
            }

            // Generate order ID and number
            $orderId = 'ORD' . date('Ymd') . '_' . strtoupper(substr(uniqid(), -6));
            $orderNumber = 'ORD' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $transactionId = $request->transaction_id ?? 'TXN' . date('YmdHis') . '_' . strtoupper(substr(uniqid(), -6));

            // Create order
            $order = Order::create([
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'customer_name' => $request->customer_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'payment_method' => $request->payment_method,
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
                'transaction_id' => $transactionId,
                'status' => 'Pending'
            ]);

            // Create order items and reserve stock
            foreach ($cartItems as $item) {
                $medicine = Medicine::find($item['medicine_id']);

                OrderItem::create([
                    'order_id' => $order->id,
                    'medicine_id' => $item['medicine_id'],
                    'medicine_name' => $medicine->name,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $item['price'] * $item['quantity'],
                    'transaction_id' => $transactionId
                ]);

                // Reserve stock (don't deduct yet until dispatched)
                $medicine->increment('reserved_quantity', $item['quantity']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'transaction_id' => $transactionId
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update order details
     */
    public function update(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'customer_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string',
            'notes' => 'sometimes|string|nullable',
            'status' => 'sometimes|in:Pending,Confirmed,Preparing,Dispatched,Delivered,Completed,Cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $order->update($request->only(['customer_name', 'email', 'phone', 'address', 'notes', 'status']));

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $order
        ]);
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Pending,Confirmed,Preparing,Dispatched,Delivered,Completed,Cancelled',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldStatus = $order->status;
        $newStatus = $request->status;

        // If dispatching, deduct stock and remove reservation
        if ($newStatus === 'Dispatched' && $oldStatus !== 'Dispatched') {
            foreach ($order->items as $item) {
                $medicine = Medicine::where('id', $item->medicine_id)->first();
                if ($medicine) {
                    // Deduct from actual quantity and remove from reserved quantity
                    $medicine->decrement('quantity', $item->quantity);
                    $medicine->decrement('reserved_quantity', $item->quantity);
                }
            }
        }

        // If cancelling, remove reservation (restore reserved stock to available)
        if ($newStatus === 'Cancelled' && $oldStatus !== 'Cancelled') {
            foreach ($order->items as $item) {
                $medicine = Medicine::where('id', $item->medicine_id)->first();
                if ($medicine) {
                    // If order was previously dispatched, restore actual quantity
                    if ($oldStatus === 'Dispatched') {
                        $medicine->increment('quantity', $item->quantity);
                    } else {
                        // If order was only reserved, remove reservation
                        $medicine->decrement('reserved_quantity', $item->quantity);
                    }
                }
            }
        }

        $order->update(['status' => $newStatus]);

        // Log status change
        OrderLog::create([
            'order_id' => $order->id,
            'status' => $newStatus,
            'notes' => $request->notes ?? "Status changed from {$oldStatus} to {$newStatus} by admin"
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => $order
        ]);
    }

    /**
     * Cancel/delete order
     */
    public function destroy($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        DB::beginTransaction();

        try {
            // Restore stock if not already cancelled
            if ($order->status !== 'Cancelled') {
                foreach ($order->items as $item) {
                    Medicine::where('id', $item->medicine_id)
                        ->increment('quantity', $item->quantity);
                }
            }

            $order->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Track order by order number, order_id, or phone number
     */
    public function track($tracking)
    {
        $order = Order::with(['items.medicine', 'logs'])
            ->where('order_number', $tracking)
            ->orWhere('order_id', $tracking)
            ->orWhere('id', $tracking)
            ->orWhere('phone', $tracking)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => $order
        ]);
    }

    /**
     * Delete all orders
     */
    public function deleteAll()
    {
        try {
            DB::beginTransaction();

            // Delete all order items first
            OrderItem::truncate();

            // Delete all order logs
            OrderLog::truncate();

            // Delete all orders
            Order::truncate();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'All orders deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete orders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get orders for a specific customer by email
     */
    public function customerOrders($email)
    {
        $orders = Order::with('items.medicine')
            ->where('email', $email)
            ->orderBy('created_at', 'DESC')
            ->get();

        // Format orders for frontend
        $formattedOrders = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'order_id' => $order->order_id,
                'order_number' => $order->order_number,
                'tracking_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'email' => $order->email,
                'phone' => $order->phone,
                'address' => $order->address,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'payment_method' => $order->payment_method,
                'created_at' => $order->created_at,
                'items_count' => $order->items->count(),
                'items' => $order->items->map(function ($item) {
                    return [
                        'medicine_id' => $item->medicine_id,
                        'medicine_name' => $item->medicine ? $item->medicine->name : 'Unknown',
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->subtotal
                    ];
                })
            ];
        });

        return response()->json([
            'success' => true,
            'orders' => $formattedOrders
        ]);
    }
}

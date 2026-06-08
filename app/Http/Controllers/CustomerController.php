<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers with search and filters
     */
    public function index(Request $request)
    {
        $query = Customer::query();

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Filter by name
        if ($request->has('name') && !empty($request->name)) {
            $query->where('name', 'LIKE', "%{$request->name}%");
        }

        // Filter by phone
        if ($request->has('phone') && !empty($request->phone)) {
            $query->where('phone', 'LIKE', "%{$request->phone}%");
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'ASC');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $limit = $request->get('limit', 100);
        $offset = $request->get('offset', 0);

        $total = $query->count();
        $customers = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'success' => true,
            'customers' => $customers,
            'total' => $total
        ]);
    }

    /**
     * Find customer by phone (for POS)
     */
    public function findByPhone($phone)
    {
        $customer = Customer::where('phone', $phone)->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'points' => $customer->points
            ]
        ]);
    }

    /**
     * Display the specified customer
     */
    public function show($id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        return response()->json($customer);
    }

    /**
     * Store a newly created customer
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:customers,phone',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'points' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer = Customer::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'points' => $request->points ?? 0
        ]);

        return response()->json([
            'success' => true,
            'id' => $customer->id,
            'message' => 'Customer created successfully'
        ], 201);
    }

    /**
     * Update the specified customer
     */
    public function update(Request $request, $id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20|unique:customers,phone,' . $id,
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'points' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => $customer
        ]);
    }

    /**
     * Remove the specified customer
     */
    public function destroy($id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully'
        ]);
    }

    /**
     * Export customers to CSV
     */
    public function export()
    {
        $customers = Customer::all();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="customers_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($customers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Phone', 'Email', 'Address', 'Points', 'Purchases', 'Last Purchase Date']);

            foreach ($customers as $customer) {
                fputcsv($file, [
                    $customer->id,
                    $customer->name,
                    $customer->phone,
                    $customer->email,
                    $customer->address,
                    $customer->points,
                    $customer->purchases,
                    $customer->last_purchase_date
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Bulk import customers from CSV
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file type',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $data = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_shift($data);

        $imported = 0;
        $errors = [];

        foreach ($data as $index => $row) {
            try {
                // Check if phone already exists
                if (Customer::where('phone', $row[1])->exists()) {
                    $errors[] = "Row " . ($index + 2) . ": Phone number already exists";
                    continue;
                }

                Customer::create([
                    'name' => $row[0] ?? '',
                    'phone' => $row[1] ?? '',
                    'email' => $row[2] ?? null,
                    'address' => $row[3] ?? null,
                    'points' => $row[4] ?? 0
                ]);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Imported {$imported} customers",
            'imported' => $imported,
            'errors' => $errors
        ]);
    }

    /**
     * Delete all customers
     */
    public function deleteAll()
    {
        try {
            $count = Customer::count();
            Customer::truncate(); // Much faster than deleting one by one

            return response()->json([
                'success' => true,
                'message' => "Deleted {$count} customers successfully",
                'deleted_count' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import customers from JSON data (for Excel uploads)
     */
    public function importJson(Request $request)
    {
        $customers = $request->input('customers', []);
        $imported = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach ($customers as $index => $customerData) {
            try {
                // Map Excel column names to database fields
                $name = $customerData['Name'] ?? $customerData['name'] ?? '';
                $phone = $customerData['Phone'] ?? $customerData['phone'] ?? '';
                $email = $customerData['Email'] ?? $customerData['email'] ?? null;
                $address = $customerData['Address'] ?? $customerData['address'] ?? null;
                $points = $customerData['Points'] ?? $customerData['points'] ?? 0;

                if (empty($name) || empty($phone)) {
                    $failed++;
                    $errors[] = ['row' => $index + 1, 'message' => 'Name and phone are required'];
                    continue;
                }

                // Check if customer already exists
                $existingCustomer = Customer::where('phone', $phone)->first();

                if ($existingCustomer) {
                    // Update existing customer
                    $existingCustomer->update([
                        'name' => $name,
                        'email' => $email,
                        'address' => $address,
                        'points' => $points
                    ]);
                    $updated++;
                } else {
                    // Create new customer
                    Customer::create([
                        'name' => $name,
                        'phone' => $phone,
                        'email' => $email,
                        'address' => $address,
                        'points' => $points
                    ]);
                    $imported++;
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = ['row' => $index + 1, 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Import completed: {$imported} imported, {$updated} updated, {$failed} failed",
            'imported' => $imported,
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors
        ]);
    }
}

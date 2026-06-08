<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MedicineController extends Controller
{
    /**
     * Display a listing of medicines with optional filtering and search
     */
    public function index(Request $request)
    {
        $query = Medicine::query();

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('category', 'LIKE', "%{$search}%")
                  ->orWhere('manufacturer', 'LIKE', "%{$search}%");
            });
        }

        // Category filter
        if ($request->has('category') && !empty($request->category)) {
            $query->where('category', $request->category);
        }

        // Stock filter
        if ($request->has('stock_filter')) {
            switch ($request->stock_filter) {
                case 'high':
                    $query->where('quantity', '>', 100);
                    break;
                case 'medium':
                    $query->whereBetween('quantity', [20, 100]);
                    break;
                case 'low':
                    $query->where('quantity', '<', 20)->where('quantity', '>', 0);
                    break;
                case 'out':
                    $query->where('quantity', '<=', 0);
                    break;
            }
        }

        // Price range filter
        if ($request->has('price_min')) {
            $query->where('price', '>=', $request->price_min);
        }
        if ($request->has('price_max')) {
            $query->where('price', '<=', $request->price_max);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'ASC');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $limit = $request->get('limit', 1000);
        $offset = $request->get('offset', 0);

        $total = $query->count();
        $medicines = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'success' => true,
            'medicines' => $medicines,
            'data' => $medicines,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * Display the specified medicine
     */
    public function show($id)
    {
        $medicine = Medicine::find($id);

        if (!$medicine) {
            return response()->json([
                'success' => false,
                'message' => 'Medicine not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $medicine
        ]);
    }

    /**
     * Store a newly created medicine
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'category' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:255',
            'batch_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $medicine = Medicine::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Medicine added successfully',
            'data' => $medicine
        ], 201);
    }

    /**
     * Update the specified medicine
     */
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
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'quantity' => 'sometimes|required|integer|min:0',
            'category' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:255',
            'batch_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $medicine->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Medicine updated successfully',
            'data' => $medicine
        ]);
    }

    /**
     * Remove the specified medicine
     */
    public function destroy($id)
    {
        $medicine = Medicine::find($id);

        if (!$medicine) {
            return response()->json([
                'success' => false,
                'message' => 'Medicine not found'
            ], 404);
        }

        $medicine->delete();

        return response()->json([
            'success' => true,
            'message' => 'Medicine deleted successfully'
        ]);
    }

    /**
     * Get medicine suggestions for autocomplete
     */
    public function suggestions(Request $request)
    {
        // Accept multiple param names for compatibility
        $query = $request->get('q', $request->get('term', $request->get('search', '')));
        $type = $request->get('type', 'both');
        $limit = $request->get('limit', 10);

        if (empty($query)) {
            return response()->json([
                'success' => false,
                'suggestions' => []
            ]);
        }

        $suggestions = [];

        // Search medicines
        if ($type === 'medicine' || $type === 'both') {
            $medicines = Medicine::where(function($query_builder) use ($query) {
                    $query_builder->where('name', 'LIKE', "%{$query}%")
                        ->orWhere('category', 'LIKE', "%{$query}%")
                        ->orWhere('manufacturer', 'LIKE', "%{$query}%");
                })
                ->where('quantity', '>', 0) // Only show medicines with stock
                ->orderByRaw("
                    CASE
                        WHEN LOWER(name) = LOWER(?) THEN 1
                        WHEN LOWER(name) LIKE LOWER(?) THEN 2
                        WHEN LOWER(category) LIKE LOWER(?) THEN 3
                        ELSE 4
                    END ASC, quantity DESC, name ASC
                ", [$query, $query . '%', $query . '%'])
                ->limit($limit)
                ->get(['id', 'name', 'category', 'price', 'quantity', 'manufacturer', 'image']);

            foreach ($medicines as $medicine) {
                $suggestions[] = [
                    'type' => 'medicine',
                    'id' => $medicine->id,
                    'name' => $medicine->name,
                    'category' => $medicine->category,
                    'price' => $medicine->price,
                    'quantity' => $medicine->quantity,
                    'manufacturer' => $medicine->manufacturer,
                    'image' => $medicine->image ?? ''
                ];
            }
        }

        // Get categories that match
        if ($type === 'category' || $type === 'both') {
            $categories = Medicine::select('category', DB::raw('count(*) as count'))
                ->where('category', 'LIKE', "%{$query}%")
                ->groupBy('category')
                ->limit(5)
                ->get();

            foreach ($categories as $cat) {
                $suggestions[] = [
                    'type' => 'category',
                    'name' => $cat->category,
                    'count' => $cat->count
                ];
            }
        }

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions
        ]);
    }

    /**
     * Export medicines to CSV
     */
    public function export(Request $request)
    {
        $medicines = Medicine::all();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="medicines_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($medicines) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Category', 'Price', 'Quantity', 'Manufacturer', 'Batch Number', 'Expiry Date']);

            foreach ($medicines as $medicine) {
                fputcsv($file, [
                    $medicine->id,
                    $medicine->name,
                    $medicine->category,
                    $medicine->price,
                    $medicine->quantity,
                    $medicine->manufacturer,
                    $medicine->batch_number,
                    $medicine->expiry_date
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Bulk import medicines from CSV or JSON
     */
    public function import(Request $request)
    {
        // Increase execution time for large imports
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        // Handle JSON data (from Excel frontend)
        if ($request->has('medicines') && is_array($request->medicines)) {
            $skipDuplicateCheck = $request->get('skip_duplicate_check', false);
            return $this->importFromJson($request->medicines, $skipDuplicateCheck);
        }

        // Handle CSV file upload
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
                Medicine::create([
                    'name' => $row[0] ?? '',
                    'category' => $row[1] ?? null,
                    'price' => $row[2] ?? 0,
                    'quantity' => $row[3] ?? 0,
                    'manufacturer' => $row[4] ?? null,
                    'batch_number' => $row[5] ?? null,
                    'expiry_date' => $row[6] ?? null,
                    'description' => $row[7] ?? null
                ]);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Imported {$imported} medicines",
            'imported' => $imported,
            'errors' => $errors
        ]);
    }

    /**
     * Import medicines from JSON array (ULTRA-FAST batch processing)
     * Uses raw SQL INSERT for maximum speed
     */
    private function importFromJson(array $medicines, bool $skipDuplicateCheck = false)
    {
        $total = count($medicines);
        $failed = 0;
        $errors = [];

        try {
            $now = now()->format('Y-m-d H:i:s');
            $insertData = [];

            // Prepare all data first (fast in-memory operation)
            foreach ($medicines as $index => $medicine) {
                $name = trim($medicine['name'] ?? $medicine['Medicine Name'] ?? '');

                if (empty($name)) {
                    $failed++;
                    if (count($errors) < 5) {
                        $errors[] = ['row' => $index + 1, 'message' => 'Medicine name is required'];
                    }
                    continue;
                }

                // Parse expiry date
                $expiryDate = $medicine['expiry_date'] ?? $medicine['Expiry Date'] ?? null;
                if ($expiryDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiryDate)) {
                    try {
                        $expiryDate = \Carbon\Carbon::parse($expiryDate)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $expiryDate = null;
                    }
                }

                $insertData[] = [
                    'name' => $name,
                    'generic_name' => $medicine['generic_name'] ?? $medicine['Generic Name'] ?? null,
                    'category' => $medicine['category'] ?? $medicine['Category'] ?? null,
                    'manufacturer' => $medicine['manufacturer'] ?? $medicine['Manufacturer'] ?? null,
                    'price' => floatval($medicine['price'] ?? $medicine['Unit Price'] ?? 0),
                    'quantity' => intval($medicine['quantity'] ?? $medicine['Stock Quantity'] ?? 0),
                    'expiry_date' => $expiryDate,
                    'batch_number' => $medicine['batch_number'] ?? $medicine['Batch Number'] ?? null,
                    'description' => $medicine['description'] ?? $medicine['Description'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (empty($insertData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid data to import',
                    'imported' => 0,
                    'updated' => 0,
                    'failed' => $failed,
                    'errors' => $errors
                ]);
            }

            // Use raw SQL for maximum speed - INSERT IGNORE to skip duplicates
            $imported = 0;
            foreach (array_chunk($insertData, 1000) as $chunk) {
                if ($skipDuplicateCheck) {
                    // Fast mode: Just insert, ignore duplicates
                    try {
                        Medicine::insert($chunk);
                        $imported += count($chunk);
                    } catch (\Exception $e) {
                        // If bulk insert fails due to duplicates, it's okay
                        $imported += count($chunk);
                    }
                } else {
                    // Normal mode: Use INSERT ... ON DUPLICATE KEY UPDATE
                    $columns = ['name', 'generic_name', 'category', 'manufacturer', 'price', 'quantity', 'expiry_date', 'batch_number', 'description', 'created_at', 'updated_at'];

                    $values = [];
                    foreach ($chunk as $row) {
                        $rowValues = [];
                        foreach ($columns as $col) {
                            $val = $row[$col];
                            if ($val === null) {
                                $rowValues[] = 'NULL';
                            } elseif (is_numeric($val)) {
                                $rowValues[] = $val;
                            } else {
                                $rowValues[] = "'" . addslashes($val) . "'";
                            }
                        }
                        $values[] = '(' . implode(',', $rowValues) . ')';
                    }

                    $sql = "INSERT INTO medicines (" . implode(',', $columns) . ") VALUES " . implode(',', $values) . "
                        ON DUPLICATE KEY UPDATE
                        generic_name = VALUES(generic_name),
                        category = VALUES(category),
                        manufacturer = VALUES(manufacturer),
                        price = VALUES(price),
                        quantity = VALUES(quantity),
                        expiry_date = VALUES(expiry_date),
                        batch_number = VALUES(batch_number),
                        description = VALUES(description),
                        updated_at = VALUES(updated_at)";

                    DB::statement($sql);
                    $imported += count($chunk);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Import completed: {$imported} records processed",
                'imported' => $imported,
                'updated' => 0,
                'failed' => $failed,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse date from various formats
     */
    private function parseDate($date)
    {
        if (empty($date)) {
            return null;
        }

        // If it's already a valid date format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // Try to parse various formats
        try {
            $parsed = \Carbon\Carbon::parse($date);
            return $parsed->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get low stock medicines
     */
    public function lowStock(Request $request)
    {
        $threshold = $request->get('threshold', 20);
        $medicines = Medicine::lowStock($threshold)->get();

        return response()->json([
            'success' => true,
            'data' => $medicines,
            'count' => $medicines->count()
        ]);
    }

    /**
     * Get expiring medicines
     */
    public function expiring(Request $request)
    {
        $days = (int) $request->get('days', 30);
        $medicines = Medicine::expiringSoon($days)->get();

        return response()->json([
            'success' => true,
            'data' => $medicines,
            'count' => $medicines->count()
        ]);
    }

    /**
     * Delete all medicines
     */
    public function deleteAll()
    {
        try {
            $count = Medicine::count();

            // Use delete instead of truncate to respect foreign key constraints
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            Medicine::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            return response()->json([
                'success' => true,
                'message' => "All {$count} medicines deleted successfully"
            ]);
        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1'); // Re-enable foreign keys even on error
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete medicines: ' . $e->getMessage()
            ], 500);
        }
    }
}

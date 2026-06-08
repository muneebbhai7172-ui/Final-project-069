<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function create(Request $request)
    {
        try {
            $database = env('DB_DATABASE');
            $username = env('DB_USERNAME');
            $password = env('DB_PASSWORD');
            $host = env('DB_HOST');

            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backupPath = storage_path('app/backups/' . $filename);

            if (!file_exists(storage_path('app/backups'))) {
                mkdir(storage_path('app/backups'), 0755, true);
            }

            // Try to find mysqldump - check common XAMPP locations on Windows
            $mysqldumpPaths = [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\xampp\\mysql\\bin\\mysqldump',
                'D:\\xampp\\mysql\\bin\\mysqldump.exe',
                '/usr/bin/mysqldump',
                '/usr/local/mysql/bin/mysqldump',
                'mysqldump' // Fallback to PATH
            ];

            $mysqldump = 'mysqldump';
            foreach ($mysqldumpPaths as $path) {
                if (file_exists($path)) {
                    $mysqldump = $path;
                    break;
                }
            }

            // Build command - handle empty password case
            if (empty($password)) {
                $command = sprintf(
                    '"%s" -h%s -u%s %s > "%s" 2>&1',
                    $mysqldump,
                    $host,
                    $username,
                    $database,
                    $backupPath
                );
            } else {
                $command = sprintf(
                    '"%s" -h%s -u%s -p%s %s > "%s" 2>&1',
                    $mysqldump,
                    $host,
                    $username,
                    $password,
                    $database,
                    $backupPath
                );
            }

            exec($command, $output, $returnVar);

            // Check if backup file was created and has content
            if ($returnVar !== 0 || !file_exists($backupPath) || filesize($backupPath) === 0) {
                // Try alternative PHP-based backup
                return $this->createPHPBackup($database, $backupPath, $filename);
            }

            return response()->json([
                'success' => true,
                'message' => 'Backup created successfully',
                'filename' => $filename,
                'size' => filesize($backupPath)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create backup using PHP when mysqldump is not available
     */
    private function createPHPBackup($database, $backupPath, $filename)
    {
        try {
            $tables = DB::select('SHOW TABLES');
            $tableKey = 'Tables_in_' . $database;

            $sql = "-- Database Backup\n";
            $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- Database: {$database}\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $table) {
                $tableName = $table->$tableKey;

                // Get create table statement
                $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
                $sql .= "-- Table: {$tableName}\n";
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= $createTable[0]->{'Create Table'} . ";\n\n";

                // Get table data
                $rows = DB::table($tableName)->get();
                if ($rows->count() > 0) {
                    $columns = array_keys((array)$rows->first());
                    $columnList = '`' . implode('`, `', $columns) . '`';

                    foreach ($rows as $row) {
                        $values = array_map(function($value) {
                            if (is_null($value)) return 'NULL';
                            return "'" . addslashes($value) . "'";
                        }, (array)$row);

                        $sql .= "INSERT INTO `{$tableName}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sql .= "\n";
                }
            }

            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

            file_put_contents($backupPath, $sql);

            return response()->json([
                'success' => true,
                'message' => 'Backup created successfully (PHP method)',
                'filename' => $filename,
                'size' => filesize($backupPath)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'PHP backup failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function list()
    {
        $backupPath = storage_path('app/backups');

        if (!file_exists($backupPath)) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $files = scandir($backupPath);
        $backups = [];

        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $filePath = $backupPath . '/' . $file;
                $backups[] = [
                    'filename' => $file,
                    'size' => filesize($filePath),
                    'created_at' => date('Y-m-d H:i:s', filemtime($filePath))
                ];
            }
        }

        usort($backups, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return response()->json([
            'success' => true,
            'data' => $backups
        ]);
    }

    public function download($filename)
    {
        $filePath = storage_path('app/backups/' . $filename);

        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Backup file not found'
            ], 404);
        }

        return response()->download($filePath);
    }

    public function delete($filename)
    {
        $filePath = storage_path('app/backups/' . $filename);

        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Backup file not found'
            ], 404);
        }

        unlink($filePath);

        return response()->json([
            'success' => true,
            'message' => 'Backup deleted successfully'
        ]);
    }
}

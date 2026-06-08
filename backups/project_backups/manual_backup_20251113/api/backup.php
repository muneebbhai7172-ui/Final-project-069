<?php
header('Content-Type: application/json');
include '../includes/db_connect.php';

$action = $_POST['action'] ?? '';

if ($action === 'backup') {
    try {
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }

        $sqlScript = "-- PharmaCare Database Backup\n";
        $sqlScript .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $sqlScript .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $sqlScript .= "-- Table: $table\n";
            $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
            
            // Get create table statement
            $createResult = $conn->query("SHOW CREATE TABLE `$table`");
            $createRow = $createResult->fetch_array();
            $sqlScript .= $createRow[1] . ";\n\n";
            
            // Get table data
            $dataResult = $conn->query("SELECT * FROM `$table`");
            if ($dataResult->num_rows > 0) {
                while ($dataRow = $dataResult->fetch_assoc()) {
                    $sqlScript .= "INSERT INTO `$table` VALUES (";
                    $values = [];
                    foreach ($dataRow as $value) {
                        $values[] = $value === null ? 'NULL' : "'" . $conn->real_escape_string($value) . "'";
                    }
                    $sqlScript .= implode(', ', $values) . ");\n";
                }
                $sqlScript .= "\n";
            }
        }

        $sqlScript .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        $filename = 'backup_' . date('Y-m-d_His') . '.sql';
        $filepath = '../backups/' . $filename;
        
        // Create backups directory if it doesn't exist
        if (!file_exists('../backups')) {
            mkdir('../backups', 0777, true);
        }
        
        file_put_contents($filepath, $sqlScript);
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully',
            'filename' => $filename,
            'size' => filesize($filepath),
            'path' => $filepath
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Backup failed: ' . $e->getMessage()
        ]);
    }
} elseif ($action === 'restore') {
    // Restore functionality (to be implemented)
    echo json_encode([
        'success' => true,
        'message' => 'Restore functionality coming soon'
    ]);
} elseif ($action === 'list') {
    $backupDir = '../backups/';
    $backups = [];
    
    if (is_dir($backupDir)) {
        $files = scandir($backupDir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $backups[] = [
                    'filename' => $file,
                    'size' => filesize($backupDir . $file),
                    'date' => date('Y-m-d H:i:s', filemtime($backupDir . $file))
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'backups' => $backups
    ]);
} elseif ($action === 'delete') {
    $filename = $_POST['filename'] ?? '';
    
    if (empty($filename)) {
        echo json_encode([
            'success' => false,
            'message' => 'Filename is required'
        ]);
        exit;
    }
    
    // Security: Only allow deleting .sql files and prevent directory traversal
    if (pathinfo($filename, PATHINFO_EXTENSION) !== 'sql' || strpos($filename, '..') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid filename'
        ]);
        exit;
    }
    
    $filepath = '../backups/' . $filename;
    
    if (file_exists($filepath)) {
        if (unlink($filepath)) {
            echo json_encode([
                'success' => true,
                'message' => 'Backup deleted successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete backup file'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Backup file not found'
        ]);
    }
} elseif ($action === 'save_schedule') {
    $frequency = $_POST['frequency'] ?? 'none';
    $time = $_POST['time'] ?? '02:00';
    $day = $_POST['day'] ?? '0';
    $date = $_POST['date'] ?? '1';
    $keep = $_POST['keep'] ?? '7';
    
    $schedule = [
        'frequency' => $frequency,
        'time' => $time,
        'day' => $day,
        'date' => $date,
        'keep' => $keep,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Save schedule to a JSON file
    $scheduleFile = '../config/backup_schedule.json';
    
    // Create config directory if it doesn't exist
    if (!file_exists('../config')) {
        mkdir('../config', 0777, true);
    }
    
    if (file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT))) {
        echo json_encode([
            'success' => true,
            'message' => 'Schedule saved successfully',
            'schedule' => $schedule
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save schedule'
        ]);
    }
} elseif ($action === 'get_schedule') {
    $scheduleFile = '../config/backup_schedule.json';
    
    if (file_exists($scheduleFile)) {
        $schedule = json_decode(file_get_contents($scheduleFile), true);
        echo json_encode([
            'success' => true,
            'schedule' => $schedule
        ]);
    } else {
        // Return default schedule
        echo json_encode([
            'success' => true,
            'schedule' => [
                'frequency' => 'none',
                'time' => '02:00',
                'day' => '0',
                'date' => '1',
                'keep' => '7'
            ]
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
}

$conn->close();
?>

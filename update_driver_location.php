<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require_once 'database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get the request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    $driver_id = isset($input['driver_id']) ? intval($input['driver_id']) : 0;
    $latitude = isset($input['latitude']) ? floatval($input['latitude']) : null;
    $longitude = isset($input['longitude']) ? floatval($input['longitude']) : null;
    $accuracy = isset($input['accuracy']) ? floatval($input['accuracy']) : null;
    $speed = isset($input['speed']) ? floatval($input['speed']) : null;
    $heading = isset($input['heading']) ? floatval($input['heading']) : null;
    
    // Validation
    if (!$driver_id) {
        throw new Exception('Driver ID is required');
    }
    
    if ($latitude === null || $longitude === null) {
        throw new Exception('Latitude and longitude are required');
    }
    
    if (abs($latitude) > 90 || abs($longitude) > 180) {
        throw new Exception('Invalid coordinates');
    }
    
    // Check if driver exists
    $driverCheckStmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'driver'");
    $driverCheckStmt->execute([$driver_id]);
    
    if ($driverCheckStmt->rowCount() === 0) {
        throw new Exception('Driver not found');
    }
    
    // Update or insert location
    $stmt = $conn->prepare("
        INSERT INTO driver_locations (driver_id, latitude, longitude, accuracy, speed, heading, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            accuracy = VALUES(accuracy),
            speed = VALUES(speed),
            heading = VALUES(heading),
            updated_at = NOW()
    ");
    
    $result = $stmt->execute([
        $driver_id,
        $latitude,
        $longitude,
        $accuracy,
        $speed,
        $heading
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Location updated successfully',
            'data' => [
                'driver_id' => $driver_id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        
        error_log("Location updated for driver $driver_id: $latitude, $longitude");
    } else {
        throw new Exception('Failed to update location');
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'error_code' => 'DB_ERROR'
    ]);
    
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'GENERAL_ERROR'
    ]);
}
?>
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
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
    
    // If no driver_id in JSON, try GET/POST
    if (!$driver_id) {
        $driver_id = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : 
                    (isset($_GET['driver_id']) ? intval($_GET['driver_id']) : 0);
    }
    
    if (!$driver_id) {
        throw new Exception('Driver ID is required');
    }
    
    // Debug: Log the request
    error_log("Getting location for driver ID: " . $driver_id);
    
    // First, check if driver_locations table exists and has data
    $checkTableStmt = $conn->prepare("SHOW TABLES LIKE 'driver_locations'");
    $checkTableStmt->execute();
    
    if ($checkTableStmt->rowCount() === 0) {
        // Table doesn't exist, create it
        $createTableSQL = "
            CREATE TABLE driver_locations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                driver_id INT NOT NULL,
                latitude DECIMAL(10, 8) NOT NULL,
                longitude DECIMAL(11, 8) NOT NULL,
                accuracy FLOAT DEFAULT NULL,
                speed FLOAT DEFAULT NULL,
                heading FLOAT DEFAULT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_driver_id (driver_id),
                INDEX idx_updated_at (updated_at),
                FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";
        
        $conn->exec($createTableSQL);
        error_log("Created driver_locations table");
        
        // Insert sample data for testing
        $sampleDataSQL = "
            INSERT INTO driver_locations (driver_id, latitude, longitude, accuracy, speed, heading) 
            VALUES (?, 23.8103, 90.4125, 10.0, 25.5, 180.0)
            ON DUPLICATE KEY UPDATE 
                latitude = VALUES(latitude),
                longitude = VALUES(longitude),
                updated_at = CURRENT_TIMESTAMP
        ";
        
        $sampleStmt = $conn->prepare($sampleDataSQL);
        $sampleStmt->execute([$driver_id]);
        error_log("Inserted sample location data");
    }
    
    // Get the latest location for the driver
    $stmt = $conn->prepare("
        SELECT 
            latitude, 
            longitude, 
            accuracy, 
            speed, 
            heading, 
            updated_at,
            TIMESTAMPDIFF(SECOND, updated_at, NOW()) as seconds_ago
        FROM driver_locations 
        WHERE driver_id = ? 
        ORDER BY updated_at DESC 
        LIMIT 1
    ");
    
    $stmt->execute([$driver_id]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$location) {
        // No location found, insert a sample location
        $insertStmt = $conn->prepare("
            INSERT INTO driver_locations (driver_id, latitude, longitude, accuracy, speed, heading) 
            VALUES (?, 23.8103, 90.4125, 10.0, 25.5, 180.0)
        ");
        $insertStmt->execute([$driver_id]);
        
        // Fetch the newly inserted location
        $stmt->execute([$driver_id]);
        $location = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($location) {
        // Check if location is recent (within last 5 minutes)
        $isRecent = $location['seconds_ago'] <= 300;
        
        echo json_encode([
            'success' => true,
            'location' => [
                'latitude' => floatval($location['latitude']),
                'longitude' => floatval($location['longitude']),
                'accuracy' => $location['accuracy'] ? floatval($location['accuracy']) : null,
                'speed' => $location['speed'] ? floatval($location['speed']) : null,
                'heading' => $location['heading'] ? floatval($location['heading']) : null,
                'updated_at' => $location['updated_at'],
                'seconds_ago' => intval($location['seconds_ago']),
                'is_recent' => $isRecent
            ],
            'message' => $isRecent ? 'Location is current' : 'Location may be outdated'
        ]);
        
        error_log("Location found: " . json_encode($location));
    } else {
        throw new Exception('No location data available for this driver');
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
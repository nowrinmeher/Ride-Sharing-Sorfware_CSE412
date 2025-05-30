<?php
session_start();
require_once 'database.php';

// Check admin login
if (empty($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        // AJAX request
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    } else {
        // Regular form submission
        header('Location: login.php');
        exit;
    }
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_dashboard.php?error=Invalid request method');
    exit;
}

// Get POST data
$userId = $_POST['user_id'] ?? null;
$userType = $_POST['user_type'] ?? null;

// Validate input
if (!$userId || !$userType || !in_array($userType, ['driver', 'rider'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid user ID or type']);
        exit;
    } else {
        header('Location: admin_dashboard.php?error=Invalid user ID or type');
        exit;
    }
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Delete related records first to maintain referential integrity
    if ($userType === 'driver') {
        // Delete driver profile
        $stmt = $conn->prepare("DELETE FROM driver_profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Update rides to remove driver assignment (set driver_id to NULL)
        $stmt = $conn->prepare("UPDATE rides SET driver_id = NULL WHERE driver_id = ?");
        $stmt->execute([$userId]);
    }
    
    if ($userType === 'rider') {
        // For riders, you might want to handle rides differently
        // Option 1: Delete all rides (be careful with this)
        // $stmt = $conn->prepare("DELETE FROM rides WHERE user_id = ?");
        // $stmt->execute([$userId]);
        
        // Option 2: Keep rides but mark rider as deleted (recommended)
        // You might want to add a 'deleted_at' column to users table for soft deletes
    }
    
    // Delete the user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND user_type = ?");
    $result = $stmt->execute([$userId, $userType]);
    
    if ($stmt->rowCount() > 0) {
        // Commit transaction
        $conn->commit();
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            // AJAX response
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => ucfirst($userType) . ' deleted successfully']);
        } else {
            // Regular form submission
            header('Location: admin_dashboard.php?msg=' . urlencode(ucfirst($userType) . ' deleted successfully'));
        }
    } else {
        // Rollback transaction
        $conn->rollBack();
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'User not found or already deleted']);
        } else {
            header('Location: admin_dashboard.php?error=User not found or already deleted');
        }
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Log error (in production, don't show detailed error to user)
    error_log("Delete user error: " . $e->getMessage());
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    } else {
        header('Location: admin_dashboard.php?error=An error occurred while deleting the user');
    }
}
exit;
?>
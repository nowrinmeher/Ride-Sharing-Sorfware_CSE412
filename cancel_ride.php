<?php
session_start();
require_once 'database.php';

// Only allow logged-in riders
if (empty($_SESSION['user_id']) || $_SESSION['user_type'] !== 'rider') {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: rider_dashboard.php');
    exit;
}

$rideId = (int)$_GET['id'];
$riderId = $_SESSION['user_id'];

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check if ride belongs to this rider and is cancellable
    $stmt = $conn->prepare("SELECT status FROM rides WHERE id = :ride_id AND user_id = :rider_id LIMIT 1");
    $stmt->bindValue(':ride_id', $rideId, PDO::PARAM_INT);
    $stmt->bindValue(':rider_id', $riderId, PDO::PARAM_INT);
    $stmt->execute();
    $ride = $stmt->fetch();

    if (!$ride || !in_array($ride['status'], ['requested', 'accepted'])) {
        // Can't cancel rides that are not in requested or accepted status
        header('Location: rider_dashboard.php?error=Cannot cancel this ride');
        exit;
    }

    // Update ride status to 'cancelled'
    $update = $conn->prepare("UPDATE rides SET status = 'cancelled' WHERE id = :ride_id");
    $update->bindValue(':ride_id', $rideId, PDO::PARAM_INT);
    $update->execute();

    header('Location: rider_dashboard.php?success=Ride cancelled successfully');
    exit;
} catch (Exception $e) {
    error_log("Error cancelling ride: " . $e->getMessage());
    header('Location: rider_dashboard.php?error=Failed to cancel ride');
    exit;
}

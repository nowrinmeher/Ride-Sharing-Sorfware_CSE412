<?php
session_start();
require_once 'database.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_type'] !== 'rider') {
    header('Location: login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$riderId = $_SESSION['user_id'];
$riderName = $_SESSION['user_name'] ?? 'Rider';

$errors = [];
$success = '';

// 1) Handle rider profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $full_name = $first_name . ' ' . $last_name;
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$first_name || !$last_name || !$email || !$phone) {
        $errors[] = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        try {
            // Check for duplicate email or phone excluding current user
            $stmt = $conn->prepare("SELECT id FROM users WHERE (email = :email OR phone = :phone) AND id != :id LIMIT 1");
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':phone', $phone);
            $stmt->bindValue(':id', $riderId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $errors[] = "Email or phone already used by another account.";
            } else {
                // Update users table
                $updateFields = "full_name = :full_name, email = :email, phone = :phone";
                $params = [
                    ':full_name' => $full_name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':id' => $riderId
                ];

                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $updateFields .= ", password = :password";
                    $params[':password'] = $hashedPassword;
                }

                $query = "UPDATE users SET $updateFields WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->execute($params);

                $_SESSION['user_name'] = $full_name;
                $riderName = $full_name;
                $success = "Profile updated successfully!";
            }
        } catch (PDOException $e) {
            $errors[] = "DB error: " . $e->getMessage();
        }
    }
}

// 2) Handle ride booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_ride'])) {
    $pickup_location = trim($_POST['pickup_location'] ?? '');
    $dropoff_location = trim($_POST['dropoff_location'] ?? '');
    $ride_type = trim($_POST['ride_type'] ?? '');
    $pickup_latitude = floatval($_POST['pickup_latitude'] ?? 0);
    $pickup_longitude = floatval($_POST['pickup_longitude'] ?? 0);
    $dropoff_latitude = floatval($_POST['dropoff_latitude'] ?? 0);
    $dropoff_longitude = floatval($_POST['dropoff_longitude'] ?? 0);
    $estimated_price = floatval($_POST['estimated_price'] ?? 0);

    if (!$pickup_location || !$dropoff_location || !$ride_type) {
        $errors[] = "Please fill all ride booking fields.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO rides (user_id, pickup_location, dropoff_location, ride_type, pickup_latitude, pickup_longitude, dropoff_latitude, dropoff_longitude, estimated_price, status) VALUES (:user_id, :pickup, :dropoff, :ride_type, :pickup_lat, :pickup_lng, :dropoff_lat, :dropoff_lng, :price, 'requested')");
            $stmt->bindValue(':user_id', $riderId, PDO::PARAM_INT);
            $stmt->bindValue(':pickup', $pickup_location);
            $stmt->bindValue(':dropoff', $dropoff_location);
            $stmt->bindValue(':ride_type', $ride_type);
            $stmt->bindValue(':pickup_lat', $pickup_latitude);
            $stmt->bindValue(':pickup_lng', $pickup_longitude);
            $stmt->bindValue(':dropoff_lat', $dropoff_latitude);
            $stmt->bindValue(':dropoff_lng', $dropoff_longitude);
            $stmt->bindValue(':price', $estimated_price);
            $stmt->execute();

            $success = "Ride booked successfully! Looking for available drivers...";
        } catch (PDOException $e) {
            $errors[] = "Error booking ride: " . $e->getMessage();
        }
    }
}

// 3) Handle ride cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_ride'])) {
    $ride_id = intval($_POST['ride_id']);
    
    try {
        $stmt = $conn->prepare("UPDATE rides SET status = 'cancelled' WHERE id = :ride_id AND user_id = :user_id AND status IN ('requested', 'confirmed')");
        $stmt->bindValue(':ride_id', $ride_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $riderId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount()) {
            $success = "Ride cancelled successfully.";
        } else {
            $errors[] = "Failed to cancel ride or ride cannot be cancelled.";
        }
    } catch (PDOException $e) {
        $errors[] = "Error cancelling ride: " . $e->getMessage();
    }
}

// 4) Handle driver selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_driver'])) {
    $ride_id = intval($_POST['ride_id']);
    $driver_id = intval($_POST['driver_id']);
    
    try {
        $stmt = $conn->prepare("UPDATE rides SET driver_id = :driver_id, status = 'confirmed' WHERE id = :ride_id AND user_id = :user_id AND status = 'requested'");
        $stmt->bindValue(':driver_id', $driver_id, PDO::PARAM_INT);
        $stmt->bindValue(':ride_id', $ride_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $riderId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount()) {
            $success = "Driver selected successfully! Your ride is confirmed.";
        } else {
            $errors[] = "Failed to select driver.";
        }
    } catch (PDOException $e) {
        $errors[] = "Error selecting driver: " . $e->getMessage();
    }
}

// 5) Handle ride rating and review - FIXED VERSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $ride_id = intval($_POST['ride_id']);
    $rating = intval($_POST['rating']);
    $comments = trim($_POST['comments'] ?? '');
    
    if ($rating < 1 || $rating > 5) {
        $errors[] = "Please provide a valid rating (1-5 stars).";
    } else {
        try {
            // Check if review already exists for this ride
            $checkStmt = $conn->prepare("SELECT id FROM ride_reviews WHERE ride_id = :ride_id LIMIT 1");
            $checkStmt->bindValue(':ride_id', $ride_id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $errors[] = "You have already reviewed this ride.";
            } else {
                // First, get the driver_id from the ride
                $rideStmt = $conn->prepare("SELECT driver_id FROM rides WHERE id = :ride_id AND user_id = :user_id LIMIT 1");
                $rideStmt->bindValue(':ride_id', $ride_id, PDO::PARAM_INT);
                $rideStmt->bindValue(':user_id', $riderId, PDO::PARAM_INT);
                $rideStmt->execute();
                
                $rideData = $rideStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$rideData || !$rideData['driver_id']) {
                    $errors[] = "Cannot find ride or driver information.";
                } else {
                // Insert review - using rider_id instead of user_id (without comments)
                $stmt = $conn->prepare("INSERT INTO ride_reviews (ride_id, rider_id, driver_id, rating, created_at) VALUES (:ride_id, :rider_id, :driver_id, :rating, NOW())");
                $stmt->bindValue(':ride_id', $ride_id, PDO::PARAM_INT);
                $stmt->bindValue(':rider_id', $riderId, PDO::PARAM_INT);
                $stmt->bindValue(':driver_id', $rideData['driver_id'], PDO::PARAM_INT);
                $stmt->bindValue(':rating', $rating, PDO::PARAM_INT);
                $stmt->execute();

                    // Update driver's average rating - FIXED QUERY
                    $updateRatingStmt = $conn->prepare("
                        UPDATE driver_profiles 
                        SET rating = (
                            SELECT COALESCE(AVG(rr.rating), 0) 
                            FROM ride_reviews rr 
                            WHERE rr.driver_id = :driver_id
                        ) 
                        WHERE user_id = :driver_id2
                    ");
                    $updateRatingStmt->bindValue(':driver_id', $rideData['driver_id'], PDO::PARAM_INT);
                    $updateRatingStmt->bindValue(':driver_id2', $rideData['driver_id'], PDO::PARAM_INT);
                    $updateRatingStmt->execute();

                    $success = "Thank you for your review! Driver rating has been updated.";
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Error submitting review: " . $e->getMessage();
        }
    }
}

// Fetch rider info for form
$riderStmt = $conn->prepare("SELECT full_name, email, phone FROM users WHERE id = :id LIMIT 1");
$riderStmt->bindValue(':id', $riderId, PDO::PARAM_INT);
$riderStmt->execute();
$riderInfo = $riderStmt->fetch(PDO::FETCH_ASSOC);

// Fetch available drivers
$availableDriversStmt = $conn->prepare("
    SELECT dp.*, u.full_name, u.phone
    FROM driver_profiles dp
    INNER JOIN users u ON dp.user_id = u.id
    WHERE dp.is_available = 1
    ORDER BY dp.rating DESC
");
$availableDriversStmt->execute();
$availableDrivers = $availableDriversStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch rider's rides
$myRidesStmt = $conn->prepare("
    SELECT r.*, u.full_name as driver_name, u.phone as driver_phone, 
           dp.vehicle_type, dp.vehicle_model, dp.vehicle_plate, dp.rating as driver_rating
    FROM rides r
    LEFT JOIN users u ON r.driver_id = u.id
    LEFT JOIN driver_profiles dp ON r.driver_id = dp.user_id
    WHERE r.user_id = :user_id
    ORDER BY r.created_at DESC
");
$myRidesStmt->bindValue(':user_id', $riderId, PDO::PARAM_INT);
$myRidesStmt->execute();
$myRides = $myRidesStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total spending per day for plotting
$spendingStmt = $conn->prepare("
    SELECT DATE(created_at) as date, SUM(COALESCE(actual_price, estimated_price)) as total_spent
    FROM rides
    WHERE user_id = :user_id AND status = 'completed'
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)
    LIMIT 30
");
$spendingStmt->bindValue(':user_id', $riderId, PDO::PARAM_INT);
$spendingStmt->execute();
$spending = $spendingStmt->fetchAll(PDO::FETCH_ASSOC);

// Get ride statistics
$totalRides = count(array_filter($myRides, fn($r) => $r['status'] === 'completed'));
$totalSpent = array_sum(array_map(fn($r) => $r['actual_price'] ?? $r['estimated_price'] ?? 0, 
    array_filter($myRides, fn($r) => $r['status'] === 'completed')));
$averageRating = 0;
if ($totalRides > 0) {
    $completedRides = array_filter($myRides, fn($r) => $r['status'] === 'completed');
    $ratingSum = array_sum(array_map(function($r) {
        return $r['driver_rating'] ?? 0;
    }, $completedRides));
    $averageRating = $ratingSum / $totalRides;
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Rider Dashboard | Ride Sharing</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
<link rel="stylesheet" href="rider_dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    .logout-btn {
        color: white;
        text-decoration: none;
        background-color: #28a745;
        padding: 8px 16px;
        border-radius: 25px;
        font-weight: 600;
    }
    .logout-btn:hover {
        background-color: #1e7e34;
        color: white;
        text-decoration: none;
    }
    .ride-card {
        border-left: 4px solid #28a745;
        margin-bottom: 15px;
    }
    .driver-card {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    .driver-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        border-color: #28a745;
    }
    .rating-stars {
        color: #ffc107;
    }
    .status-badge {
        font-size: 0.8em;
        padding: 4px 8px;
        border-radius: 12px;
    }
    .status-requested { background-color: #17a2b8; color: white; }
    .status-confirmed { background-color: #28a745; color: white; }
    .status-in-progress { background-color: #ffc107; color: black; }
    .status-completed { background-color: #6c757d; color: white; }
    .status-cancelled { background-color: #dc3545; color: white; }
    .vehicle-icon {
        font-size: 2rem;
        margin-bottom: 10px;
    }
    .price-calculator {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        border-radius: 15px;
        padding: 20px;
    }
    .map-container {
        height: 300px;
        background: #f8f9fa;
        border: 2px dashed #dee2e6;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    .location-input {
        position: relative;
    }
    .location-suggestions {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        z-index: 1000;
        max-height: 200px;
        overflow-y: auto;
    }
    .suggestion-item {
        padding: 10px;
        cursor: pointer;
        border-bottom: 1px solid #f8f9fa;
    }
    .suggestion-item:hover {
        background-color: #f8f9fa;
    }

    .tracking-map {
    height: 400px;
    width: 100%;
    border-radius: 10px;
    border: 2px solid #28a745;
}

.location-info {
    background: white;
    padding: 10px;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    margin-top: 10px;
}

.driver-marker {
    background: #28a745;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
}

.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
  <div class="container-fluid">
    <a class="navbar-brand" href="#"><i class="fas fa-taxi"></i> RideShare Rider</a>
    <div class="d-flex align-items-center gap-3">
      <span class="navbar-text text-white">Welcome, <?= htmlspecialchars($riderName) ?>!</span>
      <a href="logout.php" class="logout-btn">Logout <i class="fas fa-sign-out-alt"></i></a>
    </div>
  </div>
</nav>

<div class="container mt-4">
    
    <!-- Success/Error Messages -->
    <?php if ($errors): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0"><?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?></ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <h1>Rider Dashboard</h1>
            
            <!-- Book New Ride -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-plus-circle"></i> Book New Ride</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="bookRideForm">
                        <input type="hidden" name="book_ride" value="1" />
                        <input type="hidden" name="pickup_latitude" id="pickupLat" />
                        <input type="hidden" name="pickup_longitude" id="pickupLng" />
                        <input type="hidden" name="dropoff_latitude" id="dropoffLat" />
                        <input type="hidden" name="dropoff_longitude" id="dropoffLng" />
                        <input type="hidden" name="estimated_price" id="estimatedPrice" />
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3 location-input">
                                    <label class="form-label"><i class="fas fa-map-marker-alt text-success"></i> Pickup Location</label>
                                    <input type="text" name="pickup_location" id="pickupLocation" class="form-control" 
                                           placeholder="Enter pickup address" required autocomplete="off" />
                                    <div id="pickupSuggestions" class="location-suggestions" style="display: none;"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 location-input">
                                    <label class="form-label"><i class="fas fa-flag-checkered text-danger"></i> Dropoff Location</label>
                                    <input type="text" name="dropoff_location" id="dropoffLocation" class="form-control" 
                                           placeholder="Enter destination address" required autocomplete="off" />
                                    <div id="dropoffSuggestions" class="location-suggestions" style="display: none;"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-car"></i> Vehicle Type</label>
                            <div class="row">
                                <div class="col-md-3 col-6 text-center mb-3">
                                    <input type="radio" class="btn-check" name="ride_type" id="car" value="Car" required>
                                    <label class="btn btn-outline-primary d-block p-3" for="car">
                                        <i class="fas fa-car vehicle-icon"></i>
                                        <div><strong>Car</strong></div>
                                        <small>৳15/km</small>
                                    </label>
                                </div>
                                <div class="col-md-3 col-6 text-center mb-3">
                                    <input type="radio" class="btn-check" name="ride_type" id="bike" value="Bike" required>
                                    <label class="btn btn-outline-primary d-block p-3" for="bike">
                                        <i class="fas fa-motorcycle vehicle-icon"></i>
                                        <div><strong>Bike</strong></div>
                                        <small>৳8/km</small>
                                    </label>
                                </div>
                                <div class="col-md-3 col-6 text-center mb-3">
                                    <input type="radio" class="btn-check" name="ride_type" id="cng" value="CNG" required>
                                    <label class="btn btn-outline-primary d-block p-3" for="cng">
                                        <i class="fas fa-taxi vehicle-icon"></i>
                                        <div><strong>CNG</strong></div>
                                        <small>৳12/km</small>
                                    </label>
                                </div>
                                <div class="col-md-3 col-6 text-center mb-3">
                                    <input type="radio" class="btn-check" name="ride_type" id="truck" value="Truck" required>
                                    <label class="btn btn-outline-primary d-block p-3" for="truck">
                                        <i class="fas fa-truck vehicle-icon"></i>
                                        <div><strong>Truck</strong></div>
                                        <small>৳25/km</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="price-calculator mb-3">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6><i class="fas fa-calculator"></i> Estimated Fare</h6>
                                    <div id="fareBreakdown">
                                        <div>Distance: <span id="distanceDisplay">-- km</span></div>
                                        <div>Rate: <span id="rateDisplay">--</span></div>
                                        <div>Base Fare: ৳20</div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <h3 id="totalFare">৳--</h3>
                                    <small>Total Estimated Cost</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success btn-lg" onclick="calculateFareAndBook()">
                                <i class="fas fa-search"></i> Calculate Fare & Find Drivers
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Available Drivers Section -->
            <div class="card mb-4" id="driversSection" style="display: none;">
                <div class="card-header">
                    <h5><i class="fas fa-users"></i> Available Drivers</h5>
                </div>
                <div class="card-body" id="driversContainer">
                    <!-- Drivers will be loaded here dynamically -->
                </div>
            </div>

            <!-- My Rides -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> My Rides</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($myRides)): ?>
                        <p class="text-muted">No rides booked yet.</p>
                    <?php else: ?>
                        <?php foreach ($myRides as $ride): ?>
                            <div class="card ride-card p-3 mb-3">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6>Ride #<?= $ride['id'] ?></h6>
                                            <span class="status-badge status-<?= $ride['status'] ?>">
                                                <?= ucfirst(str_replace('_', ' ', $ride['status'])) ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($ride['driver_name']): ?>
                                            <p><strong>Driver:</strong> <?= htmlspecialchars($ride['driver_name']) ?> 
                                               <?php if (in_array($ride['status'], ['confirmed', 'in_progress'])): ?>
                                                   | <a href="tel:<?= htmlspecialchars($ride['driver_phone']) ?>" class="text-success">
                                                       <i class="fas fa-phone"></i> <?= htmlspecialchars($ride['driver_phone']) ?>
                                                   </a>
                                               <?php endif; ?>
                                            </p>
                                            <p><strong>Vehicle:</strong> <?= htmlspecialchars($ride['vehicle_type']) ?> - <?= htmlspecialchars($ride['vehicle_model'] ?? 'N/A') ?>
                                               <?php if ($ride['vehicle_plate']): ?>
                                                   (<?= htmlspecialchars($ride['vehicle_plate']) ?>)
                                               <?php endif; ?>
                                            </p>
                                            <p><strong>Driver Rating:</strong> 
                                                <span class="rating-stars">
                                                    <?php 
                                                    $rating = $ride['driver_rating'] ?? 0;
                                                    for ($i = 1; $i <= 5; $i++): 
                                                        if ($i <= $rating): ?>
                                                            <i class="fas fa-star"></i>
                                                        <?php elseif ($i - 0.5 <= $rating): ?>
                                                            <i class="fas fa-star-half-alt"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-star"></i>
                                                        <?php endif;
                                                    endfor; ?>
                                                </span>
                                                (<?= number_format($rating, 1) ?>/5.0)
                                            </p>
                                        <?php endif; ?>
                                        
                                        <p><strong>Route:</strong> <?= htmlspecialchars($ride['pickup_location']) ?> 
                                           <i class="fas fa-arrow-right mx-2"></i> <?= htmlspecialchars($ride['dropoff_location']) ?></p>
                                        
                                        <?php if ($ride['actual_price']): ?>
                                            <p><strong>Final Price:</strong> ৳<?= number_format($ride['actual_price'], 2) ?></p>
                                        <?php else: ?>
                                            <p><strong>Estimated Price:</strong> ৳<?= number_format($ride['estimated_price'], 2) ?></p>
                                        <?php endif; ?>
                                        
                                        <small class="text-muted">Booked: <?= date('M d, Y H:i', strtotime($ride['created_at'])) ?></small>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <?php if ($ride['status'] === 'requested'): ?>
                                            <form method="POST" class="mb-2">
                                                <input type="hidden" name="ride_id" value="<?= $ride['id'] ?>" />
                                                <button type="submit" name="cancel_ride" class="btn btn-danger btn-sm" 
                                                        onclick="return confirm('Are you sure you want to cancel this ride?')">
                                                    <i class="fas fa-times"></i> Cancel Ride
                                                </button>
                                            </form>
                                        <?php elseif (in_array($ride['status'], ['confirmed', 'in_progress'])): ?>
                                            <button class="btn btn-primary btn-sm mb-2" onclick="trackDriver(<?= $ride['id'] ?>)">
                                                <i class="fas fa-map-marked-alt"></i> Track Driver
                                            </button>
                                        <?php elseif ($ride['status'] === 'completed'): ?>
                                            <?php
                                            // Check if already reviewed
                                            $reviewCheckStmt = $conn->prepare("SELECT id FROM ride_reviews WHERE ride_id = :ride_id LIMIT 1");
                                            $reviewCheckStmt->bindValue(':ride_id', $ride['id'], PDO::PARAM_INT);
                                            $reviewCheckStmt->execute();
                                            $hasReview = $reviewCheckStmt->rowCount() > 0;
                                            ?>
                                            
                                            <?php if (!$hasReview): ?>
                                                <button class="btn btn-warning btn-sm" onclick="showReviewModal(<?= $ride['id'] ?>, '<?= htmlspecialchars($ride['driver_name']) ?>')">
                                                    <i class="fas fa-star"></i> Rate Driver
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-success">Reviewed</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Live Tracking Section -->
                                <?php if (in_array($ride['status'], ['confirmed', 'in_progress'])): ?>
                                    <div id="tracking-<?= $ride['id'] ?>" class="mt-3" style="display: none;">
                                        <hr>
                                        <h6><i class="fas fa-satellite-dish"></i> Live Driver Tracking</h6>
                                        <div class="map-container" id="trackingMap-<?= $ride['id'] ?>">
                                            <div class="text-center">
                                                <div class="spinner-border text-primary mb-3" role="status">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                                <p>Loading driver location...</p>
                                            </div>
                                        </div>
                                        <div class="mt-2 text-center">
                                            <button class="btn btn-danger btn-sm" onclick="stopTracking(<?= $ride['id'] ?>)">
                                                <i class="fas fa-stop"></i> Stop Tracking
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Profile Update Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-user-edit"></i> Update Profile</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1" />
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" 
                                           value="<?= htmlspecialchars(explode(' ', $riderInfo['full_name'])[0] ?? '') ?>" required />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" 
                                           value="<?= htmlspecialchars(implode(' ', array_slice(explode(' ', $riderInfo['full_name']), 1)) ?: '') ?>" required />
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($riderInfo['email']) ?>" required />
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?= htmlspecialchars($riderInfo['phone']) ?>" required />
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($riderInfo['address'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password (optional)</label>
                            <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password" />
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Ride Statistics -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Ride Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <h4 class="text-primary"><?= $totalRides ?></h4>
                            <small class="text-muted">Total Rides</small>
                        </div>
                        <div class="col-4">
                            <h4 class="text-success">৳<?= number_format($totalSpent, 0) ?></h4>
                            <small class="text-muted">Total Spent</small>
                        </div>
                        <div class="col-4">
                            <h4 class="text-warning"><?= number_format($averageRating, 1) ?></h4>
                            <small class="text-muted">Avg Rating</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Spending Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line"></i> Daily Spending</h5>
                </div>
                <div class="card-body">
                    <canvas id="spendingChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rate Your Driver</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="reviewForm">
                <div class="modal-body">
                    <input type="hidden" name="submit_review" value="1" />
                    <input type="hidden" name="ride_id" id="reviewRideId" />
                    
                    <div class="text-center mb-3">
                        <h6 id="driverNameDisplay">Driver Name</h6>
                    </div>
                    
                    <div class="card ride-card p-3 mb-3" 
                        data-ride-id="<?= $ride['id'] ?>" 
                        data-ride-status="<?= $ride['status'] ?>"
                        data-pickup-lat="<?= $ride['pickup_latitude'] ?>"
                        data-pickup-lng="<?= $ride['pickup_longitude'] ?>"
                        data-dropoff-lat="<?= $ride['dropoff_latitude'] ?>"
                        data-dropoff-lng="<?= $ride['dropoff_longitude'] ?>">
                        <label class="form-label">Rating</label>
                        <div class="rating-input">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" required>
                                <label for="star<?= $i ?>" class="star-label">
                                    <i class="fas fa-star"></i>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Comments (optional)</label>
                        <textarea name="comments" class="form-control" rows="3" placeholder="Share your experience..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Initialize spending chart
const ctx = document.getElementById('spendingChart').getContext('2d');
const spendingData = <?= json_encode($spending) ?>;

const chartData = {
    labels: spendingData.map(item => item.date),
    datasets: [{
        label: 'Daily Spending (৳)',
        data: spendingData.map(item => item.total_spent),
        borderColor: '#28a745',
        backgroundColor: 'rgba(40, 167, 69, 0.1)',
        borderWidth: 2,
        fill: true
    }]
};

new Chart(ctx, {
    type: 'line',
    data: chartData,
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '৳' + value;
                    }
                }
            }
        }
    }
});

// Ride booking and fare calculation
function calculateFareAndBook() {
    const pickupLocation = document.getElementById('pickupLocation').value;
    const dropoffLocation = document.getElementById('dropoffLocation').value;
    const rideType = document.querySelector('input[name="ride_type"]:checked');
    
    if (!pickupLocation || !dropoffLocation || !rideType) {
        alert('Please fill all required fields');
        return;
    }
    
    // Mock distance calculation (in real app, use Google Maps API)
    const distance = Math.random() * 20 + 2; // Random distance between 2-22 km
    
    const rates = {
        'Car': 15,
        'Bike': 8,
        'CNG': 12,
        'Truck': 25
    };
    
    const rate = rates[rideType.value];
    const baseFare = 20;
    const totalFare = baseFare + (distance * rate);
    
    // Update UI
    document.getElementById('distanceDisplay').textContent = distance.toFixed(1) + ' km';
    document.getElementById('rateDisplay').textContent = '৳' + rate + '/km';
    document.getElementById('totalFare').textContent = '৳' + totalFare.toFixed(0);
    document.getElementById('estimatedPrice').value = totalFare;
    
    // Show available drivers
    showAvailableDrivers();
}

function showAvailableDrivers() {
    const driversSection = document.getElementById('driversSection');
    const driversContainer = document.getElementById('driversContainer');
    
    // Mock available drivers data
    const drivers = <?= json_encode($availableDrivers) ?>;
    
    let driversHtml = '';
    drivers.forEach(driver => {
        driversHtml += `
            <div class="driver-card p-3 mb-3">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6>${driver.full_name}</h6>
                        <p class="mb-1"><strong>Vehicle:</strong> ${driver.vehicle_type} - ${driver.vehicle_model || 'N/A'}</p>
                        <p class="mb-1"><strong>Rating:</strong> 
                            <span class="rating-stars">
                                ${'★'.repeat(Math.floor(driver.rating))}${'☆'.repeat(5 - Math.floor(driver.rating))}
                            </span>
                            (${driver.rating}/5.0)
                        </p>
                        <p class="mb-0"><strong>Phone:</strong> ${driver.phone}</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-success btn-sm" onclick="selectDriver(${driver.user_id})">
                            <i class="fas fa-check"></i> Select Driver
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    driversContainer.innerHTML = driversHtml || '<p class="text-muted">No drivers available at the moment.</p>';
    driversSection.style.display = 'block';
}

function selectDriver(driverId) {
    // In real app, this would submit the form with selected driver
    document.getElementById('bookRideForm').submit();
}

// Driver tracking functions
function trackDriver(rideId) {
    const trackingDiv = document.getElementById('tracking-' + rideId);
    trackingDiv.style.display = 'block';
    
    // Mock real-time tracking (in real app, use WebSocket or periodic AJAX calls)
    setTimeout(() => {
        const mapContainer = document.getElementById('trackingMap-' + rideId);
        mapContainer.innerHTML = `
            <div class="text-center">
                <i class="fas fa-car text-success" style="font-size: 3rem;"></i>
                <p class="mt-2"><strong>Driver is 5 minutes away</strong></p>
                <p class="text-muted">Location: Moving towards pickup point</p>
            </div>
        `;
    }, 2000);
}

function stopTracking(rideId) {
    document.getElementById('tracking-' + rideId).style.display = 'none';
}

// Review modal functions
function showReviewModal(rideId, driverName) {
    document.getElementById('reviewRideId').value = rideId;
    document.getElementById('driverNameDisplay').textContent = driverName;
    new bootstrap.Modal(document.getElementById('reviewModal')).show();
}

// Star rating interaction
document.querySelectorAll('.rating-input input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const value = this.value;
        const labels = this.closest('.rating-input').querySelectorAll('.star-label');
        labels.forEach((label, index) => {
            if (index < value) {
                label.style.color = '#ffc107';
            } else {
                label.style.color = '#dee2e6';
            }
        });
    });
});

let trackingMaps = {};
let trackingIntervals = {};

function trackDriver(rideId) {
    const trackingDiv = document.getElementById('tracking-' + rideId);
    trackingDiv.style.display = 'block';
    
    // Create map container HTML
    const mapContainer = document.getElementById('trackingMap-' + rideId);
    mapContainer.innerHTML = `
        <div id="map-${rideId}" class="tracking-map"></div>
        <div class="location-info">
            <div class="row">
                <div class="col-6">
                    <small class="text-muted">Driver Location</small>
                    <div id="driver-location-${rideId}">Loading...</div>
                </div>
                <div class="col-6">
                    <small class="text-muted">Estimated Arrival</small>
                    <div id="eta-${rideId}">Calculating...</div>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-6">
                    <small class="text-muted">Distance</small>
                    <div id="distance-${rideId}">--</div>
                </div>
                <div class="col-6">
                    <small class="text-muted">Status</small>
                    <div id="ride-status-${rideId}">On the way</div>
                </div>
            </div>
        </div>
    `;
    
    // Initialize map after DOM is ready
    setTimeout(() => {
        initializeTrackingMap(rideId);
    }, 100);
}

function initializeTrackingMap(rideId) {
    // Default center (Dhaka, Bangladesh)
    const defaultCenter = [23.8103, 90.4125];
    
    // Initialize map
    const map = L.map(`map-${rideId}`).setView(defaultCenter, 13);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Store map reference
    trackingMaps[rideId] = {
        map: map,
        driverMarker: null,
        pickupMarker: null,
        routeLine: null
    };
    
    // Add pickup location marker (mock coordinates)
    const pickupLatLng = [23.8103 + (Math.random() - 0.5) * 0.01, 90.4125 + (Math.random() - 0.5) * 0.01];
    trackingMaps[rideId].pickupMarker = L.marker(pickupLatLng, {
        icon: L.divIcon({
            className: 'pickup-marker',
            html: '<i class="fas fa-map-marker-alt" style="color: #28a745; font-size: 20px;"></i>',
            iconSize: [20, 20],
            iconAnchor: [10, 20]
        })
    }).addTo(map).bindPopup('Pickup Location');
    
    // Start real-time tracking
    startRealTimeTracking(rideId, pickupLatLng);
}

function startRealTimeTracking(rideId, pickupLatLng) {
    const trackingData = trackingMaps[rideId];
    let driverLatLng = [23.8103 + (Math.random() - 0.5) * 0.02, 90.4125 + (Math.random() - 0.5) * 0.02];
    let step = 0;
    
    // Clear any existing interval
    if (trackingIntervals[rideId]) {
        clearInterval(trackingIntervals[rideId]);
    }
    
    trackingIntervals[rideId] = setInterval(() => {
        // Simulate driver movement towards pickup location
        const moveTowardsPickup = step < 20;
        
        if (moveTowardsPickup) {
            // Move driver towards pickup
            driverLatLng[0] += (pickupLatLng[0] - driverLatLng[0]) * 0.1;
            driverLatLng[1] += (pickupLatLng[1] - driverLatLng[1]) * 0.1;
        } else {
            // Simulate ride in progress - move away from pickup
            driverLatLng[0] += (Math.random() - 0.5) * 0.001;
            driverLatLng[1] += (Math.random() - 0.5) * 0.001;
        }
        
        updateDriverLocation(rideId, driverLatLng, pickupLatLng, step);
        step++;
        
        // Stop after 60 updates (simulating ride completion)
        if (step > 60) {
            clearInterval(trackingIntervals[rideId]);
            updateRideStatus(rideId, 'completed');
        }
    }, 3000); // Update every 3 seconds
}

function updateDriverLocation(rideId, driverLatLng, pickupLatLng, step) {
    const trackingData = trackingMaps[rideId];
    const map = trackingData.map;
    
    // Remove existing driver marker
    if (trackingData.driverMarker) {
        map.removeLayer(trackingData.driverMarker);
    }
    
    // Add new driver marker with car icon
    trackingData.driverMarker = L.marker(driverLatLng, {
        icon: L.divIcon({
            className: 'driver-marker pulse',
            html: '<i class="fas fa-car" style="color: white; font-size: 12px; margin: 4px;"></i>',
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        })
    }).addTo(map);
    
    // Calculate distance
    const distance = calculateDistance(driverLatLng[0], driverLatLng[1], pickupLatLng[0], pickupLatLng[1]);
    
    // Update route line
    if (trackingData.routeLine) {
        map.removeLayer(trackingData.routeLine);
    }
    
    trackingData.routeLine = L.polyline([driverLatLng, pickupLatLng], {
        color: '#28a745',
        weight: 3,
        opacity: 0.7,
        dashArray: '5, 10'
    }).addTo(map);
    
    // Center map on driver location
    map.setView(driverLatLng, 15);
    
    // Update info panel
    const eta = Math.max(1, Math.ceil(distance * 2)); // Rough ETA calculation
    const status = step < 20 ? 'Coming to pickup' : 'Ride in progress';
    
    document.getElementById(`driver-location-${rideId}`).innerHTML = `
        <strong>${getLocationName(driverLatLng)}</strong>
    `;
    document.getElementById(`eta-${rideId}`).innerHTML = `<strong>${eta} min</strong>`;
    document.getElementById(`distance-${rideId}`).innerHTML = `<strong>${distance.toFixed(1)} km</strong>`;
    document.getElementById(`ride-status-${rideId}`).innerHTML = `<strong>${status}</strong>`;
}

function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in kilometers
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

function getLocationName(latLng) {
    // Mock location names (in real app, use reverse geocoding)
    const locations = [
        'Dhanmondi', 'Gulshan', 'Banani', 'Uttara', 'Mirpur',
        'Old Dhaka', 'Wari', 'Motijheel', 'Ramna', 'Tejgaon'
    ];
    return locations[Math.floor(Math.random() * locations.length)];
}

function updateRideStatus(rideId, status) {
    document.getElementById(`ride-status-${rideId}`).innerHTML = `<strong class="text-success">Ride ${status}</strong>`;
    
    if (status === 'completed') {
        // Show completion message
        setTimeout(() => {
            const mapContainer = document.getElementById('trackingMap-' + rideId);
            mapContainer.innerHTML = `
                <div class="text-center p-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Ride Completed!</h5>
                    <p class="text-muted">Thank you for riding with us</p>
                </div>
            `;
        }, 2000);
    }
}

function stopTracking(rideId) {
    // Clear interval
    if (trackingIntervals[rideId]) {
        clearInterval(trackingIntervals[rideId]);
        delete trackingIntervals[rideId];
    }
    
    // Remove map
    if (trackingMaps[rideId]) {
        delete trackingMaps[rideId];
    }
    
    // Hide tracking section
    document.getElementById('tracking-' + rideId).style.display = 'none';
}

// Auto-start tracking for rides that are in progress
document.addEventListener('DOMContentLoaded', function() {
    // Auto-start tracking for confirmed/in-progress rides
    const confirmedRides = document.querySelectorAll('[data-ride-status="confirmed"], [data-ride-status="in_progress"]');
    confirmedRides.forEach(rideElement => {
        const rideId = rideElement.dataset.rideId;
        if (rideId) {
            // Auto-start tracking after 2 seconds
            setTimeout(() => trackDriver(rideId), 2000);
        }
    });
});



</script>

<style>
.rating-input {
    display: flex;
    justify-content: center;
    gap: 5px;
}

.rating-input input[type="radio"] {
    display: none;
}

.star-label {
    font-size: 2rem;
    color: #dee2e6;
    cursor: pointer;
    transition: color 0.2s;
}

.star-label:hover {
    color: #ffc107;
}
</style>

</body>
</html>
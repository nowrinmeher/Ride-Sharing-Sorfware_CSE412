<?php
session_start();
require_once 'database.php';

// Check admin login
if (empty($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$adminName = $_SESSION['user_name'] ?? 'Admin';

// Fetch stats
$totalUsers = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalDrivers = $conn->query("SELECT COUNT(*) FROM users WHERE user_type='driver'")->fetchColumn();
$totalRiders = $conn->query("SELECT COUNT(*) FROM users WHERE user_type='rider'")->fetchColumn();
$pendingRides = $conn->query("SELECT COUNT(*) FROM rides WHERE status='requested'")->fetchColumn();

// Fetch drivers with profile info including seating_capacity
$driversStmt = $conn->prepare("
    SELECT u.id, u.full_name, u.phone, u.email, dp.license_number, dp.nid_number, dp.vehicle_type, dp.vehicle_model, dp.vehicle_plate,
           dp.rating, dp.is_available, dp.current_latitude, dp.current_longitude, dp.total_rides
    FROM users u
    LEFT JOIN driver_profiles dp ON u.id = dp.user_id
    WHERE u.user_type = 'driver'
    ORDER BY u.full_name
");
$driversStmt->execute();
$drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch riders info
$ridersStmt = $conn->prepare("
    SELECT id, full_name, phone, email
    FROM users
    WHERE user_type = 'rider'
    ORDER BY full_name
");
$ridersStmt->execute();
$riders = $ridersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch ride assignments
$ridesStmt = $conn->prepare("
    SELECT r.id, r.pickup_location, r.dropoff_location, r.ride_type, r.status, r.actual_price, r.created_at, 
           u.full_name AS rider_name, d.full_name AS driver_name
    FROM rides r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN users d ON r.driver_id = d.id
    ORDER BY r.created_at DESC
");
$ridesStmt->execute();
$rides = $ridesStmt->fetchAll(PDO::FETCH_ASSOC);

// Rider response analytics queries

// 1. Top 5 Pickup Locations
$topPickupLocationsStmt = $conn->prepare("
    SELECT pickup_location, COUNT(*) as count
    FROM rides
    WHERE pickup_location IS NOT NULL AND pickup_location != ''
    GROUP BY pickup_location
    ORDER BY count DESC
    LIMIT 5
");
$topPickupLocationsStmt->execute();
$topPickupLocations = $topPickupLocationsStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Top 5 Active Riders (most rides)
$topActiveRidersStmt = $conn->prepare("
    SELECT u.full_name, COUNT(r.id) as ride_count
    FROM rides r
    JOIN users u ON r.user_id = u.id
    GROUP BY r.user_id
    ORDER BY ride_count DESC
    LIMIT 5
");
$topActiveRidersStmt->execute();
$topActiveRiders = $topActiveRidersStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Top 5 Rated Riders (by avg rating, min 3 reviews)
$topRatedRidersStmt = $conn->prepare("
    SELECT u.full_name, AVG(rr.rating) as avg_rating, COUNT(rr.id) as review_count
    FROM ride_reviews rr
    JOIN rides r ON rr.ride_id = r.id
    JOIN users u ON r.user_id = u.id
    GROUP BY u.id
    HAVING review_count >= 3
    ORDER BY avg_rating DESC
    LIMIT 5
");
$topRatedRidersStmt->execute();
$topRatedRiders = $topRatedRidersStmt->fetchAll(PDO::FETCH_ASSOC);

// Clean average rating data for JS
$cleanRatedRidersData = [];
foreach ($topRatedRiders as $r) {
    $cleanRatedRidersData[] = round((float)$r['avg_rating'], 2);
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin Dashboard | Ride Sharing</title>
<link href="https://cdn.jsdelivr.net/npm/chart.js" rel="stylesheet" />
<style>
  /* Enhanced Admin Dashboard Styling */
  :root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ffa500 100%);
    --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    --card-hover-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    --glass-bg: rgba(255, 255, 255, 0.1);
    --glass-border: rgba(255, 255, 255, 0.2);
    --text-gradient: linear-gradient(135deg, #667eea, #764ba2);
  }

  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    background-attachment: fixed;
    color: #333;
    line-height: 1.6;
    overflow-x: hidden;
  }

  /* Animated Background */
  body::before {
    content: '';
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: 
        radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 40% 40%, rgba(120, 200, 255, 0.2) 0%, transparent 50%);
    animation: float 20s ease-in-out infinite;
    z-index: -1;
  }

  @keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    33% { transform: translateY(-20px) rotate(1deg); }
    66% { transform: translateY(10px) rotate(-1deg); }
  }

  /* Header Styling */
  header {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--glass-border);
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    padding: 1.5rem 2rem;
    color: white;
    font-weight: 600;
    position: relative;
    text-align: center;
    user-select: none;
    animation: slideInDown 0.8s ease-out;
  }

  @keyframes slideInDown {
    from {
      opacity: 0;
      transform: translateY(-30px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  header h1 {
    font-size: 2.2rem;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    animation: glow 2s ease-in-out infinite alternate;
  }

  @keyframes glow {
    from { text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3); }
    to { text-shadow: 0 4px 20px rgba(255, 255, 255, 0.3); }
  }

  header p {
    font-size: 1.1rem;
    opacity: 0.9;
    font-weight: 400;
  }

  .logout-btn {
  position: absolute;
  top: 1.5rem;
  left: 40rem;  /* Changed from 'right: 2rem' to 'left: 2rem' */
  background: var(--danger-gradient);
    padding: 10px 20px;
    border-radius: 50px;
    color: white;
    font-weight: 600;
    text-decoration: none;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
    transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
    position: relative;
    overflow: hidden;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .logout-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.5s;
  }

  .logout-btn:hover::before {
    left: 100%;
  }

  .logout-btn:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
    color: white;
  }

  /* Main container */
  main {
    max-width: 1400px;
    margin: 2rem auto 4rem;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem 3rem;
    box-shadow: var(--card-shadow);
    user-select: none;
    animation: fadeInUp 0.8s ease-out;
    position: relative;
    overflow: hidden;
  }

  main::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
  }

  @keyframes fadeInUp {
    from {
      opacity: 0;
      transform: translateY(30px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  /* Alert Messages */
  .alert-success {
    background: linear-gradient(135deg, rgba(79, 172, 254, 0.1), rgba(0, 242, 254, 0.1));
    color: #0066cc;
    border: none;
    border-left: 4px solid #4facfe;
    border-radius: 15px;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    font-weight: 500;
  }

  .alert-danger {
    background: linear-gradient(135deg, rgba(255, 107, 107, 0.1), rgba(255, 165, 0, 0.1));
    color: #cc0000;
    border: none;
    border-left: 4px solid #ff6b6b;
    border-radius: 15px;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    font-weight: 500;
  }

  /* Stats cards */
  .stats {
    display: flex;
    gap: 24px;
    justify-content: center;
    margin-bottom: 3rem;
    flex-wrap: wrap;
  }

  .card {
    flex: 1 1 200px;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    box-shadow: var(--card-shadow);
    position: relative;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    animation: slideInUp 0.6s ease-out;
    animation-fill-mode: both;
  }

  .card:nth-child(1) { animation-delay: 0.1s; }
  .card:nth-child(2) { animation-delay: 0.2s; }
  .card:nth-child(3) { animation-delay: 0.3s; }
  .card:nth-child(4) { animation-delay: 0.4s; }

  @keyframes slideInUp {
    from {
      opacity: 0;
      transform: translateY(30px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
  }

  .card:hover::before {
    transform: scaleX(1);
  }

  .card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: var(--card-hover-shadow);
  }

  .card h2 {
    background: var(--text-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
  }

  .card p {
    font-weight: 500;
    font-size: 1rem;
    color: #555;
    margin-top: 0.75rem;
  }

  /* Headings */
  h2 {
    color: #166ba0;
    margin-bottom: 1.5rem;
    font-weight: 700;
    font-size: 1.8rem;
    position: relative;
    padding-bottom: 0.5rem;
  }

  h2::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 50px;
    height: 3px;
    background: var(--primary-gradient);
    border-radius: 2px;
  }

  /* Table styling */
  .table-container {
    overflow-x: auto;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(28,163,216,0.15);
    margin-bottom: 3rem;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
  }

  table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 20px;
    overflow: hidden;
  }

  thead tr {
    background: var(--primary-gradient);
    color: white;
  }

  th, td {
    padding: 15px 20px;
    font-size: 14px;
    color: #333;
    border-bottom: 1px solid rgba(102, 126, 234, 0.1);
    text-align: left;
    transition: all 0.3s ease;
  }

  th {
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    font-size: 13px;
  }

  th:first-child, td:first-child {
    padding-left: 2rem;
  }

  th:last-child, td:last-child {
    padding-right: 2rem;
  }

  tbody tr {
    transition: all 0.3s ease;
  }

  tbody tr:hover {
    background: rgba(102, 126, 234, 0.1);
    transform: scale(1.01);
  }

  .btn-delete {
    background: var(--danger-gradient);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 10px rgba(255, 107, 107, 0.3);
  }

  .btn-delete:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
  }

  /* Analytics cards */
  .analytics-container {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
    justify-content: space-between;
  }

  .chart-card {
    flex: 1 1 30%;
    min-width: 300px;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    padding: 2rem;
    user-select: none;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
  }

  .chart-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--success-gradient);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
  }

  .chart-card:hover::before {
    transform: scaleX(1);
  }

  .chart-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--card-hover-shadow);
  }

  .chart-card h3 {
    font-weight: 700;
    font-size: 1.3rem;
    margin-bottom: 1.5rem;
    color: #166ba0;
    text-align: center;
  }

  .chart-card canvas {
    border-radius: 15px;
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
  }

  /* Location iframe styling */
  iframe {
    width: 200px;
    height: 120px;
    border: 2px solid rgba(102, 126, 234, 0.2);
    border-radius: 10px;
    transition: all 0.3s ease;
  }

  iframe:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
  }

  /* Vehicle details styling */
  .vehicle-col {
    max-width: 200px;
  }

  .vehicle-col small {
    color: #666;
    font-style: italic;
  }

  /* Responsive Design */
  @media (max-width: 1024px) {
    .stats, .analytics-container {
      flex-direction: column;
      gap: 2rem;
    }
    .chart-card {
      flex-basis: 100%;
      min-width: auto;
    }
    main {
      margin: 1rem;
      padding: 1.5rem 2rem;
    }
  }

  @media (max-width: 768px) {
    header {
      padding: 1rem 1.5rem;
      font-size: 0.9rem;
    }
    
    header h1 {
      font-size: 1.8rem;
    }
    
    .logout-btn {
      top: 1rem;
      right: 1.5rem;
      padding: 8px 16px;
      font-size: 0.8rem;
    }
    
    main {
      padding: 1rem 1.5rem;
    }
    
    .card {
      font-size: 1.4rem;
      padding: 1.5rem;
    }
    
    .card h2 {
      font-size: 2rem;
    }
    
    th, td {
      padding: 10px 12px;
      font-size: 12px;
    }
    
    .table-container {
      font-size: 12px;
    }
    
    iframe {
      width: 150px;
      height: 100px;
    }
  }

  /* Scrollbar Styling */
  ::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  ::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
  }

  ::-webkit-scrollbar-thumb {
    background: var(--primary-gradient);
    border-radius: 10px;
    transition: all 0.3s ease;
  }

  ::-webkit-scrollbar-thumb:hover {
    background: var(--dark-gradient);
  }

  /* Loading states */
  .loading {
    opacity: 0.6;
    pointer-events: none;
  }

  /* Enhanced focus states */
  *:focus {
    outline: none;
  }

  .btn-delete:focus {
    box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.25);
  }

  /* Micro animations */
  @keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
  }

  .card:active {
    animation: pulse 0.3s ease;
  }

  /* Status indicators */
  .status-available {
    color: #4facfe;
    font-weight: 600;
  }

  .status-unavailable {
    color: #ff6b6b;
    font-weight: 600;
  }

  /* Enhanced table rows */
  tbody tr:nth-child(even) {
    background: rgba(102, 126, 234, 0.02);
  }

  /* Modern glassmorphism effects */
  .glass-effect {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Pickup Locations Bar Chart
  const pickupCtx = document.getElementById('pickupLocationsChart').getContext('2d');
  new Chart(pickupCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($topPickupLocations, 'pickup_location')) ?>,
      datasets: [{
        label: 'Number of Rides',
        data: <?= json_encode(array_column($topPickupLocations, 'count')) ?>,
        backgroundColor: 'rgba(28, 163, 216, 0.7)'
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 } }
      }
    }
  });

  // Active Riders Bar Chart
  const activeRidersCtx = document.getElementById('activeRidersChart').getContext('2d');
  new Chart(activeRidersCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($topActiveRiders, 'full_name')) ?>,
      datasets: [{
        label: 'Total Rides',
        data: <?= json_encode(array_column($topActiveRiders, 'ride_count')) ?>,
        backgroundColor: 'rgba(40, 167, 69, 0.7)'
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 } }
      }
    }
  });

  // Rated Riders Bar Chart
  const ratedRidersCtx = document.getElementById('ratedRidersChart').getContext('2d');
  new Chart(ratedRidersCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($topRatedRiders, 'full_name')) ?>,
      datasets: [{
        label: 'Average Rating',
        data: <?= json_encode($cleanRatedRidersData) ?>,
        backgroundColor: 'rgba(255, 193, 7, 0.7)'
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: { beginAtZero: true, max: 5, ticks: { stepSize: 1 } }
      }
    }
  });
});



// Add this JavaScript code to your existing script section or before the closing </body> tag

function confirmDelete(userId, userType) {
    if (confirm(`Are you sure you want to delete this ${userType}? This action cannot be undone.`)) {
        // Create a form dynamically to submit the delete request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete_user.php'; // You'll need to create this PHP file
        form.style.display = 'none';
        
        // Add user ID field
        const userIdField = document.createElement('input');
        userIdField.type = 'hidden';
        userIdField.name = 'user_id';
        userIdField.value = userId;
        
        // Add user type field
        const userTypeField = document.createElement('input');
        userTypeField.type = 'hidden';
        userTypeField.name = 'user_type';
        userTypeField.value = userType;
        
        // Add CSRF token for security (optional but recommended)
        const csrfField = document.createElement('input');
        csrfField.type = 'hidden';
        csrfField.name = 'csrf_token';
        csrfField.value = '<?php echo $_SESSION["csrf_token"] ?? ""; ?>'; // You'll need to generate this in PHP
        
        form.appendChild(userIdField);
        form.appendChild(userTypeField);
        form.appendChild(csrfField);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function confirmLogout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php';
    }
}

// Alternative AJAX version for delete (if you prefer not to reload the page)
function confirmDeleteAjax(userId, userType) {
    if (confirm(`Are you sure you want to delete this ${userType}? This action cannot be undone.`)) {
        fetch('delete_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${userId}&user_type=${userType}&csrf_token=<?php echo $_SESSION["csrf_token"] ?? ""; ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('User deleted successfully!');
                location.reload(); // Refresh the page to update the table
            } else {
                alert('Error: ' + (data.message || 'Unable to delete user'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the user');
        });
    }
}
</script>
</head>
<body>

<header>
    <a href="#" onclick="confirmLogout(); return false;" class="logout-btn">Logout</a>
    <h1>Welcome, <?= htmlspecialchars($adminName) ?></h1>
    <p>Admin Dashboard - Ride Sharing Application</p>
</header>

<main>

<?php if (!empty($_GET['msg'])): ?>
    <div style="color: green; margin-bottom: 20px;"><?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>

<?php if (!empty($_GET['error'])): ?>
    <div style="color: red; margin-bottom: 20px;"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="stats">
    <div class="card"><h2><?= $totalUsers ?></h2><p>Total Users</p></div>
    <div class="card"><h2><?= $totalDrivers ?></h2><p>Drivers</p></div>
    <div class="card"><h2><?= $totalRiders ?></h2><p>Riders</p></div>
    <div class="card"><h2><?= $pendingRides ?></h2><p>Pending Ride Requests</p></div>
</div>

<h2>Driver List</h2>
<div class="table-container">
<table class="driver-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Phone</th>
            <th>Email</th>
            <th class="nid-col">NID Number</th>
            <th class="license-col">License Number</th>
            <th class="vehicle-col">Vehicle Details</th>
            <th>Rating</th>
            <th>Available</th>
            <th>Total Rides</th>
            <th class="location-col">Current Location</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($drivers as $d): ?>
        <tr>
            <td><?= htmlspecialchars($d['full_name']) ?></td>
            <td><?= htmlspecialchars($d['phone']) ?></td>
            <td><?= htmlspecialchars($d['email']) ?></td>
            <td class="nid-col"><?= htmlspecialchars($d['nid_number'] ?? 'Not provided') ?></td>
            <td class="license-col"><?= htmlspecialchars($d['license_number'] ?? 'Not provided') ?></td>
            <td class="vehicle-col">
                <?php if ($d['vehicle_type'] || $d['vehicle_model'] || $d['vehicle_plate']): ?>
                    <?= htmlspecialchars(trim($d['vehicle_type'] . ' ' . $d['vehicle_model'])) ?><br>
                    <small>Plate: <?= htmlspecialchars($d['vehicle_plate'] ?? 'N/A') ?></small>
                <?php else: ?>
                    Not provided
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($d['rating'] ?? '5.00') ?></td>
            <td><?= $d['is_available'] ? 'Yes' : 'No' ?></td>
            <td><?= htmlspecialchars($d['total_rides'] ?? '0') ?></td>
            <td class="location-col">
                <?php if ($d['current_latitude'] && $d['current_longitude']): ?>
                <iframe src="https://maps.google.com/maps?q=<?= $d['current_latitude'] ?>,<?= $d['current_longitude'] ?>&z=15&output=embed"></iframe>
                <br><small>Lat: <?= number_format($d['current_latitude'], 6) ?>, Lng: <?= number_format($d['current_longitude'], 6) ?></small>
                <?php else: ?>
                Location not available
                <?php endif; ?>
            </td>
            <td><span class="btn-delete" onclick="confirmDelete(<?= $d['id'] ?>, 'driver')">Delete</span></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<h2>Rider List</h2>
<table>
    <thead>
        <tr><th>Name</th><th>Phone</th><th>Email</th><th>Action</th></tr>
    </thead>
    <tbody>
        <?php foreach($riders as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['full_name']) ?></td>
            <td><?= htmlspecialchars($r['phone']) ?></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><span class="btn-delete" onclick="confirmDelete(<?= $r['id'] ?>, 'rider')">Delete</span></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h2>Recent Ride History</h2>
<div class="table-container">
<table>
    <thead>
        <tr>
            <th>Ride ID</th><th>Rider</th><th>Driver</th><th>Pickup Location</th><th>Dropoff Location</th><th>Ride Type</th><th>Status</th><th>Fare (BDT)</th><th>Date & Time</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($rides as $ride): ?>
        <tr>
            <td><?= htmlspecialchars($ride['id']) ?></td>
            <td><?= htmlspecialchars($ride['rider_name']) ?></td>
            <td><?= htmlspecialchars($ride['driver_name'] ?? 'Unassigned') ?></td>
            <td><?= htmlspecialchars($ride['pickup_location']) ?></td>
            <td><?= htmlspecialchars($ride['dropoff_location']) ?></td>
            <td><?= htmlspecialchars($ride['ride_type']) ?></td>
            <td><?= htmlspecialchars(ucfirst($ride['status'])) ?></td>
            <td><?= $ride['actual_price'] ? '৳' . number_format($ride['actual_price'], 2) : '—' ?></td>
            <td><?= date('d M Y, h:i A', strtotime($ride['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<h2>Rider Response Analytics</h2>
<div class="analytics-container">

  <div class="chart-card">
    <h3>Top 5 Pickup Locations</h3>
    <canvas id="pickupLocationsChart"></canvas>
  </div>

  <div class="chart-card">
    <h3>Top 5 Active Riders</h3>
    <canvas id="activeRidersChart"></canvas>
  </div>

  <div class="chart-card">
    <h3>Top 5 Rated Riders</h3>
    <canvas id="ratedRidersChart"></canvas>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Pickup Locations Bar Chart
  const pickupCtx = document.getElementById('pickupLocationsChart').getContext('2d');
  new Chart(pickupCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($topPickupLocations, 'pickup_location')) ?>,
      datasets: [{
        label: 'Number of Rides',
        data: <?= json_encode(array_column($topPickupLocations, 'count')) ?>,
        backgroundColor: 'rgba(28, 163, 216, 0.7)'
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 } }
      }
    }
  });

  // Active Riders Bar Chart
  const activeRidersCtx = document.getElementById('activeRidersChart').getContext('2d');
  new Chart(activeRidersCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($topActiveRiders, 'full_name')) ?>,
      datasets: [{
        label: 'Total Rides',
        data: <?= json_encode(array_column($topActiveRiders, 'ride_count')) ?>,
        backgroundColor: 'rgba(40, 167, 69, 0.7)'
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 } }
      }
    }
  });

  // Rated Riders Bar Chart
  const ratedRidersCtx = document.getElementById('ratedRidersChart').getContext('2d');
  new Chart(ratedRidersCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($topRatedRiders, 'full_name')) ?>,
      datasets: [{
        label: 'Average Rating',
        data: <?= json_encode($cleanRatedRidersData) ?>,
        backgroundColor: 'rgba(255, 193, 7, 0.7)'
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: { beginAtZero: true, max: 5, ticks: { stepSize: 1 } }
      }
    }
  });
});
</script>
</body>
</html>

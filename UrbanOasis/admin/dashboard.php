<?php
// Prevent browser caching for authenticated pages
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/property_utils.php';
require_once __DIR__ . '/../config/user_utils.php';
require_once __DIR__ . '/../config/admin_utils.php';
$pageTitle = "Admin Dashboard";

// Fetch essential counts using centralized property queries
$userCount = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$propertyCount = $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
$pendingCount = $pdo->query("SELECT COUNT(*) FROM properties WHERE approval_status = 'pending'")->fetchColumn();
$rejectedCount = $pdo->query("SELECT COUNT(*) FROM properties WHERE approval_status = 'rejected'")->fetchColumn();

// Revenue data
$totalRevenue = $pdo->query("SELECT SUM(amount_paid) FROM credit_transactions WHERE transaction_type = 'purchase'")->fetchColumn() ?: 0;

// Contact messages count
$contactMessagesCount = $pdo->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();

// Check if is_read column exists, if not, add it
try {
    $unreadMessagesCount = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE COALESCE(is_read, 0) = 0")->fetchColumn();
} catch (PDOException $e) {
    // If column doesn't exist, add it and set all existing messages as unread
    $pdo->exec("ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0");
    $unreadMessagesCount = $contactMessagesCount; // All messages are unread initially
}

// Recent listings needing attention
$recentPending = $pdo->query("
    SELECT p.id, p.title, p.created_at, u.first_name, u.last_name 
    FROM properties p 
    LEFT JOIN users u ON p.user_id = u.id 
    WHERE p.approval_status = 'pending'
    ORDER BY p.created_at DESC 
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Urban Oasis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 200px;
            background: #2c3e50;
            color: #fff;
            padding: 0;
            z-index: 100;
        }
        .admin-sidebar .brand {
            font-weight: 600;
            font-size: 1.1rem;
            color: #fff;
            padding: 20px 15px;
            border-bottom: 1px solid #34495e;
        }
        .admin-sidebar .nav-link {
            color: #bdc3c7;
            padding: 12px 15px;
            border-radius: 0;
            font-size: 0.9rem;
            border-left: 3px solid transparent;
        }
        .admin-sidebar .nav-link:hover {
            background: #34495e;
            color: #fff;
        }
        .admin-sidebar .nav-link.active {
            background: #34495e;
            color: #fff;
            border-left: 3px solid #3498db;
        }
        .admin-main {
            margin-left: 200px;
            padding: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-color: #3498db;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }
        .content-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .badge-custom {
            font-size: 0.75rem;
            padding: 0.25em 0.5em;
        }
        @media (max-width: 768px) {
            .admin-sidebar { width: 60px; }
            .admin-sidebar .brand { padding: 15px 10px; font-size: 0.9rem; }
            .admin-sidebar .nav-link { padding: 10px; text-align: center; }
            .admin-main { margin-left: 60px; padding: 15px; }
        }
    </style>
</head>
<body>
    <aside class="admin-sidebar">
        <div class="brand"><i class="fas fa-user-shield me-2"></i>Urban Oasis</div>
        <nav class="nav flex-column w-100">
            <a href="dashboard.php" class="nav-link active"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a href="users.php" class="nav-link"><i class="fas fa-users me-2"></i>Users</a>
            <a href="properties.php" class="nav-link"><i class="fas fa-home me-2"></i>Properties</a>
            <a href="contact-messages.php" class="nav-link"><i class="fas fa-envelope me-2"></i>Messages<?php if ($unreadMessagesCount > 0): ?> <span class="badge bg-danger"><?php echo $unreadMessagesCount; ?></span><?php endif; ?></a>
            <a href="transactions.php" class="nav-link"><i class="fas fa-coins me-2"></i>Transactions</a>
            <hr class="my-2 opacity-25">
            <a href="../index.php" class="nav-link" target="_blank"><i class="fas fa-external-link-alt me-2"></i>View Website</a>
            <a href="login.php?logout=1" class="nav-link"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </aside>
    <main class="admin-main">
        <h2 class="fw-bold mb-4">Admin Dashboard</h2>
        
        <!-- Key Metrics -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card text-center" onclick="window.location.href='users.php'">
                    <h3 class="stat-number"><?php echo $userCount; ?></h3>
                    <p class="stat-label">Total Users</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center" onclick="window.location.href='properties.php'">
                    <h3 class="stat-number"><?php echo $propertyCount; ?></h3>
                    <p class="stat-label">Total Properties</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center" onclick="window.location.href='properties.php?filter_approval_status=pending'">
                    <h3 class="stat-number text-warning"><?php echo $pendingCount; ?></h3>
                    <p class="stat-label">Pending Approvals</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center" onclick="window.location.href='transactions.php'">
                    <h3 class="stat-number text-success">NPR <?php echo number_format($totalRevenue); ?></h3>
                    <p class="stat-label">Total Revenue</p>
                </div>
            </div>
        </div>

        <!-- Recent Listings -->
        <div class="content-section">
            <h4 class="section-title">Recent Listings for Review</h4>
            <?php if (!empty($recentPending)):
                foreach ($recentPending as $property):
            ?>
            <div class="activity-item d-flex justify-content-between align-items-center">
                <div>
                    <strong><?php echo htmlspecialchars($property['title']); ?></strong><br>
                    <small class="text-muted">by <?php echo htmlspecialchars($property['first_name'] . ' ' . $property['last_name']); ?> on <?php echo date('M j, Y', strtotime($property['created_at'])); ?></small>
                </div>
                <a href="../property-details.php?id=<?php echo $property['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
            </div>
            <?php 
                endforeach;
            else:
            ?>
                <p class="text-muted">No pending listings at the moment.</p>
            <?php endif; ?>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

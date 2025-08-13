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
require_once __DIR__ . '/../config/admin_utils.php';
require_once __DIR__ . '/../config/activity_tracker.php';
$pageTitle = "Manage Users";

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($user_id && $action === 'delete') {
        try {
            // Use a transaction for safety
            $pdo->beginTransaction();
            
            // Delete all related data first
            $stmt = $pdo->prepare("DELETE pf FROM property_features pf INNER JOIN properties p ON pf.property_id = p.id WHERE p.user_id = ?");
            $stmt->execute([$user_id]);
            
            $stmt = $pdo->prepare("DELETE FROM properties WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Finally, delete the user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $pdo->commit();
            $success = 'User and all associated content deleted.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error deleting user. Please try again.';
        }
    }
}

// Handle search
$search = $_GET['search'] ?? '';
$whereClause = $search ? "WHERE (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search)" : '';

// Fetch all users with essential data
$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email, u.created_at, 
           COUNT(p.id) as property_count
    FROM users u
    LEFT JOIN properties p ON u.id = p.user_id
    $whereClause
    GROUP BY u.id
    ORDER BY u.created_at DESC
");

if ($search) {
    $stmt->bindValue(':search', '%' . $search . '%');
}

$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics using proper activity tracking
$totalUsers = count($users);
$activeUsersCount = getActiveUsersCount($pdo, 1); // Users active within last 1 minute
$success = isset($success) ? $success : '';
$error = isset($error) ? $error : '';

// Get unread messages count for navigation badge
$unreadMessagesCount = getUnreadMessagesCount($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Users | Urban Oasis</title>
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
        .table-users {
            background: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            overflow: hidden;
        }
        .table thead {
            background: #e9ecef;
            color: #212529;
            font-weight: 600;
        }
        .table td, .table th {
            vertical-align: middle;
        }
        .badge-active {
            background-color: #d4edda !important;
            color: #155724 !important;
        }
        .badge-inactive {
            background-color: #f8d7da !important;
            color: #721c24 !important;
        }
        .action-btn {
            border: 1px solid #dee2e6;
            background: white;
            color: #495057;
            font-size: 0.8rem;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .action-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <aside class="admin-sidebar">
        <div class="brand"><i class="fas fa-user-shield me-2"></i>Urban Oasis</div>
        <nav class="nav flex-column w-100">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a href="users.php" class="nav-link active"><i class="fas fa-users me-2"></i>Users</a>
            <a href="properties.php" class="nav-link"><i class="fas fa-home me-2"></i>Properties</a>
            <a href="contact-messages.php" class="nav-link"><i class="fas fa-envelope me-2"></i>Messages<?php if ($unreadMessagesCount > 0): ?> <span class="badge bg-danger"><?php echo $unreadMessagesCount; ?></span><?php endif; ?></a>
            <a href="transactions.php" class="nav-link"><i class="fas fa-coins me-2"></i>Transactions</a>
            <hr class="my-2 opacity-25">
            <a href="../index.php" class="nav-link" target="_blank"><i class="fas fa-external-link-alt me-2"></i>View Website</a>
            <a href="login.php?logout=1" class="nav-link"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </aside>
    <main class="admin-main">
        <h2 class="fw-bold mb-4">Manage Users</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Search Form -->
        <div class="stats-card mb-4">
            <form method="GET" class="mb-3">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="search" class="form-label">Search Users</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by name, email, phone, or city...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <a href="users.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </div>
                <?php if (!empty($search)): ?>
                <div class="mt-2">
                    <small class="text-muted">Showing results for: "<?php echo htmlspecialchars($search); ?>"</small>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- User Statistics -->
        <div class="stats-card">
            <h5><i class="fas fa-users me-2"></i>User Statistics</h5>
            <div class="row">
                <div class="col-md-3">
                    <div class="text-center">
                        <h4 class="text-primary"><?php echo $totalUsers; ?></h4>
                        <small class="text-muted">Total Users</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h4 class="text-success"><?php echo $activeUsersCount; ?></h4>
                        <small class="text-muted">Active Users</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h4 class="text-warning"><?php echo array_sum(array_column($users, 'property_count')); ?></h4>
                        <small class="text-muted">Total Properties</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive table-users p-4">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User Info</th>
                        <th>Properties</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small><br>
                            <small class="text-muted">Joined: <?php echo date('M j, Y', strtotime($user['created_at'])); ?></small>
                        </td>
                        <td>
                            <span class="badge bg-primary"><?php echo $user['property_count']; ?> listings</span>
                        </td>
                        <td>
                            <button class="action-btn view" title="View User Properties" onclick="viewUserProperties(<?php echo $user['id']; ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn delete" title="Delete User" onclick="deleteUser(<?php echo $user['id']; ?>, <?php echo $user['property_count']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewUserProperties(userId) {
            // Open user properties in new tab
            window.open('../properties.php?user=' + userId, '_blank');
        }

        function deactivateUser(userId) {
            if (confirm(`Are you sure you want to deactivate this user?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="action" value="deactivate">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function activateUser(userId) {
            if (confirm(`Are you sure you want to activate this user?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="action" value="activate">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteUser(userId, propertyCount) {
            const message = propertyCount > 0 
                ? `Are you sure you want to delete this user? This will also delete ${propertyCount} properties and all associated features. This action cannot be undone.`
                : `Are you sure you want to delete this user? This action cannot be undone.`;
            
            if (confirm(message)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="action" value="delete">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

    </script>
</body>
</html> 
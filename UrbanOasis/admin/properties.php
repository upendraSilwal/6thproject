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
require_once __DIR__ . '/../config/property_utils.php';
$pageTitle = "Manage Properties";

// Handle property actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $property_id = intval($_POST['property_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($property_id && in_array($action, ['delete', 'approve', 'reject'])) {
        try {
            if ($action === 'delete') {
                // Delete property and its features
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM property_features WHERE property_id = ?");
                $stmt->execute([$property_id]);
                $stmt = $pdo->prepare("DELETE FROM properties WHERE id = ?");
                $stmt->execute([$property_id]);
                $pdo->commit();
                $success = 'Property deleted successfully!';
            } elseif ($action === 'approve' || $action === 'reject') {
                // Approve or reject property
                $approval_status = ($action === 'approve') ? 'approved' : 'rejected';
                $stmt = $pdo->prepare("UPDATE properties SET approval_status = ?, approved_by = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$approval_status, $_SESSION['admin_id'], $property_id]);
                $success = 'Property ' . $approval_status . ' successfully!';
            }
        } catch (Exception $e) {
            if ($action === 'delete') {
                $pdo->rollBack();
            }
            error_log('Admin property action error: ' . $e->getMessage());
            $error = 'An error occurred while processing the request.';
        }
    }
}

// Handle search and filtering
$search = $_GET['search'] ?? '';
$filter_property_type = $_GET['filter_property_type'] ?? '';
$filter_listing_type = $_GET['filter_listing_type'] ?? '';
$filter_approval_status = $_GET['filter_approval_status'] ?? '';

// Build query based on filters
$whereClause = [];
$params = [];

if (!empty($search)) {
    $whereClause[] = "(p.title LIKE ? OR p.location LIKE ? OR p.city LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($filter_property_type)) {
    $whereClause[] = "p.property_type = ?";
    $params[] = $filter_property_type;
}

if (!empty($filter_listing_type)) {
    $whereClause[] = "p.listing_type = ?";
    $params[] = $filter_listing_type;
}

if (!empty($filter_approval_status)) {
    $whereClause[] = "p.approval_status = ?";
    $params[] = $filter_approval_status;
}

$whereSQL = !empty($whereClause) ? 'WHERE ' . implode(' AND ', $whereClause) : '';

// Enhanced property query with user information, feature count, expiry status, and scheduling info
$stmt = $pdo->prepare("
    SELECT p.*, 
           u.first_name, 
           u.last_name,
           u.email as user_email,
           COUNT(pf.id) as feature_count,
           CASE 
               WHEN p.expires_at < NOW() THEN 'expired'
               WHEN p.expires_at < DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 'expiring_soon'
               ELSE 'active'
           END as expiry_status,
           DATEDIFF(p.expires_at, NOW()) as days_until_expiry,
           CASE 
               WHEN p.schedule_listing = 1 AND p.available_from IS NOT NULL 
                    AND CONCAT(p.available_from, ' ', p.schedule_time) > NOW() 
               THEN 'scheduled'
               ELSE 'published'
           END as publication_status
    FROM properties p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN property_features pf ON p.id = pf.property_id
    $whereSQL
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread messages count for navigation badge
$unreadMessagesCount = getUnreadMessagesCount($pdo);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Properties | Urban Oasis</title>
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
        .table-properties {
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
            margin: 0 2px;
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
        .feature-badge {
            font-size: 0.75rem;
            margin: 1px;
        }
        .property-details {
            max-width: 250px;
        }
        .property-details .detail-item {
            margin-bottom: 0.3rem;
            font-size: 0.85rem;
        }
        .property-details .detail-label {
            font-weight: 600;
            color: #6c757d;
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
            <a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a href="users.php" class="nav-link"><i class="fas fa-users me-2"></i>Users</a>
            <a href="properties.php" class="nav-link active"><i class="fas fa-home me-2"></i>Properties</a>
            <a href="contact-messages.php" class="nav-link"><i class="fas fa-envelope me-2"></i>Messages<?php if ($unreadMessagesCount > 0): ?> <span class="badge bg-danger"><?php echo $unreadMessagesCount; ?></span><?php endif; ?></a>
            <a href="transactions.php" class="nav-link"><i class="fas fa-coins me-2"></i>Transactions</a>
            <hr class="my-2 opacity-25">
            <a href="../index.php" class="nav-link" target="_blank"><i class="fas fa-external-link-alt me-2"></i>View Website</a>
            <a href="login.php?logout=1" class="nav-link"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </aside>
    <main class="admin-main">
        <h2 class="fw-bold mb-4">Manage Properties</h2>
        
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
                    <div class="col-md-6">
                        <label for="search" class="form-label">Search Properties</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by title, location, or owner...">
                    </div>
                    <div class="col-md-3">
                        <label for="filter_approval_status" class="form-label">Status</label>
                        <select class="form-select" id="filter_approval_status" name="filter_approval_status">
                            <option value="">All</option>
                            <option value="pending" <?php echo $filter_approval_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $filter_approval_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $filter_approval_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                </div>
                <?php if (!empty($search) || !empty($filter_approval_status)): ?>
                <div class="mt-2">
                    <a href="properties.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="table-responsive table-properties p-4">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Property</th>
                        <th>Owner</th>
                        <th>Status</th>
                        <th>Expiry</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($properties as $property): ?>
                    <tr>
                        <td><?php echo $property['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($property['title']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($property['city'] . ', ' . $property['location']); ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($property['first_name'] . ' ' . $property['last_name']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($property['user_email']); ?></small>
                        </td>
                        <td>
                            <?php if ($property['approval_status'] === 'pending'): ?>
                                <span class="badge bg-warning">Pending</span>
                            <?php elseif ($property['approval_status'] === 'approved'): ?>
                                <span class="badge bg-success">Approved</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Rejected</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($property['expiry_status'] === 'expired'): ?>
                                <span class="badge bg-danger">Expired</span>
                                <small class="d-block text-muted"><?php echo date('M d, Y', strtotime($property['expires_at'])); ?></small>
                            <?php elseif ($property['expiry_status'] === 'expiring_soon'): ?>
                                <span class="badge bg-warning"><?php echo $property['days_until_expiry']; ?> days</span>
                                <small class="d-block text-muted">Expires: <?php echo date('M d, Y', strtotime($property['expires_at'])); ?></small>
                            <?php else: ?>
                                <span class="badge bg-success"><?php echo $property['days_until_expiry']; ?> days</span>
                                <small class="d-block text-muted">Expires: <?php echo date('M d, Y', strtotime($property['expires_at'])); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="../property-details.php?id=<?php echo $property['id']; ?>" class="action-btn view" title="View Details" target="_blank">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button class="action-btn edit" title="Edit Property" onclick="editProperty(<?php echo $property['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($property['approval_status'] === 'pending'): ?>
                                <button class="action-btn approve" title="Approve" onclick="approveProperty(<?php echo $property['id']; ?>, '<?php echo htmlspecialchars($property['title']); ?>')">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="action-btn reject" title="Reject" onclick="rejectProperty(<?php echo $property['id']; ?>, '<?php echo htmlspecialchars($property['title']); ?>')">
                                    <i class="fas fa-times"></i>
                                </button>
                            <?php endif; ?>
                            <button class="action-btn delete" title="Delete" onclick="deleteProperty(<?php echo $property['id']; ?>, '<?php echo htmlspecialchars($property['title']); ?>')">
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
        function editProperty(id) {
            // Redirect to edit page (to be implemented)
            window.open('../edit-property.php?id=' + id, '_blank');
        }

        function approveProperty(id, title) {
            if (confirm(`Are you sure you want to approve "${title}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="property_id" value="${id}">
                    <input type="hidden" name="action" value="approve">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function rejectProperty(id, title) {
            if (confirm(`Are you sure you want to reject "${title}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="property_id" value="${id}">
                    <input type="hidden" name="action" value="reject">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteProperty(id, title) {
            if (confirm(`Are you sure you want to delete "${title}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="property_id" value="${id}">
                    <input type="hidden" name="action" value="delete">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html> 
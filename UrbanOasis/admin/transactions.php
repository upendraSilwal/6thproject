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
$pageTitle = "Credit Transactions";

// Handle search and filtering
$search = $_GET['search'] ?? '';

// Build query based on filters
$whereClause = [];
$params = [];

if (!empty($search)) {
    $whereClause[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR ct.description LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

$whereSQL = !empty($whereClause) ? 'WHERE ' . implode(' AND ', $whereClause) : '';

// Get all credit transactions with user information
$stmt = $pdo->prepare("
    SELECT ct.*, u.first_name, u.last_name, u.email
    FROM credit_transactions ct
    LEFT JOIN users u ON ct.user_id = u.id
    $whereSQL
    ORDER BY ct.created_at DESC
");
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalRevenue = $pdo->query("SELECT SUM(amount_paid) FROM credit_transactions WHERE transaction_type = 'purchase'")->fetchColumn() ?: 0;
$totalCreditsIssued = $pdo->query("SELECT SUM(credits) FROM credit_transactions WHERE transaction_type IN ('purchase', 'bonus')")->fetchColumn() ?: 0;
$totalCreditsUsed = $pdo->query("SELECT SUM(credits) FROM credit_transactions WHERE transaction_type = 'usage'")->fetchColumn() ?: 0;

// Get unread messages count for navigation badge
$unreadMessagesCount = getUnreadMessagesCount($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Transactions | Urban Oasis</title>
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
        .table-transactions {
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
        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
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
            <a href="properties.php" class="nav-link"><i class="fas fa-home me-2"></i>Properties</a>
            <a href="contact-messages.php" class="nav-link"><i class="fas fa-envelope me-2"></i>Messages<?php if ($unreadMessagesCount > 0): ?> <span class="badge bg-danger"><?php echo $unreadMessagesCount; ?></span><?php endif; ?></a>
            <a href="transactions.php" class="nav-link active"><i class="fas fa-coins me-2"></i>Transactions</a>
            <hr class="my-2 opacity-25">
            <a href="../index.php" class="nav-link" target="_blank"><i class="fas fa-external-link-alt me-2"></i>View Website</a>
            <a href="login.php?logout=1" class="nav-link"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </aside>
    <main class="admin-main">
        <h2 class="fw-bold mb-4">Credit Transactions</h2>
        
        <!-- Search Form -->
        <div class="stats-card mb-4">
            <form method="GET" class="mb-3">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="search" class="form-label">Search Transactions</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by user name, email, or description...">
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
                            <a href="transactions.php" class="btn btn-outline-secondary">
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
        
        <!-- Transaction Statistics -->
        <div class="stats-card">
            <h5><i class="fas fa-chart-line me-2"></i>Transaction Summary</h5>
            <div class="row">
                <div class="col-md-4">
                    <div class="text-center">
                        <h4 class="text-success">NPR <?php echo number_format($totalRevenue); ?></h4>
                        <small class="text-muted">Total Revenue</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <h4 class="text-primary"><?php echo $totalCreditsIssued; ?></h4>
                        <small class="text-muted">Credits Issued</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <h4 class="text-warning"><?php echo $totalCreditsUsed; ?></h4>
                        <small class="text-muted">Credits Used</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive table-transactions p-4">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Credits</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Description</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo $transaction['id']; ?></td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($transaction['email']); ?></small>
                            </div>
                        </td>
                        <td>
                            <?php
                            $badgeClass = '';
                            switch ($transaction['transaction_type']) {
                                case 'purchase': $badgeClass = 'bg-success'; break;
                                case 'usage': $badgeClass = 'bg-warning'; break;
                                case 'bonus': $badgeClass = 'bg-info'; break;
                                case 'refund': $badgeClass = 'bg-danger'; break;
                                default: $badgeClass = 'bg-secondary';
                            }
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($transaction['transaction_type']); ?></span>
                        </td>
                        <td>
                            <strong><?php echo ($transaction['transaction_type'] == 'usage' ? '-' : '+') . $transaction['credits']; ?></strong>
                        </td>
                        <td>
                            <?php if ($transaction['amount_paid']): ?>
                                NPR <?php echo number_format($transaction['amount_paid']); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($transaction['payment_method']): ?>
                                <span class="badge bg-light text-dark"><?php echo ucfirst($transaction['payment_method']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($transaction['description']); ?></small>
                        </td>
                        <td>
                            <small><?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($transactions)): ?>
                <div class="text-center py-4">
                    <p class="text-muted">No transactions found.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

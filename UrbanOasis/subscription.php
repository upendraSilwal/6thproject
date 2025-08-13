<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT listing_credits, free_listings_used, total_listings_created, first_name, last_name FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get recent credit transactions (exclude bonus transactions)
$stmt = $pdo->prepare('SELECT * FROM credit_transactions WHERE user_id = ? AND transaction_type != "bonus" ORDER BY created_at DESC LIMIT 10');
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();

$pageTitle = 'My Credits';
require_once 'includes/header.php';
?>
<main class="container py-5">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="fas fa-coins me-2"></i>My Listing Credits</h3>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-4">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Available Credits</h5>
                                    <h2 class="text-primary"><?php echo $user['listing_credits']; ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Free Listings</h5>
                                    <h2 class="text-success"><?php echo max(0, 1 - $user['free_listings_used']); ?>/1</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Total Created</h5>
                                    <h2 class="text-info"><?php echo $user['total_listings_created']; ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($user['listing_credits'] > 0 || (1 - $user['free_listings_used']) > 0): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-1"></i>You can create new property listings!
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>You need to purchase credits to create more listings.
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center">
                        <a href="pricing.php" class="btn btn-primary me-2"><i class="fas fa-shopping-cart me-1"></i>Buy More Credits</a>
                        <a href="add_property.php" class="btn btn-success"><i class="fas fa-plus me-1"></i>Add Property</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Transactions</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($transactions)): ?>
                        <p class="text-muted">No transactions yet.</p>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 border-bottom">
                                <div>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($transaction['created_at'])); ?></small>
                                    <br>
                                    <span class="badge bg-<?php echo $transaction['transaction_type'] == 'purchase' ? 'success' : ($transaction['transaction_type'] == 'usage' ? 'warning' : 'info'); ?>">
                                        <?php echo ucfirst($transaction['transaction_type']); ?>
                                    </span>
                                </div>
                                <div class="text-end">
                                    <strong><?php echo ($transaction['transaction_type'] == 'usage' ? '-' : '+') . $transaction['credits']; ?></strong>
                                    <?php if ($transaction['amount_paid']): ?>
                                        <br><small>Rs. <?php echo $transaction['amount_paid']; ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require_once 'includes/footer.php'; ?>

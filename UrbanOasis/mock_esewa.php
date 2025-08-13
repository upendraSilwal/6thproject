<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$package = $_GET['package'] ?? '';
$credits = 0;
$amount = 0;
$package_name = '';

switch ($package) {
    case 'single':
        $credits = 5;  // 1 listing = 5 credits
        $amount = 150;
        $package_name = 'Single Listing';
        break;
    case 'bundle5':
        $credits = 25; // 5 listings = 25 credits
        $amount = 600;
        $package_name = '5 Listings Bundle';
        break;
    case 'bundle10':
        $credits = 50; // 10 listings = 50 credits
        $amount = 1050;
        $package_name = '10 Listings Bundle';
        break;
    case 'basic':
        $credits = 5;
        $amount = 99;
        $package_name = 'Basic Package';
        break;
    case 'pro':
        $credits = 20;
        $amount = 399;
        $package_name = 'Pro Package';
        break;
    case 'elite':
        $credits = 25;
        $amount = 999;
        $package_name = 'Elite Package';
        break;
    default:
        header('Location: pricing.php');
        exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Add credits to user account
        $stmt = $pdo->prepare("UPDATE users SET listing_credits = listing_credits + ? WHERE id = ?");
        $result = $stmt->execute([$credits, $user_id]);
        
        if (!$result) {
            throw new Exception('Failed to update user credits');
        }
        
        // Verify the update worked
        $stmt = $pdo->prepare("SELECT listing_credits FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $new_credits = $stmt->fetchColumn();
        
        // Record transaction
        $stmt = $pdo->prepare("INSERT INTO credit_transactions (user_id, transaction_type, credits, amount_paid, payment_method, description) VALUES (?, 'purchase', ?, ?, 'esewa', ?)");
        $result = $stmt->execute([$user_id, $credits, $amount, "Purchased $credits credits ($package_name) with eSewa"]);
        
        if (!$result) {
            throw new Exception('Failed to record transaction');
        }
        
        // Commit transaction
        $pdo->commit();

        $_SESSION['success_message'] = "Successfully purchased $credits listing credits! You now have $new_credits credits.";
        header('Location: my-properties.php');
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Payment failed: ' . $e->getMessage();
        header('Location: pricing.php');
        exit();
    }
}

$pageTitle = 'Mock eSewa Payment';
require_once 'includes/header.php';
?>
<main class="container" style="max-width: 500px; margin: 2rem auto;">
    <div class="card shadow-lg border-0">
        <div class="card-header bg-gradient-primary text-white text-center">
            <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Confirmation</h4>
        </div>
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <h5 class="text-muted"><?php echo $package_name; ?></h5>
                <h2 class="display-6 fw-bold text-primary"><?php echo $credits; ?> Credits</h2>
                    <p class="text-muted">5 credits allows you to list 1 property</p>
            </div>
            
            <div class="payment-details bg-light p-3 rounded mb-4">
                <div class="d-flex justify-content-between mb-2">
                    <span>Credits:</span>
                    <strong><?php echo $credits; ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Rate per listing:</span>
                    <strong>~<?php echo round($amount / $credits); ?> NPR</strong>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">Total Amount:</span>
                    <strong class="text-success"><?php echo $amount; ?> NPR</strong>
                </div>
            </div>
            
            <form method="POST">
                <button type="submit" class="btn btn-success w-100 btn-lg">
                    <i class="fas fa-check-circle me-2"></i>Complete Payment (Mock)
                </button>
            </form>
            
            <div class="text-center mt-3">
                <small class="text-muted">This is a mock payment system for demonstration purposes</small>
            </div>
        </div>
    </div>
</main>
<?php require_once 'includes/footer.php'; ?>

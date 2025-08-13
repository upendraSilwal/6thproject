<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please log in to renew properties.']);
    exit();
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;

if (!$property_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid property ID.']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if user owns the property and get current details
    $stmt = $pdo->prepare("SELECT p.*, u.listing_credits 
                          FROM properties p 
                          JOIN users u ON p.user_id = u.id 
                          WHERE p.id = ? AND p.user_id = ?");
    $stmt->execute([$property_id, $user_id]);
    $property = $stmt->fetch();
    
    if (!$property) {
        throw new Exception('Property not found or you do not have permission to renew it.');
    }
    
    // Check if user has enough credits (5 credits needed for renewal)
    if ($property['listing_credits'] < 5) {
        throw new Exception('You need at least 5 credits to renew this property. Please purchase more credits.');
    }
    
    // Calculate new expiry date (30 days from current expiry or now if already expired)
    $current_expiry = $property['expires_at'];
    $now = date('Y-m-d H:i:s');
    
    if ($current_expiry > $now) {
        // Property not expired yet, extend from current expiry
        $new_expiry = date('Y-m-d H:i:s', strtotime($current_expiry . ' +30 days'));
    } else {
        // Property already expired, extend from now
        $new_expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
    }
    
    // Update property with new expiry date and increment renewal count
    $stmt = $pdo->prepare("UPDATE properties 
                          SET expires_at = ?, 
                              renewal_count = renewal_count + 1,
                              is_active = 1,
                              updated_at = CURRENT_TIMESTAMP
                          WHERE id = ?");
    $stmt->execute([$new_expiry, $property_id]);
    
    // Deduct credits from user
    $stmt = $pdo->prepare("UPDATE users 
                          SET listing_credits = listing_credits - 5 
                          WHERE id = ?");
    $stmt->execute([$user_id]);
    
    // Record the transaction
    $stmt = $pdo->prepare("INSERT INTO credit_transactions 
                          (user_id, transaction_type, credits, description, property_id) 
                          VALUES (?, 'renewal', 5, ?, ?)");
    $description = 'Property renewal for 30 days - Property #' . $property_id;
    $stmt->execute([$user_id, $description, $property_id]);
    
    // Commit transaction
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Property renewed successfully for 30 days!',
        'new_expiry' => date('M d, Y', strtotime($new_expiry)),
        'credits_remaining' => $property['listing_credits'] - 5
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollBack();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

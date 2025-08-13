<?php
session_start();
require_once 'config/database.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: properties.php');
    exit();
}

// Get form data
$property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;
$sender_name = isset($_POST['sender_name']) ? trim($_POST['sender_name']) : '';
$sender_email = isset($_POST['sender_email']) ? trim($_POST['sender_email']) : '';
$sender_phone = isset($_POST['sender_phone']) ? trim($_POST['sender_phone']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Basic validation
$errors = [];

if (empty($property_id)) {
    $errors[] = 'Invalid property selected.';
}

if (empty($sender_name)) {
    $errors[] = 'Please enter your name.';
}

if (empty($sender_email) || !filter_var($sender_email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

// Phone is optional, but if provided, validate format
if (!empty($sender_phone) && !preg_match('/^[0-9]{10}$/', $sender_phone)) {
    $errors[] = 'Please enter a valid 10-digit phone number.';
}

if (empty($message)) {
    $errors[] = 'Please enter a message.';
}

// Verify property exists and is active
if (empty($errors)) {
    $stmt = $pdo->prepare("SELECT id, title, user_id, expires_at, is_active FROM properties WHERE id = ? AND is_active = 1 AND expires_at > NOW()");
    $stmt->execute([$property_id]);
    $property = $stmt->fetch();
    
    if (!$property) {
        $errors[] = 'Property not found or not available.';
    }
}

// Check if user is trying to send inquiry to their own property
if (empty($errors) && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $property['user_id']) {
    $errors[] = 'You cannot send an inquiry to your own property.';
}

// Rate limiting: Check if same email sent inquiry in last 5 minutes
if (empty($errors)) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM property_inquiries 
        WHERE sender_email = ? 
        AND property_id = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$sender_email, $property_id]);
    $recent_count = $stmt->fetchColumn();
    
    if ($recent_count > 0) {
        $errors[] = 'You have already sent an inquiry for this property recently. Please wait before sending another.';
    }
}

// If there are errors, redirect back with error message
if (!empty($errors)) {
    $_SESSION['inquiry_error'] = implode(' ', $errors);
    header("Location: property-details.php?id=$property_id");
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert inquiry into database
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    $stmt = $pdo->prepare("
        INSERT INTO property_inquiries 
        (property_id, sender_id, sender_name, sender_email, sender_phone, message) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $property_id,
        $user_id,
        $sender_name,
        $sender_email,
        $sender_phone,
        $message
    ]);
    
// Increment inquiry counter for the property with atomic update
$stmt = $pdo->prepare("UPDATE properties SET inquiry_count = inquiry_count + 1 WHERE id = ?");
$stmt->execute([$property_id]);
    
    // Commit transaction
    $pdo->commit();
    
    // Set success message
    $_SESSION['inquiry_success'] = 'Your inquiry has been sent successfully! The property owner will contact you soon.';
    
    // Redirect back to property details
    header("Location: property-details.php?id=$property_id&inquiry=sent");
    exit();
    
} catch (PDOException $e) {
    // Rollback transaction
    $pdo->rollBack();
    
    // Log error and show user-friendly message
    error_log("Inquiry submission error: " . $e->getMessage());
    $_SESSION['inquiry_error'] = 'There was an error sending your inquiry. Please try again later.';
    header("Location: property-details.php?id=$property_id");
    exit();
}
?>

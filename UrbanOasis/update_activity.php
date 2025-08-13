<?php
require_once 'config/database.php';
require_once 'config/session_utils.php';
require_once 'config/activity_tracker.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and update activity
if (isset($_SESSION['user_id'])) {
    $result = updateUserActivity($pdo, $_SESSION['user_id']);
    echo json_encode(['success' => $result]);
} else {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
}
?>

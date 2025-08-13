<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$property_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$property_id) {
    header('Location: my-properties.php?error=invalid');
    exit();
}

// Check if the property belongs to the user
$stmt = $pdo->prepare('SELECT id FROM properties WHERE id = ? AND user_id = ?');
$stmt->execute([$property_id, $user_id]);
$property = $stmt->fetch();

if (!$property) {
    header('Location: my-properties.php?error=notfound');
    exit();
}

try {
    $pdo->beginTransaction();
    // Delete property features
    $stmt = $pdo->prepare('DELETE FROM property_features WHERE property_id = ?');
    $stmt->execute([$property_id]);
    // Delete property
    $stmt = $pdo->prepare('DELETE FROM properties WHERE id = ?');
    $stmt->execute([$property_id]);
    $pdo->commit();
    header('Location: my-properties.php?success=deleted');
    exit();
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: my-properties.php?error=deletefail');
    exit();
} 
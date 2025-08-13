<?php
/**
 * Utility functions for user management
 */

/**
 * Get user by ID with all details
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return array User data
 */
function getUserById($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Verify user password
 * @param string $inputPassword Input password
 * @param string $hashedPassword Hashed password from DB
 * @return bool True if password matches
 */
function verifyUserPassword($inputPassword, $hashedPassword) {
    return password_verify($inputPassword, $hashedPassword);
}

/**
 * Hash user password
 * @param string $password Plain password
 * @return string Hashed password
 */
function hashUserPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

?>

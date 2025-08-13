<?php
/**
 * User Activity Tracker
 * Tracks when users are last active for "online" status
 */

// Prevent direct access
if (!defined('URBAN_OASIS_APP')) {
    exit('Direct access not allowed');
}

/**
 * Update user's last activity timestamp
 * Call this on every page load for logged-in users
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return bool Success status
 */
function updateUserActivity($pdo, $userId) {
    if (!$userId) return false;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
        return $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log("Failed to update user activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get count of active users (online within last 15 minutes)
 * 
 * @param PDO $pdo Database connection
 * @param int $minutesThreshold Minutes to consider user as active (default: 15)
 * @return int Number of active users
 */
function getActiveUsersCount($pdo, $minutesThreshold = 15) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active_count 
            FROM users 
            WHERE last_activity >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$minutesThreshold]);
        $result = $stmt->fetch();
        return (int) $result['active_count'];
    } catch (Exception $e) {
        error_log("Failed to get active users count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get list of active users with details
 * 
 * @param PDO $pdo Database connection
 * @param int $minutesThreshold Minutes to consider user as active (default: 15)
 * @return array Array of active users
 */
function getActiveUsers($pdo, $minutesThreshold = 15) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, email, last_activity,
                   TIMESTAMPDIFF(MINUTE, last_activity, NOW()) as minutes_ago
            FROM users 
            WHERE last_activity >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY last_activity DESC
        ");
        $stmt->execute([$minutesThreshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to get active users: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if a specific user is currently active
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID to check
 * @param int $minutesThreshold Minutes to consider user as active (default: 15)
 * @return bool True if user is active
 */
function isUserActive($pdo, $userId, $minutesThreshold = 15) {
    if (!$userId) return false;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as is_active 
            FROM users 
            WHERE id = ? AND last_activity >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$userId, $minutesThreshold]);
        $result = $stmt->fetch();
        return (bool) $result['is_active'];
    } catch (Exception $e) {
        error_log("Failed to check user activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's last activity status with human-readable format
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return array Activity status with 'is_active', 'last_seen', 'status_text'
 */
function getUserActivityStatus($pdo, $userId) {
    if (!$userId) return ['is_active' => false, 'last_seen' => null, 'status_text' => 'Never'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT last_activity,
                   TIMESTAMPDIFF(MINUTE, last_activity, NOW()) as minutes_ago
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        if (!$result || !$result['last_activity']) {
            return ['is_active' => false, 'last_seen' => null, 'status_text' => 'Never'];
        }
        
        $minutesAgo = (int) $result['minutes_ago'];
        $isActive = $minutesAgo <= 15;
        
        // Generate human-readable status
        if ($minutesAgo < 1) {
            $statusText = 'Just now';
        } elseif ($minutesAgo < 15) {
            $statusText = $minutesAgo . ' min ago';
        } elseif ($minutesAgo < 60) {
            $statusText = $minutesAgo . ' min ago';
        } elseif ($minutesAgo < 1440) { // Less than 24 hours
            $hoursAgo = floor($minutesAgo / 60);
            $statusText = $hoursAgo . ' hour' . ($hoursAgo > 1 ? 's' : '') . ' ago';
        } else {
            $daysAgo = floor($minutesAgo / 1440);
            $statusText = $daysAgo . ' day' . ($daysAgo > 1 ? 's' : '') . ' ago';
        }
        
        return [
            'is_active' => $isActive,
            'last_seen' => $result['last_activity'],
            'status_text' => $statusText,
            'minutes_ago' => $minutesAgo
        ];
    } catch (Exception $e) {
        error_log("Failed to get user activity status: " . $e->getMessage());
        return ['is_active' => false, 'last_seen' => null, 'status_text' => 'Unknown'];
    }
}
?>

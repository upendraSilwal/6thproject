<?php
// Admin utility functions

/**
 * Get count of unread contact messages
 */
function getUnreadMessagesCount($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE COALESCE(is_read, 0) = 0");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        // If column doesn't exist, add it and return total count as unread
        try {
            $pdo->exec("ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0");
            $stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages");
            return $stmt->fetchColumn();
        } catch (PDOException $e2) {
            return 0;
        }
    }
}
?>

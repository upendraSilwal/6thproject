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
$pageTitle = "Contact Messages";

// Handle message deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $messageId = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
    if ($stmt->execute([$messageId])) {
        $success = "Message deleted successfully.";
    } else {
        $error = "Failed to delete message.";
    }
}

// Handle marking message as read/unread
if (isset($_GET['toggle_read']) && is_numeric($_GET['toggle_read'])) {
    $messageId = $_GET['toggle_read'];
    // Add is_read column if it doesn't exist
    $pdo->exec("ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0");
    
    $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = NOT is_read WHERE id = ?");
    $stmt->execute([$messageId]);
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count
$totalMessages = $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
$totalPages = ceil($totalMessages / $limit);

// Fetch messages with pagination
$stmt = $pdo->prepare("
    SELECT id, name, email, subject, message, created_at,
           COALESCE(is_read, 0) as is_read
    FROM contact_messages 
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - Urban Oasis Admin</title>
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
        .message-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .message-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .message-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .message-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .unread {
            border-left: 4px solid #3498db;
        }
        .read {
            opacity: 0.8;
        }
        @media (max-width: 768px) {
            .admin-sidebar { width: 60px; }
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
            <a href="contact-messages.php" class="nav-link active"><i class="fas fa-envelope me-2"></i>Messages</a>
            <a href="transactions.php" class="nav-link"><i class="fas fa-coins me-2"></i>Transactions</a>
            <hr class="my-2 opacity-25">
            <a href="../index.php" class="nav-link" target="_blank"><i class="fas fa-external-link-alt me-2"></i>View Website</a>
            <a href="login.php?logout=1" class="nav-link"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </aside>
    
    <main class="admin-main">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Contact Messages</h2>
            <span class="badge bg-primary fs-6"><?php echo $totalMessages; ?> Total Messages</span>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (empty($messages)): ?>
            <div class="text-center py-5">
                <i class="fas fa-envelope-open-text fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No contact messages yet</h4>
                <p class="text-muted">Contact messages from the website will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <div class="message-card <?php echo $message['is_read'] ? 'read' : 'unread'; ?>">
                    <div class="message-header">
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="mb-1">
                                        <?php echo htmlspecialchars($message['subject']); ?>
                                        <?php if (!$message['is_read']): ?>
                                            <span class="badge bg-primary ms-2">New</span>
                                        <?php endif; ?>
                                    </h5>
                                    <div class="text-muted small">
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($message['name']); ?>
                                        <i class="fas fa-envelope ms-3 me-1"></i><?php echo htmlspecialchars($message['email']); ?>
                                        <i class="fas fa-clock ms-3 me-1"></i><?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="btn-group" role="group">
                                    <a href="?toggle_read=<?php echo $message['id']; ?>&page=<?php echo $page; ?>" 
                                       class="btn btn-sm btn-outline-secondary" 
                                       title="<?php echo $message['is_read'] ? 'Mark as unread' : 'Mark as read'; ?>">
                                        <i class="fas fa-<?php echo $message['is_read'] ? 'envelope' : 'envelope-open'; ?>"></i>
                                    </a>
                                    <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>?subject=Re: <?php echo urlencode($message['subject']); ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="Reply via email">
                                        <i class="fas fa-reply"></i>
                                    </a>
                                    <a href="?delete=<?php echo $message['id']; ?>&page=<?php echo $page; ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       title="Delete message"
                                       onclick="return confirm('Are you sure you want to delete this message?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="message-content">
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Messages pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

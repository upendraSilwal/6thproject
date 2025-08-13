<?php
// Prevent browser caching for authenticated pages
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
require_once '../config/database.php';
if (isset($_GET['logout'])) {
    // Only unset admin session variables, preserve user session
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_name']);
    header('Location: login.php');
    exit();
}
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}
$pageTitle = "Admin Login";
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['adminEmail']);
    $password = $_POST['adminPassword'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        // Debug: Check what's in the database
        $debug_stmt = $pdo->prepare("SELECT username, email, password FROM admins");
        $debug_stmt->execute();
        $admins = $debug_stmt->fetchAll();
        
        // Allow login with either username or email
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE (email = ? OR username = ?) AND password = SHA1(?)");
        $stmt->execute([$email, $email, $password]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid username/email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Urban Oasis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .admin-login-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-width: 400px;
            width: 100%;
            padding: 40px 30px;
            border: 1px solid #e9ecef;
        }
        .admin-login-card h3 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
        }
        .btn-admin-login {
            background: #2c3e50;
            border: none;
            border-radius: 6px;
            padding: 12px 0;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .btn-admin-login:hover {
            background: #34495e;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(44, 62, 80, 0.2);
        }
        .brand-header {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
        }
        .brand-header i {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: #3498db;
        }
    </style>
</head>
<body>
    <div class="admin-login-card mx-auto">
        <div class="brand-header">
            <i class="fas fa-user-shield"></i>
            <h3>Urban Oasis Admin</h3>
            <p class="text-muted mb-0">Administrative Panel Access</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 mb-3" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="adminEmail" name="adminEmail" placeholder="Username or Email" required>
                <label for="adminEmail"><i class="fas fa-user me-2"></i>Username or Email</label>
            </div>
            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="adminPassword" name="adminPassword" placeholder="Password" required>
                <label for="adminPassword"><i class="fas fa-lock me-2"></i>Password</label>
            </div>
            <button type="submit" class="btn btn-primary btn-admin-login w-100">Login</button>
        </form>
        
        <!-- Demo Credentials for Local Development -->
        <div class="mt-4">
            <div class="demo-credentials">
                <h6 class="text-center"><i class="fas fa-info-circle me-2"></i>Demo Credentials</h6>
                <div class="demo-credentials">
                    <div class="mb-2">
                        <strong>Username:</strong> <code class="text-primary">admin</code>
                        <button class="btn btn-outline-secondary btn-sm ms-2" onclick="copyToClipboard('admin')" title="Copy Username">
                            <i class="fas fa-copy fa-xs"></i>
                        </button>
                    </div>
                    <div class="mb-2">
                        <strong>Password:</strong> <code class="text-primary">hello</code>
                        <button class="btn btn-outline-secondary btn-sm ms-2" onclick="copyToClipboard('hello')" title="Copy Password">
                            <i class="fas fa-copy fa-xs"></i>
                        </button>
                    </div>
                    <button class="btn btn-success btn-sm mt-2" onclick="fillCredentials()">
                        <i class="fas fa-magic me-1"></i>Auto Fill
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Copy to clipboard function
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show temporary feedback
                const btn = event.target.closest('button');
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check fa-xs"></i>';
                btn.classList.add('btn-success');
                btn.classList.remove('btn-outline-secondary');
                
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-secondary');
                }, 1000);
            });
        }
        
        // Auto fill credentials
        function fillCredentials() {
            document.getElementById('adminEmail').value = 'admin';
            document.getElementById('adminPassword').value = 'hello';
            
            // Show feedback
            const btn = event.target;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Filled!';
            btn.classList.add('btn-success');
            
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-success');
            }, 1500);
        }
        
        // Add some hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const codeElements = document.querySelectorAll('code');
            codeElements.forEach(code => {
                code.style.cursor = 'pointer';
                code.onclick = function() {
                    copyToClipboard(this.textContent);
                };
            });
        });
    </script>
    
    <style>
        .demo-credentials {
            font-size: 0.9rem;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
        }
        
        .demo-credentials code {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        
        .demo-credentials code:hover {
            background-color: #e3f2fd;
            border-color: #3498db;
        }
        
        .demo-credentials .btn-sm {
            padding: 0.2rem 0.4rem;
            font-size: 0.7rem;
        }
        
        .demo-credentials h6 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 15px;
        }
    </style>
</body>
</html>

<?php
session_start();
require_once 'config/database.php';
$pageTitle = "Register";

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Ensure $fieldErrors is always defined
if (!isset($fieldErrors)) {
    $fieldErrors = [
        'firstName' => '',
        'lastName' => '',
        'email' => '',
        'phone' => '',
        'password' => '',
        'confirmPassword' => ''
    ];
}

// Handle registration form submission
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    
    // Validation
    if (empty($firstName)) {
        $fieldErrors['firstName'] = 'First name is required.';
    }
    if (empty($lastName)) {
        $fieldErrors['lastName'] = 'Last name is required.';
    }
    if (empty($email)) {
        $fieldErrors['email'] = 'Email is required.';
    }
    if (empty($phone)) {
        $fieldErrors['phone'] = 'Phone number is required.';
    }
    if (empty($password)) {
        $fieldErrors['password'] = 'Password is required.';
    }
    if (empty($confirmPassword)) {
        $fieldErrors['confirmPassword'] = 'Please confirm your password.';
    }
    if (!preg_match('/^\d{10}$/', $phone) && !empty($phone)) {
        $fieldErrors['phone'] = 'Phone number must be exactly 10 digits.';
    }
    if ($password !== $confirmPassword && !empty($password) && !empty($confirmPassword)) {
        $fieldErrors['confirmPassword'] = 'Passwords do not match.';
    }
    if (strlen($password) < 6 && !empty($password)) {
        $fieldErrors['password'] = 'Password must be at least 6 characters long.';
    }
    
    // Only proceed if no field errors
    $hasFieldError = false;
    foreach ($fieldErrors as $err) {
        if (!empty($err)) {
            $hasFieldError = true;
            break;
        }
    }
    if (!$hasFieldError) {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $fieldErrors['email'] = 'Email address already registered.';
        } else {
            // Check if phone already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $fieldErrors['phone'] = 'Phone number already registered.';
            } else {
                // Hash password and insert user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$firstName, $lastName, $email, $phone, $hashedPassword])) {
                    // Auto-login and redirect
                    $userId = $pdo->lastInsertId();
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Urban Oasis</title>
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
            padding: 20px 0;
        }
        .register-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-width: 500px;
            width: 100%;
            padding: 40px 30px;
            border: 1px solid #e9ecef;
        }
        .register-card h3 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
        }
        .btn-register {
            background: #2c3e50;
            border: none;
            border-radius: 6px;
            padding: 12px 0;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .btn-register:hover {
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
    <div class="register-card mx-auto">
        <div class="brand-header">
            <i class="fas fa-home"></i>
            <h3>Join Urban Oasis</h3>
            <p class="text-muted mb-0">Create your account</p>
        </div>
                    <?php if ($error): ?>
                        <div class="alert alert-danger text-center" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success text-center" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            <br><a href="login.php" class="alert-link">Sign in now</a>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control<?php echo !empty($fieldErrors['firstName']) ? ' is-invalid' : ''; ?>" id="firstName" name="firstName" placeholder="First Name" required value="<?php echo isset($_POST['firstName']) ? htmlspecialchars($_POST['firstName']) : ''; ?>">
                                    <label for="firstName">First Name</label>
                                    <div class="invalid-feedback"><?php echo $fieldErrors['firstName']; ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control<?php echo !empty($fieldErrors['lastName']) ? ' is-invalid' : ''; ?>" id="lastName" name="lastName" placeholder="Last Name" required value="<?php echo isset($_POST['lastName']) ? htmlspecialchars($_POST['lastName']) : ''; ?>">
                                    <label for="lastName">Last Name</label>
                                    <div class="invalid-feedback"><?php echo $fieldErrors['lastName']; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control<?php echo !empty($fieldErrors['email']) ? ' is-invalid' : ''; ?>" id="email" name="email" placeholder="name@example.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <label for="email"><i class="fas fa-envelope me-2"></i>Email</label>
                            <div class="invalid-feedback"><?php echo $fieldErrors['email']; ?></div>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control<?php echo !empty($fieldErrors['phone']) ? ' is-invalid' : ''; ?>" id="phone" name="phone" placeholder="Phone Number" required value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            <label for="phone"><i class="fas fa-phone me-2"></i>Phone</label>
                            <div class="invalid-feedback"><?php echo $fieldErrors['phone']; ?></div>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control<?php echo !empty($fieldErrors['password']) ? ' is-invalid' : ''; ?>" id="password" name="password" placeholder="Password" required>
                            <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                            <div class="password-strength mt-2" id="passwordStrength"></div>
                            <div class="invalid-feedback"><?php echo $fieldErrors['password']; ?></div>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control<?php echo !empty($fieldErrors['confirmPassword']) ? ' is-invalid' : ''; ?>" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password" required>
                            <label for="confirmPassword"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                            <div class="invalid-feedback"><?php echo $fieldErrors['confirmPassword']; ?></div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-register w-100 mb-3">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </button>
                    </form>
                    
                    <div class="text-center">
                        <p class="mb-0">Already have an account? <a href="login.php" class="text-decoration-none">Sign in</a></p>
                    </div>
    </div>
    
</body>
</html>

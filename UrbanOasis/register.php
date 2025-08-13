<?php
session_start();
require_once 'config/database.php';
require_once 'config/user_utils.php';
$pageTitle = "Register";

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Handle registration form submission
$error = '';
$success = '';
$formData = []; // Initialize form data array

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Store form data for preservation on errors
    $formData = [
        'firstName' => trim($_POST['firstName']),
        'lastName' => trim($_POST['lastName']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone'])
    ];
    
    $firstName = $formData['firstName'];
    $lastName = $formData['lastName'];
    $email = $formData['email'];
    $phone = $formData['phone'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    
    // Validation
    if (empty($firstName)) {
        $error = 'First name is required.';
    }
    if (empty($lastName)) {
        $error = 'Last name is required.';
    }
    if (empty($email)) {
        $error = 'Email is required.';
    }
    if (empty($phone)) {
        $error = 'Phone number is required.';
    }
    if (empty($password)) {
        $error = 'Password is required.';
    }
    if (empty($confirmPassword)) {
        $error = 'Please confirm your password.';
    }
    if (!preg_match('/^\d{10}$/', $phone) && !empty($phone)) {
        $error = 'Phone number must be exactly 10 digits.';
    }
    if ($password !== $confirmPassword && !empty($password) && !empty($confirmPassword)) {
        $error = 'Passwords do not match.';
    }
    if (strlen($password) < 6 && !empty($password)) {
        $error = 'Password must be at least 6 characters long.';
    }
    
    // Only proceed if no errors
    if (empty($error)) {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email address already registered.';
        } else {
            // Check if phone number already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $error = 'Phone number already registered.';
            }
        }
        
        // Only proceed if no phone/email conflicts
        if (empty($error)) {
            // Hash password and insert user
            $hashedPassword = hashUserPassword($password);
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password, phone_verified) VALUES (?, ?, ?, ?, ?, 0)");
            if ($stmt->execute([$firstName, $lastName, $email, $phone, $hashedPassword])) {
                // Log the user in immediately and redirect to the main website
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['first_name'] = $firstName;
                $_SESSION['email'] = $email;
                header('Location: index.php');
                exit();
            } else {
                $error = 'Registration failed. Please try again.';
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            overflow: hidden;
        }
        .register-container {
            display: flex;
            height: 100vh;
        }
        .register-left {
            flex: 1;
            background: linear-gradient(135deg, rgba(44, 62, 80, 0.8), rgba(52, 152, 219, 0.6)), 
                        url('https://images.unsplash.com/photo-1578934656091-3bdb7baee640?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
        }
        .brand-showcase {
            text-align: center;
            z-index: 2;
        }
        .brand-showcase h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .brand-showcase p {
            font-size: 1.25rem;
            font-weight: 300;
            opacity: 0.9;
            max-width: 400px;
            margin: 0 auto;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }
        .brand-showcase .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #3498db;
        }
        .register-right {
            flex: 1;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .register-form {
            width: 100%;
            max-width: 400px;
        }
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .register-header h2 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .register-header p {
            color: #6c757d;
            font-size: 0.95rem;
        }
        .form-floating {
            margin-bottom: 1.5rem;
        }
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem 0.75rem;
            font-size: 1rem;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
        }
        .btn-register {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            border: none;
            border-radius: 8px;
            padding: 0.875rem;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            color: white;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(44, 62, 80, 0.3);
            background: linear-gradient(135deg, #34495e, #2c3e50);
        }
        .register-footer {
            text-align: center;
        }
        .register-footer a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        .register-footer a:hover {
            text-decoration: underline;
        }
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 768px) {
            .register-container {
                flex-direction: column;
            }
            .register-left {
                min-height: 40vh;
            }
            .brand-showcase h1 {
                font-size: 2.5rem;
            }
            .brand-showcase .icon {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Left side - Branding -->
        <div class="register-left">
            <div class="brand-showcase">
                <div class="icon">
                    <i class="fas fa-building"></i>
                </div>
                <h1>Urban Oasis</h1>
                <p>Your journey to a new home begins here</p>
            </div>
        </div>
        
        <!-- Right side - Register Form -->
        <div class="register-right">
            <div class="register-form">
                <div class="register-header">
                    <h2>Create Your Account</h2>
                    <p>Sign up to explore amazing properties</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="firstName" name="firstName" placeholder="First Name" required value="<?php echo htmlspecialchars($formData['firstName'] ?? ''); ?>">
                        <label for="firstName">First Name</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="lastName" name="lastName" placeholder="Last Name" required value="<?php echo htmlspecialchars($formData['lastName'] ?? ''); ?>">
                        <label for="lastName">Last Name</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email" placeholder="Email" required value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>">
                        <label for="email"><i class="fas fa-envelope me-2"></i>Email</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone Number" required value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>">
                        <label for="phone"><i class="fas fa-phone me-2"></i>Phone</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password" required>
                        <label for="confirmPassword"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                    </div>
                    <button type="submit" class="btn btn-register">Create Account</button>
                </form>
                
                <div class="register-footer">
                    <p class="mb-0">Already have an account? <a href="login.php">Sign in</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>


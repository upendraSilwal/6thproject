<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$message = '';

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login.php');
    exit();
}

// If already verified, redirect to add property
if ($user['phone_verified']) {
    header('Location: add_property.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_code') {
        // Generate and send verification code
        $verification_code = sprintf('%06d', mt_rand(0, 999999));
        $expires_at = date('Y-m-d H:i:s', time() + 900); // 15 minutes
        
        try {
            // Update user with verification code
            $stmt = $pdo->prepare("UPDATE users SET phone_verification_code = ?, phone_verification_expires = ? WHERE id = ?");
            $stmt->execute([$verification_code, $expires_at, $user_id]);
            
            // Log verification attempt
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $stmt = $pdo->prepare("INSERT INTO phone_verification_log (phone_number, verification_code, ip_address, user_id, expires_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user['phone'], $verification_code, $ip_address, $user_id, $expires_at]);
            
            // In a real application, you would send SMS here
            // For demo purposes, we'll show the code (remove in production!)
            $success = "Verification code sent to your phone: " . $user['phone'] . ". Demo code: " . $verification_code;
            
        } catch (Exception $e) {
            $error = 'Failed to send verification code. Please try again.';
        }
        
    } elseif ($action === 'verify_code') {
        $entered_code = preg_replace('/[^0-9]/', '', $_POST['verification_code'] ?? ''); // Remove all non-numeric characters
        
        
        if (empty($entered_code)) {
            $error = 'Please enter the verification code.';
        } else {
            // Check if code is valid and not expired
            $stmt = $pdo->prepare("SELECT phone_verification_code, phone_verification_expires FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $verification_data = $stmt->fetch();
            
            // Debug information (remove in production)
            $current_time = time();
            $expires_time = $verification_data ? strtotime($verification_data['phone_verification_expires']) : 0;
            $stored_code = $verification_data ? $verification_data['phone_verification_code'] : 'N/A';
            
            if (!$verification_data) {
                $error = 'No verification code found. Please request a new code.';
            } elseif ($verification_data['phone_verification_code'] !== $entered_code) {
                $error = 'Invalid verification code. Expected: ' . $stored_code . ', Entered: ' . $entered_code;
            } elseif ($expires_time < $current_time) {
                $error = 'Verification code has expired. Please request a new code. (Expired at: ' . date('Y-m-d H:i:s', $expires_time) . ', Current: ' . date('Y-m-d H:i:s', $current_time) . ')';
            } else {
                try {
                    // Mark phone as verified
                    $stmt = $pdo->prepare("UPDATE users SET phone_verified = 1, phone_verified_at = CURRENT_TIMESTAMP, phone_verification_code = NULL, phone_verification_expires = NULL WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    // Update verification log
                    $stmt = $pdo->prepare("UPDATE phone_verification_log SET is_verified = 1, verified_at = CURRENT_TIMESTAMP WHERE user_id = ? AND verification_code = ?");
                    $stmt->execute([$user_id, $entered_code]);
                    
                    $_SESSION['phone_verified'] = true;
                    $_SESSION['success_message'] = 'Phone number verified successfully! You can now create your free listing.';
                    
                    // Check if this is an AJAX request
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Phone verified successfully!', 'redirect' => 'add_property.php']);
                        exit();
                    }
                    
                    header('Location: add_property.php');
                    exit();
                    
                } catch (Exception $e) {
                    $error = 'Verification failed. Please try again.';
                }
            }
        }
        
        // If there's an error and this is an AJAX request, return JSON
        if (!empty($error) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit();
        }
    }
}

$pageTitle = "Phone Verification";
require_once 'includes/header.php';
?>

<main class="container">
    <div class="verification-container">
        <div class="verification-header">
            <h1><i class="fas fa-mobile-alt"></i> Phone Verification</h1>
            <p>Verify your phone number to access your free property listing</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>


        <div class="verification-steps">
            <div class="step-card">
                <h6>Your Phone Number</h6>
                <p class="phone-display"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?></p>
                <small class="text-muted">This is the number we'll send the verification code to</small>
            </div>

            <?php if (empty($success)): ?>
            <!-- Step 1: Send Code -->
            <form method="POST" class="verification-form">
                <input type="hidden" name="action" value="send_code">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane"></i> Send Verification Code
                </button>
            </form>
            <?php else: ?>
            <!-- Step 2: Enter Code -->
            <form method="POST" class="verification-form">
                <input type="hidden" name="action" value="verify_code">
                <div class="form-group">
                    <label for="verification_code">Enter Verification Code</label>
                    <input type="text" id="verification_code" name="verification_code" 
                           class="form-control text-center" placeholder="000000" 
                           maxlength="6" pattern="[0-9]{6}" required>
                    <small class="text-muted">Enter the 6-digit code sent to your phone</small>
                    <div id="codeError" class="text-danger mt-2" style="display: none;">
                        <i class="fas fa-exclamation-circle me-1"></i>
                        <span id="errorMessage">Please enter a valid 6-digit code</span>
                    </div>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-check"></i> Verify Code
                    </button>
                </div>
            </form>
            
            <!-- Separate form for resend code -->
            <form method="POST" class="verification-form mt-3">
                <input type="hidden" name="action" value="send_code">
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="fas fa-redo"></i> Resend Code
                </button>
            </form>
            <?php endif; ?>
        </div>

        <div class="verification-footer">
            <p><strong>Need help?</strong> Contact support if you're having trouble receiving the verification code.</p>
            <a href="my-properties.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to My Properties
            </a>
        </div>
    </div>
</main>

<style>
.verification-container {
    max-width: 600px;
    margin: 2rem auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    overflow: hidden;
}

.verification-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    text-align: center;
}

.verification-header h1 {
    margin: 0 0 0.5rem 0;
    font-size: 1.8rem;
}

.verification-header p {
    margin: 0;
    opacity: 0.9;
}

.verification-steps,
.verification-footer {
    padding: 2rem;
}

.step-card {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 2rem;
}

.phone-display {
    font-size: 1.2rem;
    font-weight: 600;
    color: #333;
    margin: 1rem 0;
}

.verification-form {
    text-align: center;
}

.form-group {
    margin-bottom: 2rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #333;
}

.form-control {
    width: 200px;
    margin: 0 auto;
    padding: 1rem;
    font-size: 1.2rem;
    font-weight: 600;
    letter-spacing: 0.2em;
    border: 2px solid #e9ecef;
    border-radius: 8px;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
}

.form-control.error {
    border-color: #dc3545;
    background-color: #ffe6e6;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.15);
}

.form-control.error:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.form-actions {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn-lg {
    padding: 0.875rem 2rem;
    font-size: 1.1rem;
}

.verification-footer {
    background: #f8f9fa;
    text-align: center;
    border-top: 1px solid #e9ecef;
}

.btn-pulse {
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
    }
}

@keyframes shake {
    0%, 20%, 40%, 60%, 80%, 100% {
        transform: translateX(0);
    }
    10%, 30%, 50%, 70%, 90% {
        transform: translateX(-3px);
    }
}

@media (max-width: 768px) {
    .verification-container {
        margin: 1rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-control {
        width: 100%;
        max-width: 200px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const codeInput = document.getElementById('verification_code');
    if (codeInput) {
        // Auto-focus and format input
        codeInput.focus();
        let autoSubmitTimeout;
        
        // Function to show error with red animation
        function showError(message) {
            const codeErrorDiv = document.getElementById('codeError');
            const errorMessage = document.getElementById('errorMessage');
            
            codeInput.classList.add('error');
            if (codeErrorDiv) {
                codeErrorDiv.style.display = 'block';
            }
            if (errorMessage) {
                errorMessage.textContent = message;
            }
            
            // Add shake animation
            codeInput.style.animation = 'shake 0.5s';
            setTimeout(() => {
                codeInput.style.animation = '';
            }, 500);
            
            // Clear input field
            codeInput.value = '';
            codeInput.focus();
        }
        
        // Function to hide error
        function hideError() {
            const codeErrorDiv = document.getElementById('codeError');
            codeInput.classList.remove('error');
            if (codeErrorDiv) {
                codeErrorDiv.style.display = 'none';
            }
        }
        
        // Function to submit verification via AJAX
        function submitVerification(code) {
            const formData = new FormData();
            formData.append('action', 'verify_code');
            formData.append('verification_code', code);
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text();
                }
            })
            .then(data => {
                if (typeof data === 'object') {
                    // JSON response
                    if (data.success) {
                        // Success - redirect to add property page
                        window.location.href = data.redirect || 'add_property.php';
                    } else {
                        showError(data.message || 'Verification failed. Please try again.');
                    }
                } else {
                    // HTML response - fallback to old method
                    if (data.includes('Invalid verification code') || data.includes('alert-danger')) {
                        showError('Invalid verification code. Please try again.');
                    } else if (data.includes('Verification code has expired')) {
                        showError('Verification code has expired. Please request a new code.');
                    } else if (data.includes('Location: add_property.php') || data.includes('verified successfully')) {
                        // Success - redirect to add property page
                        window.location.href = 'add_property.php';
                    } else {
                        // If we can't determine the result, reload the page
                        window.location.reload();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Network error. Please try again.');
            });
        }
        
        codeInput.addEventListener('input', function(e) {
            // Only allow numbers and remove any spaces or non-numeric characters
            this.value = this.value.replace(/[^0-9]/g, '').replace(/\s+/g, '');
            
            // Clear any existing timeout
            if (autoSubmitTimeout) {
                clearTimeout(autoSubmitTimeout);
            }
            
            // Always hide error while user is typing (less than 6 digits)
            if (this.value.length < 6) {
                hideError();
            }
            
            // Auto-submit when 6 digits entered
            if (this.value.length === 6) {
                const verifyForm = this.form; // The form containing the input
                const submitBtn = verifyForm.querySelector('button[type="submit"]');
                
                // Remove error styling when 6 digits are entered (assume it's valid until proven wrong)
                hideError();
                
                // Always auto-submit when 6 digits are entered
                autoSubmitTimeout = setTimeout(() => {
                    submitVerification(this.value);
                }, 500); // Quick 0.5 second delay
                
                // Add visual feedback while waiting
                if (submitBtn) {
                    submitBtn.classList.add('btn-pulse');
                }
            } else {
                // Reset button styling if user deletes characters
                const verifyForm = this.form;
                const submitBtn = verifyForm.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.classList.remove('btn-pulse');
                }
            }
        });
        
        // Handle form submission
        const form = codeInput.form;
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission
                
                if (autoSubmitTimeout) {
                    clearTimeout(autoSubmitTimeout);
                }
                
                const code = codeInput.value;
                if (code.length === 6) {
                    submitVerification(code);
                } else {
                    showError('Please enter a complete 6-digit code.');
                }
            });
        }
        
        // Add keydown event to handle Enter key
        codeInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (autoSubmitTimeout) {
                    clearTimeout(autoSubmitTimeout);
                }
                const code = this.value;
                if (code.length === 6) {
                    submitVerification(code);
                } else {
                    showError('Please enter a complete 6-digit code.');
                }
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>

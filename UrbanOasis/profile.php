<?php
session_start();
require_once 'includes/header.php';
if (!$isLoggedIn) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $updatePassword = !empty($password);

    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if email is taken by another user
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $currentUser['id']]);
        if ($stmt->fetch()) {
            $error = 'Email is already taken.';
        } else {
            // Check if phone is taken by another user
            $stmt = $pdo->prepare('SELECT id FROM users WHERE phone = ? AND id != ?');
            $stmt->execute([$phone, $currentUser['id']]);
            if ($stmt->fetch()) {
                $error = 'Phone number is already taken.';
            } else {
                if ($updatePassword) {
                    $hashedPassword = hashUserPassword($password);
                    $stmt = $pdo->prepare('UPDATE users SET first_name=?, last_name=?, email=?, phone=?, password=? WHERE id=?');
                    $ok = $stmt->execute([$firstName, $lastName, $email, $phone, $hashedPassword, $currentUser['id']]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?');
                    $ok = $stmt->execute([$firstName, $lastName, $email, $phone, $currentUser['id']]);
                }
                if ($ok) {
                    $success = 'Profile updated successfully!';
                    // Refresh user data
                    $user = getUserById($pdo, $user_id);
                } else {
                    $error = 'Failed to update profile.';
                }
            }
        }
    }
}
?>
<main class="flex-fill" style="margin-top:70px;">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">My Profile</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2"> <?php echo htmlspecialchars($error); ?> </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success py-2"> <?php echo htmlspecialchars($success); ?> </div>
                    <?php endif; ?>
                    <form method="POST" autocomplete="off">
                        <div class="mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($currentUser['first_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($currentUser['last_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($currentUser['phone']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password <span class="text-muted" style="font-size:0.9em;">(leave blank to keep current)</span></label>
                            <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</main>
<?php require_once 'includes/footer.php'; ?> 
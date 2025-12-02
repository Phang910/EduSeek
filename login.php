<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/language.php';
require_once 'db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$message = '';
$message_type = '';

// Handle Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $login_type = trim($_POST['login_type'] ?? 'user');
    
    if (empty($email) || empty($password)) {
        $message = 'Email and password are required';
        $message_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email address';
        $message_type = 'danger';
    } else {
        // Find user by email
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user && password_verify($password, $user['password'])) {
            $user_role = $user['role'] ?? 'user';
            
            // Verify login type matches user role
            if ($login_type === 'vendor' && $user_role !== 'vendor') {
                $message = 'This account is not a vendor account. Please login as a user.';
                $message_type = 'danger';
            } elseif ($login_type === 'user' && $user_role === 'vendor') {
                $message = 'This account is a vendor account. Please login as a vendor.';
                $message_type = 'danger';
            } elseif ($login_type === 'vendor' && $user_role === 'vendor') {
                // Check vendor status
                $vendor_status = $user['vendor_status'] ?? null;
                if ($vendor_status === 'pending') {
                    $message = 'Your vendor account is pending admin approval. Please wait for approval before logging in.';
                    $message_type = 'warning';
                } elseif ($vendor_status === 'rejected') {
                    $message = 'Your vendor account registration has been rejected. Please contact admin for more information.';
                    $message_type = 'danger';
                } elseif ($vendor_status === 'approved' || $vendor_status === null) {
                    // Approved - allow login
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user_role;
                    $_SESSION['user_unique_number'] = $user['unique_number'] ?? null;
                    $_SESSION['just_logged_in'] = true;
                    
                    header('Location: index.php');
                    exit;
                }
            } else {
                // Normal user login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user_role;
                $_SESSION['user_unique_number'] = $user['unique_number'] ?? null;
                $_SESSION['just_logged_in'] = true;
            
                header('Location: index.php');
                exit;
            }
        } else {
            $message = 'Invalid email or password';
            $message_type = 'danger';
        }
    }
}

// Load header
$page_title = t('login_title');
require_once 'includes/header.php';
?>

<!-- Login Background with Design Image -->
<?php 
$login_bg = getDesignImage('designpic1');
$login_section_style = '';
if ($login_bg) {
    $login_section_style = 'background-image: linear-gradient(135deg, rgba(79, 163, 247, 0.9) 0%, rgba(118, 75, 162, 0.9) 100%), url(' . htmlspecialchars($login_bg) . '); background-size: cover; background-position: center; min-height: 100vh;';
}
?>
<div class="login-page-wrapper" style="<?php echo $login_section_style; ?>">
<div class="container my-5 page-content" style="min-height: calc(100vh - 200px); display: flex; align-items: center;">
    <div class="row justify-content-center w-100">
        <div class="col-xl-5 col-lg-6 col-md-7">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4" style="color: #4FA3F7;">
                        <i class="fas fa-sign-in-alt me-2"></i><?php echo t('login_title'); ?>
                    </h2>
                    
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Login Form -->
                    <form method="POST" action="" id="loginForm">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" 
                                   class="form-control form-control-lg" 
                                   name="email" 
                                   id="email"
                                   placeholder="Enter your email"
                                   required 
                                   autofocus>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" 
                                   class="form-control form-control-lg" 
                                   name="password" 
                                   id="password"
                                   placeholder="Enter your password"
                                   required>
                        </div>
                            
                        <!-- Account Type Selection -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Account Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="login_type" id="login_type_user" value="user" checked>
                                <label class="form-check-label" for="login_type_user">
                                    <i class="fas fa-user me-2"></i>Normal User
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="radio" name="login_type" id="login_type_vendor" value="vendor">
                                <label class="form-check-label" for="login_type_vendor">
                                    <i class="fas fa-store me-2"></i>Vendor
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <p class="text-center text-muted mb-0">
                        <?php echo t('login_no_account'); ?> <a href="register_phone.php"><?php echo t('login_register_link'); ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<?php require_once 'includes/footer.php'; ?>

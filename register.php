<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/language.php';

// Redirect if already logged in (before any output)
if (isLoggedIn()) {
    header('Location: profile.php');
    exit;
}

// Redirect to main registration page
header('Location: register_phone.php');
exit;

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once 'db.php';
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = trim($_POST['role'] ?? 'user'); // Default to 'user' if not set
    
    // Validation
    $errors = [];
    if (empty($name)) {
        $errors[] = t('register_name') . ' ' . t('required');
    }
    if (empty($email)) {
        $errors[] = t('register_email') . ' ' . t('required');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('contact_valid_email');
    }
    if (empty($password)) {
        $errors[] = t('register_password') . ' ' . t('required');
    } elseif (strlen($password) < 6) {
        $errors[] = t('register_password_short');
    }
    if ($password !== $confirm_password) {
        $errors[] = t('register_password_mismatch');
    }
    
    if (empty($errors)) {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = t('register_email_exists');
            $message_type = 'danger';
        } else {
            // Validate role
            if (!in_array($role, ['user', 'vendor'])) {
                $role = 'user'; // Default to 'user' if invalid
            }
            
            // Ensure role column exists
            $check_role_col = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
            $has_role_col = $check_role_col->num_rows > 0;
            $check_role_col->close();
            
            if (!$has_role_col) {
                // Create role column if it doesn't exist
                $conn->query("ALTER TABLE users ADD COLUMN role ENUM('user', 'vendor') DEFAULT 'user' AFTER email");
            }
            
            // Ensure vendor_status column exists
            $check_status_col = $conn->query("SHOW COLUMNS FROM users LIKE 'vendor_status'");
            $has_status_col = $check_status_col->num_rows > 0;
            $check_status_col->close();
            
            if (!$has_status_col) {
                // Create vendor_status column if it doesn't exist
                $conn->query("ALTER TABLE users ADD COLUMN vendor_status ENUM('pending', 'approved', 'rejected') NULL AFTER role");
            }
            
            // Ensure unique_number column exists
            $check_unique_col = $conn->query("SHOW COLUMNS FROM users LIKE 'unique_number'");
            $has_unique_col = $check_unique_col->num_rows > 0;
            $check_unique_col->close();
            
            if (!$has_unique_col) {
                // Create unique_number column if it doesn't exist
                $conn->query("ALTER TABLE users ADD COLUMN unique_number INT UNSIGNED UNIQUE NULL AFTER id");
            }
            
            // Generate unique number (starting from 100000)
            $max_result = $conn->query("SELECT MAX(unique_number) as max_num FROM users WHERE unique_number IS NOT NULL");
            $max_row = $max_result->fetch_assoc();
            $max_result->close();
            
            $unique_number = 100000;
            if ($max_row && $max_row['max_num'] && $max_row['max_num'] >= 100000) {
                $unique_number = $max_row['max_num'] + 1;
            }
            
            // Set vendor status
            $vendor_status = null;
            if ($role === 'vendor') {
                $vendor_status = 'pending'; // Vendors need admin approval
            }
            
            // Create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            if ($role === 'vendor') {
                // Vendor registration - needs admin approval
                $stmt = $conn->prepare("INSERT INTO users (name, email, role, vendor_status, unique_number, password) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssis", $name, $email, $role, $vendor_status, $unique_number, $hashed_password);
            } else {
                // Normal user registration - auto approved
                $stmt = $conn->prepare("INSERT INTO users (name, email, role, unique_number, password) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssis", $name, $email, $role, $unique_number, $hashed_password);
            }
            
            if ($stmt->execute()) {
                if ($role === 'vendor') {
                    // Vendor registration - show pending message
                    $message = 'Your vendor account registration has been submitted and is pending admin approval. You will be notified once your account is approved.';
                    $message_type = 'info';
                    // Don't redirect, show message on same page
                } else {
                    // Normal user - redirect to login
                    header('Location: login.php?registered=1');
                    exit;
                }
            } else {
                $message = t('error');
                $message_type = 'danger';
            }
            $stmt->close();
        }
        $stmt->close();
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'danger';
    }
}

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
?>

<!-- Register Background with Design Image -->
<?php 
$register_bg = getDesignImage('designpic2');
$register_section_style = '';
if ($register_bg) {
    $register_section_style = 'background-image: linear-gradient(135deg, rgba(79, 163, 247, 0.9) 0%, rgba(118, 75, 162, 0.9) 100%), url(' . htmlspecialchars($register_bg) . '); background-size: cover; background-position: center; min-height: 100vh;';
}
?>
<div class="register-page-wrapper" style="<?php echo $register_section_style; ?>">
<div class="container my-5 page-content" style="min-height: calc(100vh - 200px); display: flex; align-items: center;">
    <div class="row justify-content-center w-100">
        <div class="col-xl-5 col-lg-6 col-md-7">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4" style="color: #4FA3F7;"><?php echo t('register_title'); ?></h2>
                    
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label"><?php echo t('register_name'); ?></label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($name); ?>" required autofocus>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo t('register_email'); ?></label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Account Type *</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="role" id="role_user" value="user" checked required>
                                <label class="form-check-label" for="role_user">
                                    <i class="fas fa-user me-2"></i>Normal User (Browse schools and express interest)
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="radio" name="role" id="role_vendor" value="vendor" required>
                                <label class="form-check-label" for="role_vendor">
                                    <i class="fas fa-store me-2"></i>Vendor (Manage school listings and view interested clients)
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo t('register_password'); ?></label>
                            <input type="password" class="form-control" name="password" required minlength="6">
                            <small class="text-muted"><?php echo t('register_password_short'); ?></small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo t('register_confirm_password'); ?></label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="6">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus me-2"></i><?php echo t('register_button'); ?>
                            </button>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <p class="text-center text-muted mb-0">
                        <?php echo t('register_have_account'); ?> <a href="login.php"><?php echo t('register_login_link'); ?></a><br>
                        Or <a href="register_phone.php"><i class="fas fa-user-plus me-1"></i>Register with Email</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

    </div>
</div>
</div>

<?php require_once 'includes/footer.php'; ?>

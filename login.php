<?php
$page_title = 'Admin Login';
require_once '../config.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isAdminLoggedIn()) {
    // Use absolute URL to prevent path issues
    $dashboard_url = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) 
        ? 'dashboard.php' 
        : SITE_URL . 'admin/dashboard.php';
    header('Location: ' . $dashboard_url);
    exit;
}

$message = '';
$message_type = '';

// Check if user logged out successfully
if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
    $message = 'You have been successfully logged out.';
    $message_type = 'success';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once '../db.php';
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $message = 'Please fill in all fields.';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            
            // Use relative path since we're already in admin folder
            header('Location: dashboard.php');
            exit;
        } else {
            $message = 'Invalid username or password.';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - EduSeek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #4FA3F7 0%, #3d8ee5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card shadow-lg">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4" style="color: #4FA3F7;">
                            <i class="fas fa-shield-alt me-2"></i>Admin Login
                        </h2>
                        
                        <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" required autofocus>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <p class="text-center mb-0">
                            <a href="../index.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>Back to Website
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Password Toggle Visibility -->
    <script>
    // Initialize password toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Find all password input fields (including those that might become text type)
        const allInputs = document.querySelectorAll('input[type="password"], input[type="text"][name*="password"], input[type="text"][id*="password"]');
        
        allInputs.forEach(function(input) {
            // Skip if not a password field or already has toggle button
            if (input.type !== 'password' && !input.name.toLowerCase().includes('password') && !input.id.toLowerCase().includes('password')) {
                return;
            }
            
            if (input.parentElement && input.parentElement.classList.contains('password-toggle-wrapper')) {
                return;
            }
            
            // Create wrapper
            const wrapper = document.createElement('div');
            wrapper.className = 'password-toggle-wrapper';
            wrapper.style.width = '100%';
            wrapper.style.position = 'relative';
            
            // Get parent (could be a div, form, etc.)
            const parent = input.parentNode;
            
            // Insert wrapper before input
            parent.insertBefore(wrapper, input);
            
            // Move input into wrapper
            wrapper.appendChild(input);
            
            // Create toggle button
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'password-toggle-btn';
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
            toggleBtn.setAttribute('aria-label', 'Toggle password visibility');
            toggleBtn.style.position = 'absolute';
            toggleBtn.style.right = '12px';
            toggleBtn.style.top = '50%';
            toggleBtn.style.transform = 'translateY(-50%)';
            toggleBtn.style.zIndex = '10';
            toggleBtn.style.border = 'none';
            toggleBtn.style.background = 'transparent';
            toggleBtn.style.padding = '0';
            toggleBtn.style.margin = '0';
            toggleBtn.style.boxShadow = 'none';
            toggleBtn.style.outline = 'none';
            
            // Add toggle functionality
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (input.type === 'password') {
                    input.type = 'text';
                    toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    input.type = 'password';
                    toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
                }
            });
            
            // Append button to wrapper (positioned absolutely inside the input field visually)
            wrapper.appendChild(toggleBtn);
        });
    });
    </script>
</body>
</html>


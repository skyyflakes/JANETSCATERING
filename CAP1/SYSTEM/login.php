<?php
/**
 * Login Page - Janet's Quality Catering System
 * Updated with Registration and SMS Verification
 */
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$captcha = '';
$show_captcha = isset($_POST['show_captcha']) || isset($_SESSION['show_captcha']);
$mode = $_GET['mode'] ?? 'login'; // login or register

// Generate new captcha
if (!isset($_SESSION['captcha']) || ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$show_captcha)) {
    $_SESSION['captcha'] = generateCaptcha();
}
$captcha = $_SESSION['captcha'];

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $contact = sanitize($_POST['contact'] ?? '');
    $sms_code = sanitize($_POST['sms_code'] ?? '');
    
    // Validate SMS code
    if (!isset($_SESSION['sms_verification_code']) || $sms_code !== $_SESSION['sms_verification_code']) {
        $error = 'Invalid SMS verification code!';
        $mode = 'register';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters!';
        $mode = 'register';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters!';
        $mode = 'register';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match!';
        $mode = 'register';
    } else {
        $pdo = getDBConnection();
        if ($pdo) {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already exists!';
                $mode = 'register';
            } else {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email already registered!';
                    $mode = 'register';
                } else {
                    // Create new user
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, first_name, last_name, contact_number, role) VALUES (?, ?, ?, ?, ?, ?, 'ADMIN')");
                    $stmt->execute([$username, $hashed_password, $email, $first_name, $last_name, $contact]);
                    
                    unset($_SESSION['sms_verification_code']);
                    $success = 'Account created successfully! Please login.';
                    $mode = 'login';
                }
            }
        } else {
            $error = 'Database connection failed!';
            $mode = 'register';
        }
    }
}

// Handle SMS verification request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $contact = sanitize($_POST['contact'] ?? '');
    
    if (preg_match('/^09\d{9}$/', $contact) || preg_match('/^\+639\d{9}$/', $contact)) {
        // Generate 6-digit code
        $verification_code = sprintf('%06d', mt_rand(0, 999999));
        $_SESSION['sms_verification_code'] = $verification_code;
        $_SESSION['sms_contact'] = $contact;
        
        // In production, integrate with SMS API (Semaphore, Twilio, etc.)
        // For demo, we'll show the code in the response
        echo json_encode([
            'success' => true,
            'message' => 'Verification code sent!',
            'demo_code' => $verification_code // Remove in production
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid phone number format! Use 09XXXXXXXXX'
        ]);
    }
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['register']) && !isset($_POST['send_sms'])) {
    $form_step = $_POST['form_step'] ?? 'initial';
    
    if ($form_step === 'initial') {
        $_SESSION['show_captcha'] = true;
        $_SESSION['temp_username'] = sanitize($_POST['username'] ?? '');
        $_SESSION['temp_password'] = $_POST['password'] ?? '';
        $show_captcha = true;
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $captcha_input = strtoupper(sanitize($_POST['captcha_input'] ?? ''));
        $terms = isset($_POST['terms']);

        if (!$terms) {
            $error = 'You must agree to the Terms and Conditions.';
            $_SESSION['captcha'] = generateCaptcha();
            $captcha = $_SESSION['captcha'];
        } elseif ($captcha_input !== $_SESSION['captcha']) {
            $error = 'Invalid Captcha! Please try again.';
            $_SESSION['captcha'] = generateCaptcha();
            $captcha = $_SESSION['captcha'];
        } else {
            $pdo = getDBConnection();
            
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    unset($_SESSION['show_captcha'], $_SESSION['temp_username'], $_SESSION['temp_password']);
                    
                    setFlash('Welcome back, ' . $user['username'] . '!', 'success');
                    redirect('dashboard.php');
                } else {
                    // Fallback hardcoded accounts
                    if (($username === 'admin' && $password === 'admin123') ||
                        ($username === 'owner' && $password === 'owner123')) {
                        
                        $_SESSION['user_id'] = ($username === 'admin') ? 1 : 2;
                        $_SESSION['username'] = $username;
                        $_SESSION['role'] = ($username === 'owner') ? 'OWNER' : 'ADMIN';
                        
                        unset($_SESSION['show_captcha'], $_SESSION['temp_username'], $_SESSION['temp_password']);
                        
                        setFlash('Welcome back, ' . $username . '!', 'success');
                        redirect('dashboard.php');
                    } else {
                        $error = 'Invalid username or password!';
                        $_SESSION['captcha'] = generateCaptcha();
                        $captcha = $_SESSION['captcha'];
                    }
                }
            } else {
                // Database not available
                if (($username === 'admin' && $password === 'admin123') ||
                    ($username === 'owner' && $password === 'owner123')) {
                    
                    $_SESSION['user_id'] = ($username === 'admin') ? 1 : 2;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = ($username === 'owner') ? 'OWNER' : 'ADMIN';
                    
                    unset($_SESSION['show_captcha'], $_SESSION['temp_username'], $_SESSION['temp_password']);
                    
                    setFlash('Welcome back, ' . $username . '!', 'success');
                    redirect('dashboard.php');
                } else {
                    $error = 'Invalid username or password!';
                    $_SESSION['captcha'] = generateCaptcha();
                    $captcha = $_SESSION['captcha'];
                }
            }
        }
    }
}

$temp_username = $_SESSION['temp_username'] ?? '';
$temp_password = $_SESSION['temp_password'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" class="light-style" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $mode === 'register' ? 'Register' : 'Login'; ?> | Janet's Quality Catering</title>
    
    <link rel="icon" type="image/x-icon" href="static/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bs-primary: #9370DB;
            --bs-primary-dark: #7B5FC7;
            --body-bg: #E6E6FA;
            --body-color: #4A4A6A;
            --heading-color: #2D2D4A;
            --border-color: #C8B8E8;
            --card-bg: #fff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Public Sans', sans-serif;
            background: linear-gradient(135deg, #E6E6FA 0%, #D8BFD8 50%, #DDA0DD 100%);
            color: var(--body-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .authentication-wrapper {
            width: 100%;
            max-width: <?php echo $mode === 'register' ? '500px' : '420px'; ?>;
        }

        .authentication-inner {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(147, 112, 219, 0.2);
            padding: 40px;
        }

        .app-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }

        .app-brand-logo {
            width: 120px;
            height: 120px;
            border-radius: 16px;
            margin-bottom: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(147, 112, 219, 0.3);
        }

        .app-brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .app-brand-text {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--heading-color);
            text-align: center;
        }

        .app-brand-text span {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--bs-primary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .auth-title {
            font-size: 1.375rem;
            font-weight: 600;
            color: var(--heading-color);
            margin-bottom: 8px;
            text-align: center;
        }

        .auth-subtitle {
            font-size: 0.9375rem;
            color: var(--body-color);
            text-align: center;
            margin-bottom: 24px;
        }

        .form-label {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--heading-color);
            margin-bottom: 6px;
        }

        .form-control {
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9375rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            background: #FAFAFF;
            color: var(--heading-color);
        }

        .form-control:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 3px rgba(147, 112, 219, 0.15);
            background: #fff;
        }

        .form-control::placeholder {
            color: #A0A0B8;
        }

        .input-group {
            position: relative;
        }

        .input-group .form-control {
            padding-right: 45px;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--body-color);
            cursor: pointer;
            z-index: 10;
            padding: 0;
        }

        .password-toggle:hover {
            color: var(--bs-primary);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--bs-primary) 0%, #8B5CF6 100%);
            border: none;
            padding: 14px 24px;
            font-weight: 600;
            font-size: 0.9375rem;
            border-radius: 8px;
            width: 100%;
            transition: all 0.2s;
            color: #fff;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--bs-primary-dark) 0%, #7C3AED 100%);
            box-shadow: 0 4px 16px rgba(147, 112, 219, 0.4);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: transparent;
            border: 2px solid var(--bs-primary);
            color: var(--bs-primary);
            padding: 12px 24px;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background: var(--bs-primary);
            color: #fff;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger {
            background: rgba(220, 38, 38, 0.1);
            color: #DC2626;
            border: none;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #16A34A;
            border: none;
        }

        .form-check {
            padding-left: 1.75rem;
        }

        .form-check-input {
            width: 1.125rem;
            height: 1.125rem;
            margin-left: -1.75rem;
            border-color: var(--border-color);
        }

        .form-check-input:checked {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }

        .form-check-label {
            font-size: 0.8125rem;
            color: var(--body-color);
        }

        .form-check-label a {
            color: var(--bs-primary);
            text-decoration: none;
            font-weight: 500;
        }

        /* Captcha Section */
        .captcha-container {
            background: #F8F6FF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 16px;
            display: <?php echo $show_captcha && $mode === 'login' ? 'block' : 'none'; ?>;
        }

        .captcha-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .captcha-checkbox {
            width: 24px;
            height: 24px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .captcha-checkbox:hover {
            border-color: var(--bs-primary);
        }

        .captcha-checkbox.checked {
            background: var(--bs-primary);
            border-color: var(--bs-primary);
        }

        .captcha-checkbox i {
            color: #fff;
            font-size: 14px;
            display: none;
        }

        .captcha-checkbox.checked i {
            display: block;
        }

        .captcha-text {
            font-size: 0.875rem;
            color: var(--heading-color);
            font-weight: 500;
        }

        .captcha-code-section {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 14px;
        }

        .captcha-code-section label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--body-color);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: block;
        }

        .captcha-display {
            background: linear-gradient(135deg, #F0E6FF 0%, #E6E0FA 100%);
            border: 1px solid var(--border-color);
            padding: 12px 18px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 1.3rem;
            letter-spacing: 8px;
            color: var(--heading-color);
            text-align: center;
            margin-bottom: 10px;
            user-select: none;
        }

        .captcha-input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.9rem;
            text-align: center;
            letter-spacing: 3px;
            text-transform: uppercase;
            background: #FAFAFF;
        }

        .captcha-input:focus {
            outline: none;
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 2px rgba(147, 112, 219, 0.15);
        }

        .terms-section {
            display: <?php echo $show_captcha && $mode === 'login' ? 'block' : 'none'; ?>;
        }

        .auth-switch {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .auth-switch span {
            color: var(--body-color);
            font-size: 0.9rem;
        }

        .auth-switch a {
            color: var(--bs-primary);
            font-weight: 600;
            text-decoration: none;
        }

        .auth-switch a:hover {
            text-decoration: underline;
        }

        /* SMS Verification */
        .sms-verification {
            background: #F8F6FF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .sms-verification h6 {
            color: var(--heading-color);
            font-weight: 600;
            margin-bottom: 12px;
            font-size: 0.875rem;
        }

        .sms-row {
            display: flex;
            gap: 10px;
        }

        .sms-row input {
            flex: 1;
        }

        .btn-send-sms {
            background: var(--bs-primary);
            border: none;
            color: #fff;
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.8rem;
            white-space: nowrap;
            cursor: pointer;
        }

        .btn-send-sms:hover {
            background: var(--bs-primary-dark);
        }

        .btn-send-sms:disabled {
            background: #C8B8E8;
            cursor: not-allowed;
        }

        .sms-code-input {
            margin-top: 12px;
        }

        .sms-code-input input {
            text-align: center;
            font-size: 1.25rem;
            letter-spacing: 8px;
            font-weight: 600;
        }

        .sms-status {
            font-size: 0.75rem;
            margin-top: 8px;
            display: none;
        }

        .sms-status.success {
            color: #16A34A;
            display: block;
        }

        .sms-status.error {
            color: #DC2626;
            display: block;
        }

        .demo-info {
            background: rgba(147, 112, 219, 0.1);
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
        }

        .demo-info h6 {
            color: var(--bs-primary);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .demo-account {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(147, 112, 219, 0.2);
        }

        .demo-account:last-child {
            border-bottom: none;
        }

        .demo-account span {
            font-size: 0.8125rem;
            color: var(--body-color);
        }

        .demo-account code {
            background: rgba(147, 112, 219, 0.15);
            color: var(--bs-primary);
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 0.8125rem;
        }

        /* Row for registration */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 500px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Modal */
        .modal-content {
            border: none;
            border-radius: 12px;
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 20px 24px;
        }

        .modal-title {
            font-weight: 600;
            color: var(--heading-color);
        }

        .modal-body {
            padding: 24px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .modal-body h5 {
            color: var(--heading-color);
            font-weight: 600;
            margin-top: 20px;
            margin-bottom: 10px;
        }

        .modal-body ul {
            padding-left: 20px;
        }

        .modal-body li {
            margin-bottom: 6px;
            font-size: 0.875rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 16px 24px;
        }
    </style>
</head>
<body>
    <div class="authentication-wrapper">
        <div class="authentication-inner">
            <!-- Logo -->
            <div class="app-brand">
                <div class="app-brand-logo">
                    <img src="static/images/logo.png" alt="Janet's Catering Logo">
                </div>
                <div class="app-brand-text">
                    Janet's Quality
                    <span>Catering Services</span>
                </div>
            </div>

            <?php if ($mode === 'register'): ?>
            <!-- Registration Form -->
            <h4 class="auth-title">Create Account</h4>
            <p class="auth-subtitle">Join Janet's Catering Services</p>

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="bx bx-error-circle"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="login.php?mode=register" id="registerForm">
                <input type="hidden" name="register" value="1">
                
                <div class="form-row">
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" placeholder="Your first name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" placeholder="Your last name" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="username" placeholder="Choose a username" required minlength="3">
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" placeholder="your@email.com" required>
                </div>

                <!-- SMS Verification -->
                <div class="sms-verification">
                    <h6><i class="bx bx-phone me-2"></i>Phone Verification</h6>
                    <div class="sms-row">
                        <input type="tel" class="form-control" id="contactInput" name="contact" placeholder="09XX-XXX-XXXX" required pattern="09\d{9}">
                        <button type="button" class="btn-send-sms" id="sendSmsBtn" onclick="sendSmsCode()">
                            Send Code
                        </button>
                    </div>
                    <div class="sms-status" id="smsStatus"></div>
                    <div class="sms-code-input">
                        <input type="text" class="form-control" name="sms_code" id="smsCodeInput" placeholder="Enter 6-digit code" maxlength="6" required pattern="\d{6}">
                    </div>
                </div>

                <div class="form-row">
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="regPassword" name="password" placeholder="Min 6 characters" required minlength="6">
                            <button type="button" class="password-toggle" onclick="toggleRegPassword('regPassword')">
                                <i class="bx bx-hide"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" placeholder="Confirm password" required>
                            <button type="button" class="password-toggle" onclick="toggleRegPassword('confirmPassword')">
                                <i class="bx bx-hide"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="regTerms" required>
                    <label class="form-check-label" for="regTerms">
                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bx bx-user-plus me-2"></i>Create Account
                </button>
            </form>

            <div class="auth-switch">
                <span>Already have an account?</span>
                <a href="login.php">Sign In</a>
            </div>

            <?php else: ?>
            <!-- Login Form -->
            <h4 class="auth-title">Welcome Back!</h4>
            <p class="auth-subtitle">Please sign in to your account</p>

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="bx bx-error-circle"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="bx bx-check-circle"></i><?php echo $success; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="form_step" id="formStep" value="<?php echo $show_captcha ? 'verify' : 'initial'; ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required value="<?php echo htmlspecialchars($temp_username); ?>" autofocus>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required value="<?php echo htmlspecialchars($temp_password); ?>">
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="bx bx-hide" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Captcha Container -->
                <div class="captcha-container" id="captchaContainer">
                    <div class="captcha-header">
                        <div class="captcha-checkbox" id="captchaCheckbox" onclick="toggleCaptchaCheck()">
                            <i class="bx bx-check"></i>
                        </div>
                        <span class="captcha-text">I'm not a robot</span>
                    </div>
                    
                    <div class="captcha-code-section">
                        <label>Enter the code below</label>
                        <div class="captcha-display"><?php echo $captcha; ?></div>
                        <input type="text" class="captcha-input" name="captcha_input" id="captchaInput" placeholder="Type the code" autocomplete="off">
                    </div>
                </div>

                <!-- Terms Section -->
                <div class="terms-section" id="termsSection">
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="terms" id="termsCheckbox">
                        <label class="form-check-label" for="termsCheckbox">
                            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bx bx-log-in me-2"></i>Sign In
                </button>
            </form>

            <div class="auth-switch">
                <span>Don't have an account?</span>
                <a href="login.php?mode=register">Sign Up</a>
            </div>

            <!-- Demo Accounts -->
            <div class="demo-info">
                <h6>Demo Accounts</h6>
                <div class="demo-account">
                    <span>Admin:</span>
                    <code>admin / admin123</code>
                </div>
                <div class="demo-account">
                    <span>Owner:</span>
                    <code>owner / owner123</code>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h5>1. Introduction</h5>
                    <p>Welcome to Janet's Quality Catering System. By using this system, you agree to these terms.</p>
                    
                    <h5>2. User Accounts</h5>
                    <ul>
                        <li>You are responsible for maintaining the confidentiality of your login credentials</li>
                        <li>You agree to provide accurate information during registration</li>
                        <li>Unauthorized access to the system is strictly prohibited</li>
                    </ul>

                    <h5>3. Data Privacy</h5>
                    <ul>
                        <li>We collect and store information necessary for system operation</li>
                        <li>Personal data is protected and not shared with third parties</li>
                        <li>You have the right to request deletion of your account data</li>
                    </ul>

                    <h5>4. System Usage</h5>
                    <ul>
                        <li>The system is for authorized personnel only</li>
                        <li>All activities are logged for security purposes</li>
                        <li>Misuse of the system may result in account suspension</li>
                    </ul>

                    <h5>5. Contact</h5>
                    <p>For questions about these terms, contact Janet's Quality Catering Services.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('bx-hide');
                icon.classList.add('bx-show');
            } else {
                password.type = 'password';
                icon.classList.remove('bx-show');
                icon.classList.add('bx-hide');
            }
        }

        function toggleRegPassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('bx-hide');
                icon.classList.add('bx-show');
            } else {
                field.type = 'password';
                icon.classList.remove('bx-show');
                icon.classList.add('bx-hide');
            }
        }

        function toggleCaptchaCheck() {
            const checkbox = document.getElementById('captchaCheckbox');
            checkbox.classList.toggle('checked');
        }

        // SMS Verification
        let smsCooldown = 0;
        
        function sendSmsCode() {
            const contact = document.getElementById('contactInput').value;
            const sendBtn = document.getElementById('sendSmsBtn');
            const status = document.getElementById('smsStatus');
            
            if (!contact.match(/^09\d{9}$/)) {
                status.textContent = 'Invalid format! Use 09XXXXXXXXX';
                status.className = 'sms-status error';
                return;
            }
            
            sendBtn.disabled = true;
            sendBtn.textContent = 'Sending...';
            
            const formData = new FormData();
            formData.append('send_sms', '1');
            formData.append('contact', contact);
            
            fetch('login.php?mode=register', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    status.textContent = 'Code sent! Check your phone. (Demo: ' + data.demo_code + ')';
                    status.className = 'sms-status success';
                    
                    // Start cooldown
                    smsCooldown = 60;
                    const interval = setInterval(() => {
                        smsCooldown--;
                        sendBtn.textContent = `Resend (${smsCooldown}s)`;
                        if (smsCooldown <= 0) {
                            clearInterval(interval);
                            sendBtn.disabled = false;
                            sendBtn.textContent = 'Send Code';
                        }
                    }, 1000);
                } else {
                    status.textContent = data.message;
                    status.className = 'sms-status error';
                    sendBtn.disabled = false;
                    sendBtn.textContent = 'Send Code';
                }
            })
            .catch(error => {
                status.textContent = 'Error sending code. Try again.';
                status.className = 'sms-status error';
                sendBtn.disabled = false;
                sendBtn.textContent = 'Send Code';
            });
        }
    </script>
</body>
</html>

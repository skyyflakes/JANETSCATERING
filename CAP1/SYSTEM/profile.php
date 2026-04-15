<?php
/**
 * Profile Page - Janet's Quality Catering System
 * Complete CRUD with Photo Upload - Sneat Design
 */
$page_title = "My Profile | Janet's Quality Catering";
$current_page = 'profile';

require_once 'includes/auth_check.php';

$pdo = getDBConnection();

$success_message = '';
$error_message = '';
$user_data = [];
$active_tab = $_GET['tab'] ?? 'personal';

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
} catch (Exception $e) {
    $error_message = "Error fetching user data: " . $e->getMessage();
}

// Handle profile update with photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'update_profile') {
        try {
            $first_name = sanitize($_POST['first_name'] ?? '');
            $last_name = sanitize($_POST['last_name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $contact_number = sanitize($_POST['contact_number'] ?? '');
            $address = sanitize($_POST['address'] ?? '');
            
            // Handle profile photo upload
            $profile_photo = $user_data['profile_photo'];
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['size'] > 0) {
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                $file_ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
                
                if (in_array($file_ext, $allowed_ext) && $_FILES['profile_photo']['size'] < 5000000) {
                    $upload_dir = 'uploads/profile/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Delete old photo
                    if ($user_data['profile_photo'] && file_exists($user_data['profile_photo'])) {
                        unlink($user_data['profile_photo']);
                    }
                    
                    $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                        $profile_photo = $upload_path;
                    } else {
                        $error_message = "Failed to upload image.";
                    }
                } else {
                    $error_message = "Invalid file format or size exceeds 5MB.";
                }
            }
            
            if (empty($error_message)) {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, contact_number = ?, address = ?, profile_photo = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $email, $contact_number, $address, $profile_photo, $_SESSION['user_id']]);
                
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user_data = $stmt->fetch();
                
                $success_message = "Profile updated successfully!";
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'change_password') {
        try {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error_message = "All fields are required!";
            } elseif ($new_password !== $confirm_password) {
                $error_message = "New passwords do not match!";
            } elseif (strlen($new_password) < 6) {
                $error_message = "Password must be at least 6 characters!";
            } elseif (!password_verify($current_password, $user_data['password'])) {
                $error_message = "Current password is incorrect!";
            } else {
                $hashed = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed, $_SESSION['user_id']]);
                $success_message = "Password changed successfully!";
            }
            $active_tab = 'security';
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1">Account Settings</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Profile</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Alerts -->
<?php if ($success_message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bx bx-check-circle me-2"></i><?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bx bx-error-circle me-2"></i><?php echo $error_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <!-- Profile Header Card -->
        <div class="card mb-4" style="background: linear-gradient(135deg, var(--bs-primary) 0%, #8385ff 100%);">
            <div class="card-body py-4">
                <div class="d-flex align-items-center flex-wrap gap-4">
                    <div class="position-relative">
                        <?php if ($user_data['profile_photo'] && file_exists($user_data['profile_photo'])): ?>
                            <img src="<?php echo htmlspecialchars($user_data['profile_photo']); ?>?t=<?php echo time(); ?>" 
                                 alt="Profile" 
                                 style="width: 100px; height: 100px; border-radius: 50%; border: 4px solid rgba(255,255,255,0.3); object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 100px; height: 100px; border-radius: 50%; background: rgba(255,255,255,0.2); border: 4px solid rgba(255,255,255,0.3); display: flex; align-items: center; justify-content: center;">
                                <span style="font-size: 2.5rem; font-weight: 700; color: #fff;">
                                    <?php echo strtoupper(substr($user_data['first_name'] ?? $user_data['username'], 0, 1)); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 class="mb-1" style="color: #fff; font-weight: 600;">
                            <?php echo htmlspecialchars(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? '')); ?>
                            <?php if (empty($user_data['first_name'])): ?>
                                <?php echo htmlspecialchars($user_data['username']); ?>
                            <?php endif; ?>
                        </h4>
                        <span class="badge" style="background: rgba(255,255,255,0.2); color: #fff; font-size: 0.8125rem; padding: 6px 12px;">
                            <?php echo htmlspecialchars($user_data['role']); ?>
                        </span>
                        <p class="mb-0 mt-2" style="color: rgba(255,255,255,0.8); font-size: 0.875rem;">
                            <i class="bx bx-envelope me-1"></i><?php echo htmlspecialchars($user_data['email'] ?? 'No email set'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="card">
            <div class="card-body p-0">
                <ul class="nav nav-tabs" role="tablist" style="border-bottom: 1px solid var(--border-color);">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'personal' ? 'active' : ''; ?>" 
                           data-bs-toggle="tab" href="#personal" role="tab"
                           style="padding: 16px 24px; font-weight: 500; color: var(--body-color);">
                            <i class="bx bx-user me-2"></i>Personal Info
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'security' ? 'active' : ''; ?>" 
                           data-bs-toggle="tab" href="#security" role="tab"
                           style="padding: 16px 24px; font-weight: 500; color: var(--body-color);">
                            <i class="bx bx-lock-alt me-2"></i>Security
                        </a>
                    </li>
                </ul>

                <div class="tab-content p-4">
                    <!-- Personal Info Tab -->
                    <div class="tab-pane fade <?php echo $active_tab === 'personal' ? 'show active' : ''; ?>" id="personal" role="tabpanel">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <label class="form-label">Profile Photo</label>
                                </div>
                                <div class="col-md-9">
                                    <div class="d-flex align-items-center gap-4">
                                        <div class="position-relative" id="photoPreviewContainer">
                                            <?php if ($user_data['profile_photo'] && file_exists($user_data['profile_photo'])): ?>
                                                <img src="<?php echo htmlspecialchars($user_data['profile_photo']); ?>?t=<?php echo time(); ?>" 
                                                     alt="Profile" id="photoPreview"
                                                     style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover;">
                                            <?php else: ?>
                                                <div id="photoPreview" style="width: 80px; height: 80px; border-radius: 8px; background: rgba(105, 108, 255, 0.16); display: flex; align-items: center; justify-content: center;">
                                                    <i class="bx bx-user" style="font-size: 2rem; color: var(--bs-primary);"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/*" style="display: none;" onchange="previewPhoto(event)">
                                            <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('profilePhotoInput').click()">
                                                <i class="bx bx-upload me-1"></i> Upload Photo
                                            </button>
                                            <p class="text-muted mb-0 mt-2" style="font-size: 0.8125rem;">Allowed JPG, PNG, GIF. Max 5MB</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">First Name</label>
                                </div>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" name="first_name" 
                                           value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Last Name</label>
                                </div>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" name="last_name" 
                                           value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Email</label>
                                </div>
                                <div class="col-md-9">
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Contact Number</label>
                                </div>
                                <div class="col-md-9">
                                    <input type="tel" class="form-control" name="contact_number" 
                                           value="<?php echo htmlspecialchars($user_data['contact_number'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Address</label>
                                </div>
                                <div class="col-md-9">
                                    <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3"></div>
                                <div class="col-md-9">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bx bx-save me-1"></i> Save Changes
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Security Tab -->
                    <div class="tab-pane fade <?php echo $active_tab === 'security' ? 'show active' : ''; ?>" id="security" role="tabpanel">
                        <h5 class="mb-4" style="color: var(--heading-color);">Change Password</h5>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Current Password</label>
                                </div>
                                <div class="col-md-9">
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="current_password" id="currentPassword" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('currentPassword', 'toggleCurrent')">
                                            <i class="bx bx-hide" id="toggleCurrent"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">New Password</label>
                                </div>
                                <div class="col-md-9">
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="new_password" id="newPassword" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('newPassword', 'toggleNew')">
                                            <i class="bx bx-hide" id="toggleNew"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Confirm New Password</label>
                                </div>
                                <div class="col-md-9">
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirmPassword', 'toggleConfirm')">
                                            <i class="bx bx-hide" id="toggleConfirm"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3"></div>
                                <div class="col-md-9">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bx bx-lock me-1"></i> Change Password
                                    </button>
                                </div>
                            </div>
                        </form>

                        <hr class="my-4" style="border-color: var(--border-color);">

                        <h5 class="mb-4" style="color: var(--heading-color);">Account Information</h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <span class="text-muted">Username</span>
                            </div>
                            <div class="col-md-9">
                                <strong><?php echo htmlspecialchars($user_data['username']); ?></strong>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <span class="text-muted">Role</span>
                            </div>
                            <div class="col-md-9">
                                <span class="badge <?php echo $user_data['role'] === 'OWNER' ? 'badge-success' : 'badge-primary'; ?>">
                                    <?php echo htmlspecialchars($user_data['role']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <span class="text-muted">Account Status</span>
                            </div>
                            <div class="col-md-9">
                                <span class="badge badge-success">
                                    <i class="bx bx-check-circle me-1"></i>Active
                                </span>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <span class="text-muted">Account Created</span>
                            </div>
                            <div class="col-md-9">
                                <span><?php echo date('F d, Y', strtotime($user_data['created_at'])); ?></span>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <span class="text-muted">Last Updated</span>
                            </div>
                            <div class="col-md-9">
                                <span><?php echo date('F d, Y h:i A', strtotime($user_data['updated_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.nav-tabs .nav-link {
    border: none;
    border-bottom: 2px solid transparent;
    border-radius: 0;
}

.nav-tabs .nav-link:hover {
    border-bottom-color: var(--bs-primary);
    color: var(--bs-primary);
}

.nav-tabs .nav-link.active {
    border-bottom-color: var(--bs-primary);
    color: var(--bs-primary);
    background: transparent;
}
</style>

<script>
function previewPhoto(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const container = document.getElementById('photoPreviewContainer');
            container.innerHTML = '<img src="' + e.target.result + '" id="photoPreview" style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover;">';
        }
        reader.readAsDataURL(file);
    }
}

function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bx bx-show';
    } else {
        input.type = 'password';
        icon.className = 'bx bx-hide';
    }
}
</script>

<?php include 'includes/footer.php'; ?>

<?php
session_start();
require_once 'db_connection.php';
require_once 'migrate.php';
runMigration($pdo);

date_default_timezone_set('Asia/Manila');

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Helper to generate 6-digit OTP
function generateOtp() {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Helper to send OTP email via Brevo
function sendOtpEmail($email, $otpCode, $username) {
    $apiKey = getenv('BREVO_API_KEY');
    $senderEmail = getenv('BREVO_SENDER_EMAIL');
    $senderName = getenv('BREVO_SENDER_NAME') ?: 'BondNest';

    if (!$apiKey || !$senderEmail) {
        error_log("[EMAIL OTP DEV MODE] $email -> $otpCode");
        return true;
    }

    $payload = [
        'sender' => ['name' => $senderName, 'email' => $senderEmail],
        'to' => [['email' => $email, 'name' => $username ?: $email]],
        'subject' => 'BondNest - Verify your new email address',
        'htmlContent' => "<html><body style='font-family: Arial, sans-serif; color: #2F3E36;'>
            <h2>Verify your new email address</h2>
            <p>Hello " . htmlspecialchars($username) . ",</p>
            <p>You requested to update your email address on BondNest. Your verification code is:</p>
            <p style='font-size: 28px; font-weight: bold; letter-spacing: 6px; color: #008080;'>$otpCode</p>
            <p>This code expires in 10 minutes. If you did not request this change, you can safely ignore this email.</p>
        </body></html>",
        'textContent' => "Your BondNest email verification code is $otpCode. It expires in 10 minutes.",
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'api-key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode >= 200 && $httpCode < 300);
}

// ── AJAX / POST HANDLERS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Check Username Availability
    if (isset($_POST['check_username']) || $action === 'check_username') {
        header('Content-Type: application/json');
        $username = trim($_POST['username'] ?? '');
        if (strlen($username) < 3) {
            echo json_encode(['available' => false, 'message' => 'Username must be at least 3 characters']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $user_id]);
        $exists = $stmt->fetch();
        if ($exists) {
            echo json_encode(['available' => false, 'message' => 'Username is already taken']);
        } else {
            echo json_encode(['available' => true, 'message' => 'Username is available']);
        }
        exit;
    }

    // 2. Upload Profile Picture
    if ($action === 'upload_photo' && isset($_FILES['profile_picture'])) {
        header('Content-Type: application/json');
        $file = $_FILES['profile_picture'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'File upload failed. Please try again.']);
            exit;
        }
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Invalid image format. Please upload JPG, PNG, GIF, or WebP.']);
            exit;
        }

        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'uploads/pfp_' . $user_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $filename)) {
            $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->execute([$filename, $user_id]);
            echo json_encode(['success' => true, 'profile_picture' => $filename, 'message' => 'Profile picture updated!']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Could not save uploaded picture.']);
        }
        exit;
    }

    // 3. Save Personal Information
    if ($action === 'save_personal') {
        header('Content-Type: application/json');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $bio = trim($_POST['bio'] ?? '');
        $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
        $gender = trim($_POST['gender'] ?? '');
        $birthday = !empty($_POST['birthday']) ? $_POST['birthday'] : null;
        $location = trim($_POST['location'] ?? '');
        $interests = trim($_POST['interests'] ?? '');
        $website = trim($_POST['website'] ?? '');

        if (!$first_name || !$last_name || !$username) {
            echo json_encode(['success' => false, 'error' => 'First name, last name, and username are required.']);
            exit;
        }

        if (strlen($bio) > 0 && strlen($bio) < 10) {
            echo json_encode(['success' => false, 'error' => 'Bio must be at least 10 characters long.']);
            exit;
        }

        // Check username uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $user_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Username is already taken by another account.']);
            exit;
        }

        // Get current user row
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $currUser = $stmt->fetch(PDO::FETCH_ASSOC);
        $currEmail = strtolower(trim($currUser['email'] ?? ''));

        // Check if email changed
        if ($email && $email !== $currEmail) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
                exit;
            }

            // Check if email taken by someone else
            $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'This email address is already registered to another account.']);
                exit;
            }

            // Generate OTP & store challenge
            $otpCode = generateOtp();
            $otpExpires = gmdate('Y-m-d H:i:s', time() + 600); // 10 mins
            $pendingPayload = json_encode([
                'first_name' => $first_name,
                'last_name' => $last_name,
                'username' => $username,
                'email' => $email,
                'bio' => $bio,
                'age' => $age,
                'gender' => $gender,
                'birthday' => $birthday,
                'location' => $location,
                'interests' => $interests,
                'website' => $website,
            ]);

            // Upsert challenge
            $stmt = $pdo->prepare("DELETE FROM email_change_challenges WHERE user_id = ?");
            $stmt->execute([$user_id]);

            $stmt = $pdo->prepare("INSERT INTO email_change_challenges (user_id, new_email, otp_code, otp_expires_at, pending_payload) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $email, $otpCode, $otpExpires, $pendingPayload]);

            sendOtpEmail($email, $otpCode, $username);

            echo json_encode([
                'success' => true,
                'email_change_required' => true,
                'new_email' => $email,
                'message' => "We've sent a 6-digit verification code to $email. Enter it to verify your new email.",
            ]);
            exit;
        }

        // Direct update (email unchanged)
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, bio = ?, age = ?, gender = ?, birthday = ?, location = ?, interests = ?, website = ? WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $username, $bio, $age, $gender, $birthday, $location, $interests, $website, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Personal information saved successfully!']);
        exit;
    }

    // 4. Verify Email OTP
    if ($action === 'verify_email_otp') {
        header('Content-Type: application/json');
        $code = trim($_POST['otp_code'] ?? '');
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            echo json_encode(['success' => false, 'error' => 'Please enter the 6-digit verification code.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM email_change_challenges WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$challenge) {
            echo json_encode(['success' => false, 'error' => 'No pending email change found. Please save your information again.']);
            exit;
        }

        $expiresAt = strtotime($challenge['otp_expires_at'] . ' UTC');
        if (time() > $expiresAt || $challenge['otp_code'] !== $code) {
            echo json_encode(['success' => false, 'error' => 'Invalid or expired verification code.']);
            exit;
        }

        $pending = json_decode($challenge['pending_payload'], true);
        if (!$pending) {
            echo json_encode(['success' => false, 'error' => 'Invalid pending data. Please try again.']);
            exit;
        }

        // Apply full update
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, email = ?, bio = ?, age = ?, gender = ?, birthday = ?, location = ?, interests = ?, website = ? WHERE id = ?");
        $stmt->execute([
            $pending['first_name'],
            $pending['last_name'],
            $pending['username'],
            $pending['email'],
            $pending['bio'],
            $pending['age'],
            $pending['gender'],
            $pending['birthday'],
            $pending['location'],
            $pending['interests'],
            $pending['website'],
            $user_id
        ]);

        // Clear challenge
        $del = $pdo->prepare("DELETE FROM email_change_challenges WHERE user_id = ?");
        $del->execute([$user_id]);

        echo json_encode(['success' => true, 'message' => 'Email verified and personal information updated successfully!']);
        exit;
    }

    // 5. Resend Email OTP
    if ($action === 'resend_email_otp') {
        header('Content-Type: application/json');
        $stmt = $pdo->prepare("SELECT * FROM email_change_challenges WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$challenge) {
            echo json_encode(['success' => false, 'error' => 'No pending email change request found.']);
            exit;
        }

        $newOtp = generateOtp();
        $newExpires = gmdate('Y-m-d H:i:s', time() + 600);

        $upd = $pdo->prepare("UPDATE email_change_challenges SET otp_code = ?, otp_expires_at = ? WHERE user_id = ?");
        $upd->execute([$newOtp, $newExpires, $user_id]);

        $pending = json_decode($challenge['pending_payload'], true);
        $username = $pending['username'] ?? '';
        sendOtpEmail($challenge['new_email'], $newOtp, $username);

        echo json_encode(['success' => true, 'message' => 'A new 6-digit verification code has been sent.']);
        exit;
    }

    // 6. Change Password
    if ($action === 'change_password') {
        header('Content-Type: application/json');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!$current_password || !$new_password || !$confirm_password) {
            echo json_encode(['success' => false, 'error' => 'All password fields are required.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userRow || !password_verify($current_password, $userRow['password'])) {
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect.']);
            exit;
        }

        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'error' => 'New passwords do not match.']);
            exit;
        }

        if (strlen($new_password) < 8) {
            echo json_encode(['success' => false, 'error' => 'New password must be at least 8 characters long.']);
            exit;
        }

        if (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password) || !preg_match('/[\W_]/', $new_password)) {
            echo json_encode(['success' => false, 'error' => 'Password must include uppercase, lowercase, number, and special character.']);
            exit;
        }

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->execute([$new_hash, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Password updated successfully!']);
        exit;
    }
}

// Fetch fresh user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "User not found";
    exit;
}

$full_name = htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$username = htmlspecialchars($user['username'] ?? '');
$email = htmlspecialchars($user['email'] ?? '');
$bio = htmlspecialchars($user['bio'] ?? '');
$age = htmlspecialchars($user['age'] ?? '');
$gender = htmlspecialchars($user['gender'] ?? '');
$birthday = htmlspecialchars($user['birthday'] ?? '');
$location = htmlspecialchars($user['location'] ?? '');
$interests = htmlspecialchars($user['interests'] ?? '');
$website = htmlspecialchars($user['website'] ?? '');
$profile_picture = !empty($user['profile_picture']) ? $user['profile_picture'] : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BondNest | Settings</title>
    <!-- Bootstrap Icons & FontAwesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="homepage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="homepage2.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="settings.css?v=<?php echo time(); ?>">
</head>
<body class="settings-page">
    <?php include 'navbar.php'; ?>
    <?php include 'mobile-nav.php'; ?>

    <main>
        <div class="container">
            <?php include 'sidebar.php'; ?>

            <div class="middle">
                <div class="settings-content">
                    <!-- Page Header -->
                    <div class="settings-header">
                        <h1 class="settings-title">Settings</h1>
                        <p class="settings-subtitle">Manage your personal information and security preferences</p>
                    </div>

                    <!-- Navigation Tabs -->
                    <div class="settings-tabs">
                        <button type="button" class="settings-tab-btn active" data-tab="personal">
                            <i class="bi bi-person"></i> Personal Information
                        </button>
                        <button type="button" class="settings-tab-btn" data-tab="security">
                            <i class="bi bi-shield-lock"></i> Security
                        </button>
                    </div>

                    <!-- 1. Personal Information Panel -->
                    <div class="settings-panel active" id="personalPanel">
                        <div class="settings-card">
                            <h2 class="settings-card-title">
                                <i class="bi bi-person-badge"></i> Profile Details
                            </h2>

                            <!-- Avatar Changer -->
                            <div class="settings-avatar-section">
                                <div class="settings-avatar-wrap" id="avatarPreviewContainer">
                                    <?php if (!empty($profile_picture)): ?>
                                        <img src="<?php echo $profile_picture; ?>" alt="Profile Picture" id="currentAvatarImg">
                                    <?php else: ?>
                                        <?php echo getInitialsHtml($user['first_name'], $user['last_name'], 100); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="settings-avatar-info">
                                    <h3 class="settings-avatar-name"><?php echo $full_name; ?></h3>
                                    <p class="settings-avatar-hint">JPG, PNG, GIF, or WebP. Max 5MB.</p>
                                    <input type="file" id="profilePictureInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
                                    <button type="button" class="settings-change-photo-btn" id="changePhotoBtn">
                                        <i class="bi bi-camera"></i> Change Photo
                                    </button>
                                </div>
                            </div>

                            <!-- Personal Details Form -->
                            <form id="personalInfoForm">
                                <div class="settings-form-grid">
                                    <div class="settings-field">
                                        <label class="settings-label" for="firstName">First Name</label>
                                        <div class="settings-input-wrap">
                                            <i class="bi bi-person settings-input-icon"></i>
                                            <input type="text" id="firstName" name="first_name" class="settings-input" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <div class="settings-field">
                                        <label class="settings-label" for="lastName">Last Name</label>
                                        <div class="settings-input-wrap">
                                            <i class="bi bi-person settings-input-icon"></i>
                                            <input type="text" id="lastName" name="last_name" class="settings-input" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <div class="settings-field">
                                        <label class="settings-label" for="username">Username</label>
                                        <div class="settings-input-wrap">
                                            <i class="bi bi-at settings-input-icon"></i>
                                            <input type="text" id="username" name="username" class="settings-input" value="<?php echo $username; ?>" required>
                                        </div>
                                        <div class="username-feedback" id="usernameFeedback"></div>
                                    </div>

                                    <div class="settings-field">
                                        <label class="settings-label" for="email">Email Address <small>(Verification required if changed)</small></label>
                                        <div class="settings-input-wrap">
                                            <i class="bi bi-envelope settings-input-icon"></i>
                                            <input type="email" id="email" name="email" class="settings-input" value="<?php echo $email; ?>" required>
                                        </div>
                                    </div>

                                    <div class="settings-field">
                                        <label class="settings-label" for="gender">Gender</label>
                                        <div class="settings-input-wrap">
                                            <i class="bi bi-gender-ambiguous settings-input-icon"></i>
                                            <select id="gender" name="gender" class="settings-input">
                                                <option value="" disabled <?php echo empty($gender) ? 'selected' : ''; ?>>Select gender</option>
                                                <option value="Male" <?php echo $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                                                <option value="Non-binary" <?php echo $gender === 'Non-binary' ? 'selected' : ''; ?>>Non-binary</option>
                                                <option value="Other" <?php echo $gender === 'Other' ? 'selected' : ''; ?>>Other</option>
                                                <option value="Prefer not to say" <?php echo $gender === 'Prefer not to say' ? 'selected' : ''; ?>>Prefer not to say</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="settings-field">
                                        <label class="settings-label" for="birthday">Birthday</label>
                                        <div class="settings-input-wrap">
                                            <i class="bi bi-calendar3 settings-input-icon"></i>
                                            <input type="date" id="birthday" name="birthday" class="settings-input" value="<?php echo $birthday; ?>">
                                        </div>
                                    </div>

                                    <div class="settings-field">
                                        <label class="settings-label" for="age">Age</label>
                                        <div class="settings-input-wrap">
                                            <i class="bi bi-hash settings-input-icon"></i>
                                            <input type="number" id="age" name="age" class="settings-input" max="120" value="<?php echo $age; ?>">
                                        </div>
                                    </div>

                                    <div class="settings-field">
                                        <label class="settings-label" for="location">Location</label>
                                        <div class="settings-input-wrap">
                                            <i class="bi bi-geo-alt settings-input-icon"></i>
                                            <input type="text" id="location" name="location" class="settings-input" value="<?php echo $location; ?>" placeholder="City, Country">
                                        </div>
                                    </div>

                                    <div class="settings-field">
                                        <label class="settings-label" for="interests">Interests</label>
                                        <div class="settings-input-wrap">
                                            <i class="bi bi-heart settings-input-icon"></i>
                                            <input type="text" id="interests" name="interests" class="settings-input" value="<?php echo $interests; ?>" placeholder="Photography, Coding, Music...">
                                        </div>
                                    </div>

                                    <div class="settings-field">
                                        <label class="settings-label" for="website">Website</label>
                                        <div class="settings-input-wrap">
                                            <i class="bi bi-link-45deg settings-input-icon"></i>
                                            <input type="text" id="website" name="website" class="settings-input" value="<?php echo $website; ?>" placeholder="https://example.com">
                                        </div>
                                    </div>

                                    <div class="settings-field full-width">
                                        <label class="settings-label" for="bio">Bio <small>(Minimum 10 characters)</small></label>
                                        <textarea id="bio" name="bio" class="settings-input no-icon" rows="4" minlength="10" placeholder="Tell us about yourself..."><?php echo $bio; ?></textarea>
                                        <div class="char-counter"><span id="bioCharCount"><?php echo strlen($bio); ?></span>/10 characters min</div>
                                    </div>
                                </div>

                                <div class="settings-form-actions">
                                    <button type="reset" class="settings-btn settings-btn--secondary">Reset</button>
                                    <button type="submit" class="settings-btn settings-btn--primary" id="savePersonalBtn">
                                        <i class="bi bi-check2"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 2. Security Panel -->
                    <div class="settings-panel" id="securityPanel">
                        <div class="settings-card">
                            <h2 class="settings-card-title">
                                <i class="bi bi-shield-lock"></i> Password & Security
                            </h2>

                            <div class="security-status-banner">
                                <i class="bi bi-shield-check"></i>
                                <span>Your account is protected. Use a strong, unique password to maintain account security.</span>
                            </div>

                            <form id="securityForm">
                                <div class="settings-form-grid">
                                    <div class="settings-field full-width">
                                        <label class="settings-label" for="currentPassword">Current Password</label>
                                        <div class="settings-input-wrap">
                                            <i class="bi bi-lock settings-input-icon"></i>
                                            <input type="password" id="currentPassword" name="current_password" class="settings-input" placeholder="Enter current password" required>
                                            <button type="button" class="settings-reveal-btn" data-toggle-pw="currentPassword">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="settings-field">
                                        <label class="settings-label" for="newPassword">New Password</label>
                                        <div class="settings-input-wrap">
                                            <i class="bi bi-key settings-input-icon"></i>
                                            <input type="password" id="newPassword" name="new_password" class="settings-input" placeholder="New password" required>
                                            <button type="button" class="settings-reveal-btn" data-toggle-pw="newPassword">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="settings-field">
                                        <label class="settings-label" for="confirmPassword">Confirm New Password</label>
                                        <div class="settings-input-wrap">
                                            <i class="bi bi-key-fill settings-input-icon"></i>
                                            <input type="password" id="confirmPassword" name="confirm_password" class="settings-input" placeholder="Confirm new password" required>
                                            <button type="button" class="settings-reveal-btn" data-toggle-pw="confirmPassword">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Password Policy Checklist -->
                                    <div class="password-policy-card">
                                        <h4 class="password-policy-title">Password Requirements:</h4>
                                        <ul class="password-policy-list">
                                            <li class="policy-item" id="rule-length"><i class="bi bi-circle"></i> At least 8 characters</li>
                                            <li class="policy-item" id="rule-upper"><i class="bi bi-circle"></i> 1 uppercase letter</li>
                                            <li class="policy-item" id="rule-lower"><i class="bi bi-circle"></i> 1 lowercase letter</li>
                                            <li class="policy-item" id="rule-number"><i class="bi bi-circle"></i> 1 number</li>
                                            <li class="policy-item" id="rule-special"><i class="bi bi-circle"></i> 1 special character</li>
                                            <li class="policy-item" id="rule-match"><i class="bi bi-circle"></i> Passwords match</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="settings-form-actions">
                                    <button type="submit" class="settings-btn settings-btn--primary" id="savePasswordBtn">
                                        <i class="bi bi-lock-fill"></i> Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Email Change OTP Verification Modal (DiariCore Style) -->
    <div class="settings-modal-overlay" id="emailOtpModal" style="display: none;">
        <div class="settings-modal-card">
            <button type="button" class="settings-modal-close" id="closeEmailOtpModal">&times;</button>
            <div class="otp-icon-wrap">
                <i class="bi bi-envelope-check"></i>
            </div>
            <h3 class="otp-modal-title">Verify new email</h3>
            <p class="otp-modal-lead" id="otpModalLead">We've sent a 6-digit verification code to your new email address. Please enter it below to proceed.</p>

            <div class="otp-digits-container" id="otpDigitsGroup">
                <input type="text" inputmode="numeric" maxlength="1" class="otp-digit-input" data-index="0" autocomplete="one-time-code">
                <input type="text" inputmode="numeric" maxlength="1" class="otp-digit-input" data-index="1">
                <input type="text" inputmode="numeric" maxlength="1" class="otp-digit-input" data-index="2">
                <input type="text" inputmode="numeric" maxlength="1" class="otp-digit-input" data-index="3">
                <input type="text" inputmode="numeric" maxlength="1" class="otp-digit-input" data-index="4">
                <input type="text" inputmode="numeric" maxlength="1" class="otp-digit-input" data-index="5">
            </div>

            <div class="otp-alert" id="otpErrorAlert" style="display: none;">
                <span id="otpErrorText"></span>
            </div>

            <p class="otp-resend-row">
                Didn't receive the code?
                <button type="button" class="otp-resend-btn" id="resendOtpBtn" disabled>Resend Code</button>
                <span id="resendTimer">(60s)</span>
            </p>

            <div class="otp-actions">
                <button type="button" class="otp-verify-btn" id="verifyOtpBtn">
                    <i class="bi bi-check-circle"></i> Verify & Save
                </button>
                <button type="button" class="otp-cancel-btn" id="cancelOtpBtn">Cancel</button>
            </div>
        </div>
    </div>

    <!-- JavaScript logic -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ── Tabs Switching ──
        const tabBtns = document.querySelectorAll('.settings-tab-btn');
        const personalPanel = document.getElementById('personalPanel');
        const securityPanel = document.getElementById('securityPanel');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                tabBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const target = this.dataset.tab;
                if (target === 'personal') {
                    personalPanel.classList.add('active');
                    securityPanel.classList.remove('active');
                } else {
                    securityPanel.classList.add('active');
                    personalPanel.classList.remove('active');
                }
            });
        });

        // ── Toast Notification Helper ──
        function showToast(message, type = 'success') {
            const existing = document.querySelector('.settings-toast');
            if (existing) existing.remove();

            const toast = document.createElement('div');
            toast.className = `settings-toast settings-toast--${type}`;
            toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> <span>${message}</span>`;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'fadeIn 0.3s ease reverse';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        // ── Profile Picture Upload & Preview ──
        const changePhotoBtn = document.getElementById('changePhotoBtn');
        const profilePictureInput = document.getElementById('profilePictureInput');
        const avatarPreviewContainer = document.getElementById('avatarPreviewContainer');

        if (changePhotoBtn && profilePictureInput) {
            changePhotoBtn.addEventListener('click', () => profilePictureInput.click());

            profilePictureInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const formData = new FormData();
                    formData.append('action', 'upload_photo');
                    formData.append('profile_picture', this.files[0]);

                    changePhotoBtn.disabled = true;
                    changePhotoBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

                    fetch('settings.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        changePhotoBtn.disabled = false;
                        changePhotoBtn.innerHTML = '<i class="bi bi-camera"></i> Change Photo';
                        if (data.success) {
                            avatarPreviewContainer.innerHTML = `<img src="${data.profile_picture}?t=${Date.now()}" alt="Profile Picture" id="currentAvatarImg">`;
                            showToast(data.message, 'success');
                        } else {
                            showToast(data.error || 'Upload failed.', 'error');
                        }
                    })
                    .catch(() => {
                        changePhotoBtn.disabled = false;
                        changePhotoBtn.innerHTML = '<i class="bi bi-camera"></i> Change Photo';
                        showToast('Error uploading profile picture.', 'error');
                    });
                }
            });
        }

        // ── Bio Character Counter ──
        const bioInput = document.getElementById('bio');
        const bioCharCount = document.getElementById('bioCharCount');
        if (bioInput && bioCharCount) {
            bioInput.addEventListener('input', function() {
                bioCharCount.textContent = this.value.length;
            });
        }

        // ── Username Real-time Check ──
        const usernameInput = document.getElementById('username');
        const usernameFeedback = document.getElementById('usernameFeedback');
        let usernameTimer;
        if (usernameInput && usernameFeedback) {
            usernameInput.addEventListener('input', function() {
                clearTimeout(usernameTimer);
                const val = this.value.trim();
                if (val.length < 3) {
                    usernameFeedback.textContent = 'Username must be at least 3 characters';
                    usernameFeedback.style.color = '#e74c3c';
                    return;
                }
                usernameTimer = setTimeout(() => {
                    const fd = new FormData();
                    fd.append('action', 'check_username');
                    fd.append('username', val);

                    fetch('settings.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(d => {
                            usernameFeedback.textContent = d.message;
                            usernameFeedback.style.color = d.available ? '#27ae60' : '#e74c3c';
                        });
                }, 300);
            });
        }

        // ── Password Visibility Toggles ──
        document.querySelectorAll('[data-toggle-pw]').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.dataset.togglePw;
                const field = document.getElementById(targetId);
                if (field) {
                    const isPassword = field.type === 'password';
                    field.type = isPassword ? 'text' : 'password';
                    this.innerHTML = `<i class="bi bi-eye${isPassword ? '' : '-slash'}"></i>`;
                }
            });
        });

        // ── Live Password Strength Meter ──
        const newPasswordInput = document.getElementById('newPassword');
        const confirmPasswordInput = document.getElementById('confirmPassword');

        const ruleLength = document.getElementById('rule-length');
        const ruleUpper = document.getElementById('rule-upper');
        const ruleLower = document.getElementById('rule-lower');
        const ruleNumber = document.getElementById('rule-number');
        const ruleSpecial = document.getElementById('rule-special');
        const ruleMatch = document.getElementById('rule-match');

        function setRule(el, valid) {
            if (!el) return;
            el.className = 'policy-item ' + (valid ? 'valid' : 'invalid');
            el.querySelector('i').className = 'bi bi-' + (valid ? 'check-circle-fill' : 'circle');
        }

        function validatePasswordRules() {
            const val = newPasswordInput ? newPasswordInput.value : '';
            const conf = confirmPasswordInput ? confirmPasswordInput.value : '';

            setRule(ruleLength, val.length >= 8);
            setRule(ruleUpper, /[A-Z]/.test(val));
            setRule(ruleLower, /[a-z]/.test(val));
            setRule(ruleNumber, /[0-9]/.test(val));
            setRule(ruleSpecial, /[\W_]/.test(val));
            setRule(ruleMatch, val.length > 0 && val === conf);
        }

        if (newPasswordInput) newPasswordInput.addEventListener('input', validatePasswordRules);
        if (confirmPasswordInput) confirmPasswordInput.addEventListener('input', validatePasswordRules);

        // ── Personal Info Form Submission ──
        const personalInfoForm = document.getElementById('personalInfoForm');
        const savePersonalBtn = document.getElementById('savePersonalBtn');

        if (personalInfoForm) {
            personalInfoForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const fd = new FormData(this);
                fd.append('action', 'save_personal');

                savePersonalBtn.disabled = true;
                savePersonalBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

                fetch('settings.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    savePersonalBtn.disabled = false;
                    savePersonalBtn.innerHTML = '<i class="bi bi-check2"></i> Save Changes';

                    if (data.email_change_required) {
                        openEmailOtpModal(data.new_email);
                    } else if (data.success) {
                        showToast(data.message, 'success');
                    } else {
                        showToast(data.error || 'Failed to save changes.', 'error');
                    }
                })
                .catch(() => {
                    savePersonalBtn.disabled = false;
                    savePersonalBtn.innerHTML = '<i class="bi bi-check2"></i> Save Changes';
                    showToast('An error occurred while saving.', 'error');
                });
            });
        }

        // ── Email Change OTP Modal & Verification ──
        const emailOtpModal = document.getElementById('emailOtpModal');
        const otpModalLead = document.getElementById('otpModalLead');
        const otpErrorAlert = document.getElementById('otpErrorAlert');
        const otpErrorText = document.getElementById('otpErrorText');
        const digitInputs = document.querySelectorAll('.otp-digit-input');
        const resendOtpBtn = document.getElementById('resendOtpBtn');
        const resendTimer = document.getElementById('resendTimer');
        const verifyOtpBtn = document.getElementById('verifyOtpBtn');
        const closeEmailOtpModal = document.getElementById('closeEmailOtpModal');
        const cancelOtpBtn = document.getElementById('cancelOtpBtn');

        let resendCountdown = 60;
        let resendInterval = null;

        function startResendTimer() {
            clearInterval(resendInterval);
            resendCountdown = 60;
            resendOtpBtn.disabled = true;
            resendTimer.textContent = `(${resendCountdown}s)`;

            resendInterval = setInterval(() => {
                resendCountdown--;
                if (resendCountdown <= 0) {
                    clearInterval(resendInterval);
                    resendOtpBtn.disabled = false;
                    resendTimer.textContent = '';
                } else {
                    resendTimer.textContent = `(${resendCountdown}s)`;
                }
            }, 1000);
        }

        function openEmailOtpModal(newEmail) {
            otpModalLead.textContent = `We've sent a 6-digit verification code to ${newEmail}. Please enter it below to proceed.`;
            otpErrorAlert.style.display = 'none';
            digitInputs.forEach(input => input.value = '');
            emailOtpModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            startResendTimer();
            if (digitInputs[0]) digitInputs[0].focus();
        }

        function closeModal() {
            emailOtpModal.style.display = 'none';
            document.body.style.overflow = '';
            clearInterval(resendInterval);
        }

        if (closeEmailOtpModal) closeEmailOtpModal.addEventListener('click', closeModal);
        if (cancelOtpBtn) cancelOtpBtn.addEventListener('click', closeModal);

        // Digit inputs navigation & paste handler
        digitInputs.forEach((input, index) => {
            input.addEventListener('input', function(e) {
                const val = this.value.replace(/\D/g, '').slice(-1);
                this.value = val;
                if (val && index < digitInputs.length - 1) {
                    digitInputs[index + 1].focus();
                }
                checkAutoVerify();
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !this.value && index > 0) {
                    digitInputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                if (paste) {
                    paste.split('').forEach((char, i) => {
                        if (digitInputs[i]) digitInputs[i].value = char;
                    });
                    const nextFocus = Math.min(paste.length, digitInputs.length - 1);
                    if (digitInputs[nextFocus]) digitInputs[nextFocus].focus();
                    checkAutoVerify();
                }
            });
        });

        function getEnteredOtp() {
            let code = '';
            digitInputs.forEach(i => code += i.value);
            return code;
        }

        function checkAutoVerify() {
            const code = getEnteredOtp();
            if (code.length === 6) {
                submitVerifyOtp();
            }
        }

        function submitVerifyOtp() {
            const code = getEnteredOtp();
            if (code.length !== 6) {
                otpErrorText.textContent = 'Please enter all 6 digits.';
                otpErrorAlert.style.display = 'flex';
                return;
            }

            verifyOtpBtn.disabled = true;
            verifyOtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

            const fd = new FormData();
            fd.append('action', 'verify_email_otp');
            fd.append('otp_code', code);

            fetch('settings.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    verifyOtpBtn.disabled = false;
                    verifyOtpBtn.innerHTML = '<i class="bi bi-check-circle"></i> Verify & Save';

                    if (data.success) {
                        closeModal();
                        showToast(data.message, 'success');
                    } else {
                        otpErrorText.textContent = data.error || 'Verification failed.';
                        otpErrorAlert.style.display = 'flex';
                    }
                })
                .catch(() => {
                    verifyOtpBtn.disabled = false;
                    verifyOtpBtn.innerHTML = '<i class="bi bi-check-circle"></i> Verify & Save';
                    otpErrorText.textContent = 'Network error. Please try again.';
                    otpErrorAlert.style.display = 'flex';
                });
        }

        if (verifyOtpBtn) verifyOtpBtn.addEventListener('click', submitVerifyOtp);

        if (resendOtpBtn) {
            resendOtpBtn.addEventListener('click', function() {
                this.disabled = true;
                const fd = new FormData();
                fd.append('action', 'resend_email_otp');

                fetch('settings.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.message, 'success');
                            startResendTimer();
                        } else {
                            otpErrorText.textContent = data.error || 'Could not resend code.';
                            otpErrorAlert.style.display = 'flex';
                            resendOtpBtn.disabled = false;
                        }
                    });
            });
        }

        // ── Security Form Submission ──
        const securityForm = document.getElementById('securityForm');
        const savePasswordBtn = document.getElementById('savePasswordBtn');

        if (securityForm) {
            securityForm.addEventListener('submit', function(e) {
                e.preventDefault();

                savePasswordBtn.disabled = true;
                savePasswordBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

                const fd = new FormData(this);
                fd.append('action', 'change_password');

                fetch('settings.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        savePasswordBtn.disabled = false;
                        savePasswordBtn.innerHTML = '<i class="bi bi-lock-fill"></i> Update Password';

                        if (data.success) {
                            showToast(data.message, 'success');
                            securityForm.reset();
                            validatePasswordRules();
                        } else {
                            showToast(data.error || 'Password update failed.', 'error');
                        }
                    })
                    .catch(() => {
                        savePasswordBtn.disabled = false;
                        savePasswordBtn.innerHTML = '<i class="bi bi-lock-fill"></i> Update Password';
                        showToast('Error updating password.', 'error');
                    });
            });
        }
    });
    </script>
</body>
</html>

<?php


require_once 'db_connection.php';
require_once 'migrate.php';
runMigration($pdo);

function generateRecoveryCode() {
    return bin2hex(random_bytes(5)); // 10-character code
}

// Handle all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Check which action is being requested
    $action = '';
    if (isset($_POST['forgot_password'])) $action = 'forgot_password';
    if (isset($_POST['reset_password'])) $action = 'reset_password';
    if (isset($_POST['check_username'])) $action = 'check_username';
    if (isset($_POST['email']) || isset($_POST['firstName'])) $action = 'signup';
    else if (isset($_FILES['profilePicture'])) $action = 'signup';
    if (isset($_POST['username']) && isset($_POST['password']) && $action === '') $action = 'login';
    
    switch ($action) {
        case 'signup':
            $errors = [];
            $required = ['firstName', 'lastName', 'username', 'email', 'birthday', 'gender', 'createPassword', 'confirmPassword'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    $errors[] = ucfirst($field) . ' is required.';
                }
            }

            // Email validation
            if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format.';
            }

            if ($_POST['createPassword'] !== $_POST['confirmPassword']) {
                $errors[] = 'Passwords do not match.';
            }

            if (strlen($_POST['createPassword']) < 12) {
                $errors[] = 'Password must be at least 12 characters.';
            } elseif (strlen($_POST['createPassword']) > 64) {
                $errors[] = 'Password must not exceed 64 characters.';
            } elseif (strpos($_POST['createPassword'], ' ') !== false) {
                $errors[] = 'Password must not contain spaces.';
            } elseif (!preg_match('/[A-Z]/', $_POST['createPassword'])) {
                $errors[] = 'Password must include at least one uppercase letter (A–Z).';
            } elseif (!preg_match('/[a-z]/', $_POST['createPassword'])) {
                $errors[] = 'Password must include at least one lowercase letter (a–z).';
            } elseif (!preg_match('/[0-9]/', $_POST['createPassword'])) {
                $errors[] = 'Password must include at least one digit (0–9).';
            } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:\'",.<>?\/`~\\\\]/', $_POST['createPassword'])) {
                $errors[] = 'Password must include at least one special character (!@#$…).';
            } else {
                $common = ['password','12345678','123456789','qwerty','qwerty123','111111','iloveyou','admin','welcome','monkey','dragon','letmein','abc123','password1'];
                if (in_array(strtolower($_POST['createPassword']), $common)) {
                    $errors[] = 'This password is too common. Choose a less predictable password.';
                } else {
                    $pl = strtolower($_POST['createPassword']);
                    $personal = [strtolower($_POST['username']??''), strtolower($_POST['email']??''), strtolower($_POST['firstName']??''), strtolower($_POST['lastName']??'')];
                    foreach ($personal as $idx=>$tok) {
                        $t = trim($tok);
                        if (strlen($t) >= 2 && strpos($pl, $t) !== false) {
                            $labels = ['username','email address','first name','last name'];
                            $errors[] = 'Password must not contain your ' . $labels[$idx] . '.';
                            break;
                        }
                    }
                }
            }

            $profile_picture = null;
            if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif'];
                $detected = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $_FILES['profilePicture']['tmp_name']);
                
                if (in_array($detected, $allowed)) {
                    $dir = $upload_dir . '/profile_pictures/';
                    if (!file_exists($dir)) mkdir($dir, 0755, true);
                    
                    $ext = pathinfo($_FILES['profilePicture']['name'], PATHINFO_EXTENSION);
                    $filename = uniqid() . '.' . $ext;
                    $target = $dir . $filename;
                    
                    if (move_uploaded_file($_FILES['profilePicture']['tmp_name'], $target)) {
                        $profile_picture = 'uploads/profile_pictures/' . $filename;
                    } else {
                        $errors[] = 'Failed to upload image.';
                    }
                } else {
                    $errors[] = 'Invalid file type. Only images allowed.';
                }
            }

            // Email uniqueness check
            if (empty($errors) && !empty($_POST['email'])) {
                try {
                    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                    $check->execute([$_POST['email']]);
                    if ($check->fetch()) {
                        $errors[] = 'Email is already registered.';
                    }
                } catch (PDOException $e) { /* ignore if column missing before migration */ }
                try {
                    $checkU = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                    $checkU->execute([$_POST['username']]);
                    if ($checkU->fetch()) {
                        $errors[] = 'Username is already taken.';
                    }
                } catch (PDOException $e) {}
            }

            if (empty($errors)) {
                $recovery_code = generateRecoveryCode();
                $hashed_pw = password_hash($_POST['createPassword'], PASSWORD_DEFAULT);
                // Compute age from birthday for legacy column (nullable)
                $computedAge = null;
                if (!empty($_POST['birthday'])) {
                    try {
                        $b = new DateTime($_POST['birthday']);
                        $computedAge = (new DateTime())->diff($b)->y;
                    } catch (Exception $e) { $computedAge = null; }
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("INSERT INTO users 
                        (first_name, last_name, username, email, age, birthday, gender, password, profile_picture, recovery_code) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
                        htmlspecialchars($_POST['firstName']),
                        htmlspecialchars($_POST['lastName']),
                        htmlspecialchars($_POST['username']),
                        $_POST['email'],
                        $computedAge,
                        $_POST['birthday'],
                        $_POST['gender'],
                        $hashed_pw,
                        $profile_picture,
                        $recovery_code
                    ]);
                    
                    $pdo->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'recovery_code' => $recovery_code
                    ]);
                    exit;
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'errors' => ["Database error. Please try again."]]);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
            break;

        case 'login':
            $input_username = $_POST['username'] ?? '';
            $input_password = $_POST['password'] ?? '';
            
            if (empty($input_username) && empty($input_password)) {
                echo json_encode(['success' => false, 'error' => 'Please enter both of your username and password']);
                exit;
            }
            if (empty($input_username)) {
                echo json_encode(['success' => false, 'error' => 'Please enter your username']);
                exit;
            }
            if (empty($input_password)) {
                echo json_encode(['success' => false, 'error' => 'Please enter your password']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT id, username, password, is_admin FROM users WHERE username = ?");
                $stmt->execute([$input_username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    echo json_encode(['success' => false, 'error' => 'Username not found']);
                    exit;
                }
                
                if (password_verify($input_password, $user['password'])) {
                    session_start();
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    
                    // Check if user is admin and set appropriate session variables and redirect
                    if (isset($user['is_admin']) && $user['is_admin'] == 1) {
                        $_SESSION['is_admin'] = true;
                        echo json_encode(['success' => true, 'redirect' => 'admin.php']);
                    } else {
                        $_SESSION['is_admin'] = false;
                        echo json_encode(['success' => true, 'redirect' => 'homepage.php']);
                    }
                    exit;
                } else {
                    echo json_encode(['success' => false, 'error' => 'Incorrect password']);
                    exit;
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Database error']);
                exit;
            }
            break;

        case 'forgot_password':
            $input_username = $_POST['username'] ?? '';
            $recovery_code = $_POST['recovery_code'] ?? '';
            
            if (empty($input_username)) {
                echo json_encode(['success' => false, 'error' => 'Please enter your username']);
                exit;
            }
            
            if (empty($recovery_code)) {
                echo json_encode(['success' => false, 'error' => 'Please enter your recovery code']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT id, recovery_code FROM users WHERE username = ?");
                $stmt->execute([$input_username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    echo json_encode(['success' => false, 'error' => 'Username not found']);
                    exit;
                }
                
                if ($user['recovery_code'] !== $recovery_code) {
                    echo json_encode(['success' => false, 'error' => 'Invalid recovery code']);
                    exit;
                }
                
                echo json_encode([
                    'success' => true, 
                    'user_id' => $user['id'],
                    'message' => 'Verification successful'
                ]);
                exit;
                
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Database error']);
                exit;
            }
            break;

        case 'reset_password':
            $user_id = $_POST['user_id'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($user_id) || empty($new_password) || empty($confirm_password)) {
                echo json_encode(['success' => false, 'error' => 'All fields are required']);
                exit;
            }
            
            if ($new_password !== $confirm_password) {
                echo json_encode(['success' => false, 'error' => 'Passwords do not match']);
                exit;
            }
            
            if (strlen($new_password) < 8) {
                echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters long']);
                exit;
            }
            
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
                exit;
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Database error']);
                exit;
            }
            break;

        case 'check_username':
            $username = $_POST['username'] ?? '';
            if (empty($username)) {
                echo json_encode(['exists' => false]);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                echo json_encode(['exists' => $stmt->rowCount() > 0]);
                exit;
            } catch (PDOException $e) {
                echo json_encode(['error' => $e->getMessage()]);
                exit;
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$loggedIn = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BondNest - Log In / Sign Up</title>
    <link rel="stylesheet" href="login-signup.css"> <link rel="stylesheet" href="login.css"> <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
</head>

<body>
  <div class="bondnest-title-container">
    <img src="./web-images/bn-logo.png" alt="BondNest Icon" class="bondnest-icon-top">
    <h1 class="bondnest-title-top">
        <span style="--random-angle: 3;">B</span>
        <span style="--random-angle: -2;">o</span>
        <span style="--random-angle: 5;">n</span>
        <span style="--random-angle: -1;">d</span>
        <span style="--random-angle: 4;">N</span>
        <span style="--random-angle: -3;">e</span>
        <span style="--random-angle: 2;">s</span>
        <span style="--random-angle: -4;">t</span>
    </h1>
</div>

<div class="flying-bird">
    <img src="./web-images/bird.gif" alt="Flying Bird">
</div>

<div class="flying-bird-secondary">
    <img src="./web-images/bird.gif" alt="Flying Bird Secondary">
</div>

<div class="login-wrapper">
    <div class="form-container" id="loginContainer">
        <h2 class="login-title">Welcome Back</h2>
        <p class="login-subtitle">Please enter your account</p>

        <form class="login-form" id="loginForm">
            <div class="form-group">
                <label for="email">Your username</label> <!-- Changed from email to username -->
                <div class="login-form-group">
                    <div class="login-icon-container">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="login-input-wrapper">
                        <input type="text" id="email" name="username" placeholder="Your username" autocomplete="off">
                    </div>
                </div>
            </div>
        
            <div class="form-group">
                <label for="password">Your password</label>
                <div class="login-form-group">
                    <div class="login-icon-container">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="login-input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Your password" autocomplete="current-password">
                    </div>
                </div>
            </div>
        
            <button type="submit" class="login-button" id="loginSubmit">Sign In</button>
        </form>
        <div class="login-links">
            <a href="#">Forgot password?</a>
        </div>

        <p id="errorMessage" class="error-message" style="display:none;">Invalid email or password.</p>
    </div>

    <div class="right-section">
        <h2>Your Network Awaits!</h2>
        <p>Join BondNest and start connecting.</p>
        <div class="signup-button-section">
            <button class="signup-button" id="showSignup">Sign Up</button>
        </div>
    </div>
</div>
<div class="neggy-container1">
    <div class="header">
        <div class="neg-icon">N</div>
        <h4>Neggy Says...</h4>
    </div>
    <div id="neggy-messages1"></div>
</div>

<div class="forgot-password-container" id="forgotPasswordContainer">
    <div class="forgot-password-wrapper">
        <div class="forgot-password-header">
            <h2 class="forgot-password-title">Reset Password</h2>
            <p class="forgot-password-subtitle">Enter your username and recovery code to reset your password</p>
        </div>

        <form class="forgot-password-form" id="forgotPasswordForm">
            <div class="form-row">
                <div class="form-group">
                    <div class="input-container">
                        <div class="icon-container">
                            <i class="fas fa-user"></i>
                        </div>
                        <input type="text" id="forgotUsername" name="username" placeholder="Your username" autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <div class="input-container">
                        <div class="icon-container">
                            <i class="fas fa-key"></i>
                        </div>
                        <input type="text" id="recoveryCode" name="recovery_code" placeholder="Your recovery code" autocomplete="off">
                    </div>
                </div>
            </div>

            <div id="newPasswordFields" style="display: none;">
                <div class="form-row">
                    <div class="form-group">
                        <div class="input-container">
                            <div class="icon-container">
                                <i class="fas fa-lock"></i>
                            </div>
                            <input type="password" id="newPassword" name="new_password" placeholder="New password" autocomplete="off">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <div class="input-container">
                            <div class="icon-container">
                                <i class="fas fa-lock"></i>
                            </div>
                            <input type="password" id="confirmNewPassword" name="confirm_password" placeholder="Confirm new password" autocomplete="off">
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="create-account-button" id="submitForgot">
                Continue
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="form-footer">
            <div class="back-to-login">
                <a href="#" id="hideForgotPassword" class="link">Back to Log In</a>
            </div>
        </div>
    </div>
</div>

<div class="signup-container" id="signupContainer">
    <div class="signup-form-wrapper">
        <div class="signup-header">
            <h2 class="create-account-title">Join BondNest</h2>
            <p class="create-account-subtitle">Create your account to start connecting</p>
        </div>

        <form class="create-account-form" id="createAccountForm" novalidate>
            <div class="form-row">
                <div class="form-group">
                    <div class="input-container">
                        <div class="icon-container"><i class="fas fa-at"></i></div>
                        <input type="text" id="username" name="username" class="form-input" placeholder="Username" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" required>
                    </div>
                    <div class="custom-error" id="username-error">Username is required.</div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <div class="icon-container"><i class="fas fa-envelope"></i></div>
                        <input type="email" id="signupEmail" name="email" class="form-input" placeholder="Email" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" required>
                    </div>
                    <div class="custom-error" id="signupEmail-error">Email is required.</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <div class="input-container">
                        <div class="icon-container"><i class="fas fa-user"></i></div>
                        <input type="text" id="firstName" name="firstName" class="form-input" placeholder="First Name" autocomplete="off" autocapitalize="words" autocorrect="off" spellcheck="false" required>
                    </div>
                    <div class="custom-error" id="firstName-error">First name is required.</div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <div class="icon-container"><i class="fas fa-user"></i></div>
                        <input type="text" id="lastName" name="lastName" class="form-input" placeholder="Last Name" autocomplete="off" autocapitalize="words" autocorrect="off" spellcheck="false" required>
                    </div>
                    <div class="custom-error" id="lastName-error">Last name is required.</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <div class="input-container" id="birthdayWrapper">
                        <div class="icon-container" id="birthdayIcon" style="cursor:pointer;"><i class="fas fa-calendar-alt"></i></div>
                        <input type="date" id="birthday" name="birthday" class="form-input form-date-input" placeholder="Birthday" autocomplete="off" required>
                    </div>
                    <div class="custom-error" id="birthday-error">Date of birth is required.</div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <div class="icon-container"><i class="fas fa-venus-mars"></i></div>
                        <select id="gender" name="gender" class="form-input form-select-input" required>
                            <option value="" disabled selected>Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Prefer not to say">Prefer not to say</option>
                        </select>
                    </div>
                    <div class="custom-error" id="gender-error">Gender is required.</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <div class="input-container" style="position:relative;">
                        <div class="icon-container"><i class="fas fa-lock"></i></div>
                        <input type="password" id="createPassword" name="createPassword" class="form-input" placeholder="Password" autocomplete="new-password" required maxlength="64">
                        <i class="fas fa-eye-slash input-icon toggle-password" id="toggleCreatePassword" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#9AA9A1;"></i>
                    </div>
                    <div class="custom-error" id="createPassword-error">Password is required.</div>
                </div>

                <div class="form-group">
                    <div class="input-container" style="position:relative;">
                        <div class="icon-container"><i class="fas fa-lock"></i></div>
                        <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" placeholder="Confirm Password" autocomplete="new-password" required maxlength="64">
                        <i class="fas fa-eye-slash input-icon toggle-password" id="toggleConfirmPassword" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#9AA9A1;"></i>
                    </div>
                    <div class="custom-error" id="confirmPassword-error">Password confirmation is required.</div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group form-group--pwd-full">
                    <div class="pwd-live" id="signUpPwLive" hidden></div>
                    <div class="custom-error" id="signUpPassword-common-error"></div>
                </div>
            </div>

            <button type="submit" class="create-account-button" id="signUpSubmitBtn" disabled>Sign Up</button>

            <div class="form-footer">
                <p class="terms-policy">
                    By joining, you agree to our 
                    <a href="#" class="link">Terms</a> and 
                    <a href="#" class="link">Privacy Policy</a>
                </p>
                <div class="back-to-login">
                    Already have an account? <a href="#" id="hideSignup" class="link">Back to Log In</a>
                </div>
            </div>
        </form>
        <script>
        // DiariCore white-panel floating labels + eye toggle (scoped to signup)
        (function(){
            const inputs = document.querySelectorAll('#createAccountForm .form-input');
            inputs.forEach(input => {
                const wrapper = input.closest('.input-wrapper');
                if (!wrapper || wrapper.classList.contains('input-wrapper--static-label')) return;
                const upd = () => wrapper.classList.toggle('has-content', input.value.trim() !== '' || (input.tagName==='SELECT' && input.value!==''));
                input.addEventListener('input', upd);
                input.addEventListener('change', upd);
                input.addEventListener('blur', upd);
                upd();
            });
            document.querySelectorAll('#createAccountForm .toggle-password').forEach(icon=>{
                icon.addEventListener('click', ()=>{
                    const w = icon.closest('.input-wrapper, .input-container');
                    const inp = w ? w.querySelector('.form-input') : document.getElementById(icon.id==='toggleCreatePassword'?'createPassword':'confirmPassword');
                    if (!inp) return;
                    const isPwd = inp.type==='password';
                    inp.type = isPwd ? 'text' : 'password';
                    icon.classList.toggle('fa-eye-slash');
                    icon.classList.toggle('fa-eye');
                });
            });
            // Birthday calendar trigger (left icon)
            const bInput = document.getElementById('birthday');
            const bIcon = document.getElementById('birthdayIcon');
            const bWrapper = document.getElementById('birthdayWrapper');
            function openBirthdayPicker(e){
                if(e) e.preventDefault();
                if(!bInput) return;
                try{
                    if(typeof bInput.showPicker==='function') bInput.showPicker();
                    else bInput.focus();
                    bInput.click();
                }catch(_){ bInput.focus(); }
            }
            if(bIcon) bIcon.addEventListener('click', openBirthdayPicker);
            if(bWrapper) bWrapper.addEventListener('click', (e)=>{
                if(e.target.closest('#birthdayIcon') || e.target.closest('.icon-container')) openBirthdayPicker(e);
            });
        })();
        </script>
        <script>
        // ——— DiariCore password policy (BondNest adapted) ———
        (function(global){
            var COMMON_PASSWORDS=['password','12345678','123456789','qwerty','qwerty123','111111','iloveyou','admin','welcome','monkey','dragon','letmein','abc123','password1'];
            var COMMON_SET={}; for(var i=0;i<COMMON_PASSWORDS.length;i++) COMMON_SET[COMMON_PASSWORDS[i].toLowerCase()]=true;
            var SPECIAL_CHARS="!@#$%^&*()_+-=[]{}|;:'\",.<>?/`~\\";
            function hasSpecialChar(p){ for(var i=0;i<SPECIAL_CHARS.length;i++) if(p.indexOf(SPECIAL_CHARS.charAt(i))!==-1) return true; return false; }
            var MIN_LEN=12, MAX_LEN=64;
            function norm(s){ return String(s||'').trim(); }
            function containsPersonal(pl, token){ var t=norm(token).toLowerCase(); if(t.length<2) return false; return pl.indexOf(t)!==-1; }
            function isCommonPassword(password){ var l=String(password||'').trim().toLowerCase(); return !!COMMON_SET[l]; }
            function getChecklistState(password, personal){
                var p=password!=null?String(password):''; var pl=p.toLowerCase(); var per=personal||{};
                return { len12:p.length>=MIN_LEN, upper:/[A-Z]/.test(p), lower:/[a-z]/.test(p), digit:/[0-9]/.test(p), special:hasSpecialChar(p), noSpace:p.indexOf(' ') === -1, noPersonal:!(containsPersonal(pl,per.nickname)||containsPersonal(pl,per.email)||containsPersonal(pl,per.firstName)||containsPersonal(pl,per.lastName)) };
            }
            function countChecklistPassed(state){ var n=0; if(state.len12)n++; if(state.upper)n++; if(state.lower)n++; if(state.digit)n++; if(state.special)n++; if(state.noSpace)n++; if(state.noPersonal)n++; return n; }
            function passwordsMatch(p,c){ return String(c||'')===String(p||'') && String(c||'').length>0; }
            function getStrengthScoreMeterOnly(p,c){
                var pp=p!=null?String(p):''; var state={ len12:pp.length>=MIN_LEN, upper:/[A-Z]/.test(pp), lower:/[a-z]/.test(pp), digit:/[0-9]/.test(pp), special:hasSpecialChar(pp), noSpace:pp.indexOf(' ') === -1 };
                var cc=0; if(state.len12)cc++; if(state.upper)cc++; if(state.lower)cc++; if(state.digit)cc++; if(state.special)cc++; if(state.noSpace)cc++; if(passwordsMatch(pp,c))cc+=1; return cc;
            }
            function getStrengthBandMeter(score){ if(score<=2) return {key:'weak',label:'Weak',color:'#c75c5c'}; if(score<=4) return {key:'fair',label:'Fair',color:'#d4a017'}; if(score<=5) return {key:'good',label:'Good',color:'#9db85a'}; return {key:'strong',label:'Strong',color:'#4a7c59'}; }
            function getPasswordBlockMessage(password, confirm, personal){
                var p=String(password||''); var c=String(confirm||''); if(!p.trim()) return 'Enter a password to continue.'; if(p.length>MAX_LEN) return 'Password must be '+MAX_LEN+' characters or fewer.'; if(isCommonPassword(p)) return 'This password is too common. Choose a less predictable password.'; var state=getChecklistState(p,personal); if(!state.len12) return 'Password must be at least '+MIN_LEN+' characters.'; if(!state.upper) return 'Password must include at least one uppercase letter (A–Z).'; if(!state.lower) return 'Password must include at least one lowercase letter (a–z).'; if(!state.digit) return 'Password must include at least one number.'; if(!state.special) return 'Password must include at least one special character (!@#$…).'; if(!state.noSpace) return 'Password must not contain spaces.'; if(!state.noPersonal){ var per=personal||{}; var hits=[]; if(containsPersonal(p.toLowerCase(),per.nickname)) hits.push('username'); if(containsPersonal(p.toLowerCase(),per.firstName)) hits.push('first name'); if(containsPersonal(p.toLowerCase(),per.lastName)) hits.push('last name'); if(containsPersonal(p.toLowerCase(),per.email)) hits.push('email'); var hint=hits.length>0?' (matched your '+hits.join(', ')+')':''; return 'Password must not contain your username, first name, last name, or email'+hint+'. Use a different password that does not include those words.'; } if(!passwordsMatch(p,c)) return 'Passwords do not match. Check confirm password.'; return '';
            }
            function isPasswordSubmitReady(p,c,personal){ var pp=String(p||''); if(pp.length>MAX_LEN) return false; var st=getChecklistState(pp,personal); var cnt=countChecklistPassed(st); if(cnt!==7) return false; if(!passwordsMatch(pp,c)) return false; if(isCommonPassword(pp)) return false; return true; }
            global.DiariPasswordPolicy={MIN_LEN:MIN_LEN, MAX_LEN:MAX_LEN, getChecklistState:getChecklistState, getStrengthScoreMeterOnly:getStrengthScoreMeterOnly, getStrengthBandMeter:getStrengthBandMeter, isCommonPassword:isCommonPassword, getPasswordBlockMessage:getPasswordBlockMessage, isPasswordSubmitReady:isPasswordSubmitReady};
        })(window);
        </script>
        <script>
        // Live strength meter for BondNest signup (DiariCore-style)
        (function(){
            const pwdEl=document.getElementById('createPassword');
            const confirmEl=document.getElementById('confirmPassword');
            const liveWrap=document.getElementById('signUpPwLive');
            const commonErr=document.getElementById('signUpPassword-common-error');
            if(!pwdEl||!confirmEl||!liveWrap) return;
            if(!liveWrap.querySelector('.pwd-strength')){
                liveWrap.innerHTML='<div class="pwd-strength"><div class="pwd-strength__track" role="progressbar" aria-valuemin="0" aria-valuemax="7" aria-valuenow="0"><div class="pwd-strength__fill"></div></div><span class="pwd-strength__label">Weak</span></div>';
            }
            const fillEl=liveWrap.querySelector('.pwd-strength__fill');
            const labelEl=liveWrap.querySelector('.pwd-strength__label');
            const trackEl=liveWrap.querySelector('.pwd-strength__track');
            function getPersonal(){ return { nickname: document.getElementById('username')?.value||'', email: document.getElementById('signupEmail')?.value||'', firstName: document.getElementById('firstName')?.value||'', lastName: document.getElementById('lastName')?.value||'' }; }
            function refresh(){
                const p=pwdEl.value; const c=confirmEl.value;
                if(p.length===0){ liveWrap.hidden=true; if(commonErr) commonErr.classList.remove('show'); return; }
                liveWrap.hidden=false;
                const score=window.DiariPasswordPolicy.getStrengthScoreMeterOnly(p,c);
                const band=window.DiariPasswordPolicy.getStrengthBandMeter(score);
                if(fillEl){ fillEl.style.width=Math.min(100,(score/7)*100)+'%'; fillEl.style.backgroundColor=band.color; }
                if(labelEl){ labelEl.textContent=band.label; labelEl.style.color=band.color; }
                if(trackEl){ trackEl.setAttribute('aria-valuenow',String(score)); trackEl.setAttribute('aria-valuetext',band.label); }
                const personal=getPersonal();
                const ready=window.DiariPasswordPolicy.isPasswordSubmitReady(p,c,personal);
                if(commonErr){
                    if(!ready && p.length>0){
                        const msg=window.DiariPasswordPolicy.getPasswordBlockMessage(p,c,personal);
                        if(msg){ commonErr.textContent=msg; commonErr.classList.add('show'); } else commonErr.classList.remove('show');
                    } else commonErr.classList.remove('show');
                }
                if(typeof updateSignupButton==='function') updateSignupButton();
            }
            pwdEl.addEventListener('input', refresh);
            confirmEl.addEventListener('input', refresh);
            ['username','signupEmail','firstName','lastName'].forEach(id=>{ const el=document.getElementById(id); if(el) el.addEventListener('input', refresh); });
            refresh();
            window._bondRefreshPwd = refresh;
        })();
        </script>
    </div>
</div>
    <div class="neggy-container">
        <div class="header">
            <div class="neg-icon">N</div>
            <h4>Neggy Says...</h4>
        </div>
        <div id="neggy-messages"></div>
    </div>
</div>




  

<script>
    const showSignupButton = document.getElementById('showSignup');
    const hideSignupButton = document.getElementById('hideSignup');
    const signupContainer = document.getElementById('signupContainer');
    const loginWrapper = document.querySelector('.login-wrapper');
    const bondnestContainer = document.querySelector('.bondnest-title-container');
    const flyingBird = document.querySelector('.flying-bird');
    const flyingBirdSecondary = document.querySelector('.flying-bird-secondary');
    const body = document.body;
    const signupForm = document.getElementById('createAccountForm');
    const loginForm = document.getElementById('loginForm');
    const loginEmailInput = document.getElementById('email');
    const loginPasswordInput = document.getElementById('password');
    const forgotPasswordButton = document.querySelector('.login-links a');
    const neggyContainer = document.querySelector('.neggy-container');
    const neggyMessages = document.getElementById('neggy-messages');
    const neggyContainer1 = document.querySelector('.neggy-container1');
    const neggyMessages1 = document.getElementById('neggy-messages1');
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const newPasswordFields = document.getElementById('newPasswordFields');
    const submitForgot = document.getElementById('submitForgot');

    // Timer variables
    let registrationTimer;
    let countdownInterval;
    let validationTimer;
    let currentValidationTimer;


    // Initialize Neggy containers as hidden
    if (neggyContainer) {
        neggyContainer.style.display = 'none';
    }
    if (neggyContainer1) {
        neggyContainer1.style.display = 'none';
    }

    function clearAllTimers() {
        if (registrationTimer) {
            clearTimeout(registrationTimer);
            registrationTimer = null;
        }
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
        if (validationTimer) {
            clearTimeout(validationTimer);
            validationTimer = null;
        }
        if (currentValidationTimer) {
            clearTimeout(currentValidationTimer);
            currentValidationTimer = null;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        function resetSignupForm() {
            if (signupForm) {
                signupForm.reset();
            }
            if (neggyContainer) {
                neggyContainer.style.display = 'none';
                neggyMessages.innerHTML = '';
            }
            clearAllTimers();
        }

        function resetLoginForm() {
            const loginFormElement = document.getElementById('loginForm');
            if (loginFormElement) {
                const emailInput = loginFormElement.querySelector('#email');
                const passwordInput = loginFormElement.querySelector('#password');
                if (emailInput) {
                    emailInput.value = '';
                }
                if (passwordInput) {
                    passwordInput.value = '';
                }
            }
            if (neggyContainer1) {
                neggyContainer1.style.display = 'none';
                neggyMessages1.innerHTML = '';
                neggyContainer1.classList.remove('login-position', 'forgot-position');
            }
            clearAllTimers();
        }

        function positionNeggyForLogin() {
            if (neggyContainer1) {
                neggyContainer1.classList.add('login-position');
                neggyContainer1.classList.remove('forgot-position');
            }
        }

        function positionNeggyForForgotPassword() {
            if (neggyContainer1) {
                neggyContainer1.classList.add('forgot-position');
                neggyContainer1.classList.remove('login-position');
            }
        }

        function showNeggyMessage(message, isSuccess = false) {
            if (!neggyContainer || !neggyMessages) return;

            // Clear any existing timers first
            clearAllTimers();

            neggyContainer.classList.remove('success', 'error');
            neggyContainer.style.display = 'block';
            neggyMessages.innerHTML = '<p>' + message + '</p>';

            if (isSuccess) {
                neggyContainer.classList.add('success');

                // Add visual countdown
                const countdownElement = document.createElement('div');
                countdownElement.style.marginTop = '10px';
                countdownElement.style.fontWeight = 'bold';
                neggyMessages.appendChild(countdownElement);

                let secondsLeft = 60;
                countdownElement.textContent = `Auto-redirect in ${secondsLeft}s...`;

                countdownInterval = setInterval(() => {
                    secondsLeft--;
                    countdownElement.textContent = `Auto-redirect in ${secondsLeft}s...`;

                    if (secondsLeft <= 0) {
                        clearInterval(countdownInterval);
                        neggyContainer.style.display = 'none';
                        window.location.href = 'index.php';
                    }
                }, 1000);
            } else {
                neggyContainer.classList.add('error');
                // For error messages, keep it visible for 10 seconds
                registrationTimer = setTimeout(() => {
                    neggyContainer.style.display = 'none';
                }, 10000);
            }
        }

        function showNeggyMessage1(message, isSuccess = false, autoRedirect = true) {
            if (!neggyContainer1 || !neggyMessages1) return;

            // Clear any existing timers first
            clearAllTimers();

            neggyContainer1.classList.remove('success', 'error');
            neggyContainer1.style.display = 'block';
            neggyMessages1.innerHTML = '<p>' + message + '</p>';

            if (isSuccess) {
                neggyContainer1.classList.add('success');
                if (autoRedirect) {
                    setTimeout(() => {
                        neggyContainer1.style.display = 'none';
                        if (window.location.pathname.includes('forgot-password')) {
                            window.location.href = 'index.php';
                        } else {
                            window.location.href = 'index.php';
                        }
                    }, 5000);
                }
            } else {
                neggyContainer1.classList.add('error');
                // For error messages, keep it visible for 10 seconds
                registrationTimer = setTimeout(() => {
                    neggyContainer1.style.display = 'none';
                }, 10000);
            }
        }

        // Click handler to cancel timers when navigating
        document.addEventListener('click', function(e) {
            if (e.target.closest('#hideSignup, #hideForgotPassword, .login-links a')) {
                clearAllTimers();
            }
        });

        // ——— DiariCore-style inline validation for signup ———
        const signupIds = ['username','signupEmail','firstName','lastName','gender','birthday','createPassword','confirmPassword'];
        let signupAvailability = { username: { val:'', ok:null, pending:null }, signupEmail: { val:'', ok:null, pending:null } };
        let availabilityTimers = {};

        function isValidEmailBond(email){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email); }

        function showErrorBond(inputEl, msg){
            inputEl.classList.add('error'); inputEl.classList.remove('success');
            const container = inputEl.closest('.input-container');
            if(container) container.classList.add('error');
            const err = document.getElementById(inputEl.id + '-error');
            if(err){ err.textContent = msg; err.classList.add('show'); }
        }
        function showSuccessBond(inputEl){
            inputEl.classList.remove('error'); inputEl.classList.add('success');
            const container = inputEl.closest('.input-container');
            if(container) container.classList.remove('error');
            const err = document.getElementById(inputEl.id + '-error');
            if(err) err.classList.remove('show');
        }
        function clearValidationBond(inputEl){
            inputEl.classList.remove('error','success');
            const container = inputEl.closest('.input-container');
            if(container) container.classList.remove('error');
            const err = document.getElementById(inputEl.id + '-error');
            if(err) err.classList.remove('show');
        }

        function checkAvailabilityBond(fieldId, value){
            const key = fieldId==='username' ? 'username' : 'signupEmail';
            const state = signupAvailability[key];
            if(!state) return Promise.resolve(true);
            if(state.val===value && state.ok!==null){
                const el=document.getElementById(fieldId);
                if(el){ if(state.ok) showSuccessBond(el); else showErrorBond(el, key==='username'?'Username already exists.':'Email already exists.'); }
                return Promise.resolve(state.ok);
            }
            if(state.val===value && state.pending) return state.pending;
            state.val=value; state.ok=null;
            const body = key==='username' ? `check_username=1&username=${encodeURIComponent(value)}` : `check_username=1&username=${encodeURIComponent(value)}`;
            // Reuse check_username for both; for email we check via same endpoint fallback to PHP email check on submit
            state.pending = fetch('index.php',{method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
                .then(r=>r.json().then(d=>({ok:r.ok,d})))
                .then(({ok,d})=>{
                    if(!ok) return true;
                    // Only username check is supported server-side; for email, rely on submit error
                    if(key==='username' && d.exists){
                        state.ok=false;
                        const el=document.getElementById(fieldId);
                        if(el) showErrorBond(el,'Username already exists.');
                        return false;
                    }
                    state.ok=true;
                    const el=document.getElementById(fieldId);
                    if(el && key==='username') showSuccessBond(el);
                    return true;
                }).catch(()=>true)
                .finally(()=>{ if(state.val===value) state.pending=null; });
            return state.pending;
        }

        function validateSignupField(fieldId){
            const el=document.getElementById(fieldId);
            if(!el) return true;
            const v=el.value.trim();
            if(fieldId==='username'){
                if(!v){ showErrorBond(el,'Username is required.'); return false; }
                if(v.length<4 || v.length>64){ showErrorBond(el,'Username must be 4-64 characters.'); return false; }
                if(/[<>\/]/.test(v)){ showErrorBond(el,'Invalid characters.'); return false; }
                const st=signupAvailability.username;
                if(st.val===v && st.ok===false){ showErrorBond(el,'Username already exists.'); return false; }
                if(st.val===v && st.ok===true){ showSuccessBond(el); return true; }
                // schedule availability check
                if(availabilityTimers.username) clearTimeout(availabilityTimers.username);
                availabilityTimers.username=setTimeout(()=>checkAvailabilityBond('username',v),300);
                if(v.length>=4) showSuccessBond(el);
                return true;
            }
            if(fieldId==='signupEmail'){
                if(!v){ showErrorBond(el,'Email is required.'); return false; }
                if(!isValidEmailBond(v)){ showErrorBond(el,'Please enter a valid email.'); return false; }
                showSuccessBond(el); return true;
            }
            if(fieldId==='firstName'){ if(!v){ showErrorBond(el,'First name is required.'); return false; } if(/[<>\/]/.test(v)){ showErrorBond(el,'Invalid characters.'); return false; } showSuccessBond(el); return true; }
            if(fieldId==='lastName'){ if(!v){ showErrorBond(el,'Last name is required.'); return false; } if(/[<>\/]/.test(v)){ showErrorBond(el,'Invalid characters.'); return false; } showSuccessBond(el); return true; }
            if(fieldId==='gender'){ if(!v){ showErrorBond(el,'Gender is required.'); return false; } showSuccessBond(el); return true; }
            if(fieldId==='birthday'){ if(!v){ showErrorBond(el,'Date of birth is required.'); return false; } showSuccessBond(el); return true; }
            if(fieldId==='createPassword'){
                if(!v){ showErrorBond(el,'Password is required.'); if(window._bondRefreshPwd) window._bondRefreshPwd(); return false; }
                // Delegate detailed policy to live meter (common-error); per-field just marks success if not empty
                showSuccessBond(el);
                if(window._bondRefreshPwd) window._bondRefreshPwd();
                const c=document.getElementById('confirmPassword');
                if(c && c.value.trim()) validateSignupField('confirmPassword');
                return true;
            }
            if(fieldId==='confirmPassword'){
                const p=document.getElementById('createPassword')?.value||'';
                if(!v){ showErrorBond(el,'Password confirmation is required.'); return false; }
                if(v!==p){ showErrorBond(el,'Passwords do not match.'); return false; }
                showSuccessBond(el); return true;
            }
            return true;
        }

        // Attach listeners + button enable/disable
        const signupSubmitBtn = document.getElementById('signUpSubmitBtn');
        function updateSignupButton(){
            const otherIds = ['username','signupEmail','firstName','lastName','gender','birthday'];
            const otherValid = otherIds.every(id=>{
                const el=document.getElementById(id);
                return el && el.value.trim()!=='' && !el.classList.contains('error');
            });
            const pw=document.getElementById('createPassword')?.value||'';
            const cpw=document.getElementById('confirmPassword')?.value||'';
            const personal={ nickname: document.getElementById('username')?.value||'', email: document.getElementById('signupEmail')?.value||'', firstName: document.getElementById('firstName')?.value||'', lastName: document.getElementById('lastName')?.value||'' };
            const pwReady = window.DiariPasswordPolicy ? window.DiariPasswordPolicy.isPasswordSubmitReady(pw, cpw, personal) : (pw.length>=12 && pw===cpw);
            const noInlineErrors = !document.querySelector('#createAccountForm .custom-error.show');
            if(signupSubmitBtn) signupSubmitBtn.disabled = !(otherValid && pwReady && noInlineErrors);
        }

        signupIds.forEach(fid=>{
            const el=document.getElementById(fid);
            if(!el) return;
            el.addEventListener('blur', ()=>{ validateSignupField(fid); updateSignupButton(); });
            el.addEventListener('input', ()=>{ validateSignupField(fid); updateSignupButton(); });
            el.addEventListener('change', ()=>{ validateSignupField(fid); updateSignupButton(); });
        });

        // Birthday calendar icon click -> show picker
        const birthdayInput = document.getElementById('birthday');
        const birthdayIcon = document.getElementById('birthdayIcon');
        if(birthdayIcon && birthdayInput){
            const openPicker = (e)=>{
                e.preventDefault();
                try{ if(typeof birthdayInput.showPicker==='function') birthdayInput.showPicker(); else birthdayInput.focus(); if(!birthdayInput._pickerFallback) birthdayInput.click(); }catch(_){ birthdayInput.focus(); }
            };
            birthdayIcon.addEventListener('click', openPicker);
            birthdayIcon.style.cursor='pointer';
            // Also clicking wrapper
            birthdayInput.closest('.input-wrapper')?.addEventListener('click', (e)=>{
                if(e.target===birthdayInput) return;
                if(e.target.closest('.input-icon')) return;
                // don't steal focus from other inputs
            });
        }

        // Forgot password validation
        if (forgotPasswordForm) {
            const forgotUsernameInput = document.getElementById('forgotUsername');
            const recoveryCodeInput = document.getElementById('recoveryCode');
            const newPasswordInput = document.getElementById('newPassword');
            const confirmNewPasswordInput = document.getElementById('confirmNewPassword');

            if (forgotUsernameInput && recoveryCodeInput) {
                forgotUsernameInput.addEventListener('input', validateForgotPasswordFields);
                recoveryCodeInput.addEventListener('input', validateForgotPasswordFields);
            }

            if (newPasswordInput && confirmNewPasswordInput) {
                newPasswordInput.addEventListener('input', validateNewPassword);
                confirmNewPasswordInput.addEventListener('input', validateNewPassword);
            }

            function validateForgotPasswordFields() {
                const username = forgotUsernameInput.value.trim();
                const recoveryCode = recoveryCodeInput.value.trim();

                clearTimeout(currentValidationTimer);

                if (username && recoveryCode) {
                    currentValidationTimer = setTimeout(() => {
                        neggyContainer1.style.display = 'none';
                    }, 500);
                }
            }

            function validateNewPassword() {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmNewPasswordInput.value;

                clearTimeout(currentValidationTimer);

                if (newPassword.length >= 8 && newPassword === confirmPassword) {
                    currentValidationTimer = setTimeout(() => {
                        neggyContainer1.style.display = 'none';
                    }, 500);
                }
            }

            forgotPasswordForm.addEventListener('submit', function(e) {
                e.preventDefault();

                positionNeggyForForgotPassword();

                const username = document.getElementById('forgotUsername').value.trim();
                const recoveryCode = document.getElementById('recoveryCode').value.trim();

                if (!newPasswordFields.style.display || newPasswordFields.style.display === 'none') {
                    // First step: verify username and recovery code
                    if (!username && !recoveryCode) {
                        showNeggyMessage1('Please enter both your username and recovery code');
                        return;
                    }
                    if (!username) {
                        showNeggyMessage1('Please enter your username');
                        return;
                    }
                    if (!recoveryCode) {
                        showNeggyMessage1('Please enter your recovery code');
                        return;
                    }

                    // Show loading state
                    const originalText = submitForgot.textContent;
                    submitForgot.disabled = true;

                    fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `forgot_password=1&username=${encodeURIComponent(username)}&recovery_code=${encodeURIComponent(recoveryCode)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            newPasswordFields.style.display = 'block';
                            submitForgot.textContent = 'Reset Password';
                            submitForgot.setAttribute('data-user-id', data.user_id);
                            showNeggyMessage1('Verification successful. Please enter your new password.', true, false);
                        } else {
                            showNeggyMessage1(data.error || 'Verification failed. Please check your recovery code and try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNeggyMessage1('An error occurred during verification. Please try again.');
                    })
                    .finally(() => {
                        submitForgot.disabled = false;
                        if (!newPasswordFields.style.display || newPasswordFields.style.display === 'none') {
                            submitForgot.textContent = originalText;
                        }
                    });
                } else {
                    // Second step: reset password
                    const newPassword = document.getElementById('newPassword').value;
                    const confirmNewPassword = document.getElementById('confirmNewPassword').value;
                    const userId = submitForgot.getAttribute('data-user-id');

                    if (!newPassword && !confirmNewPassword) {
                        showNeggyMessage1('Please enter your new password and confirm your new password');
                        return;
                    }
                    if (!newPassword) {
                        showNeggyMessage1('Please enter your new password');
                        return;
                    }
                    if (!confirmNewPassword) {
                        showNeggyMessage1('Please confirm your new password');
                        return;
                    }
                    if (newPassword.length < 8) {
                        showNeggyMessage1('Password must be at least 8 characters long');
                        return;
                    }
                    if (newPassword !== confirmNewPassword) {
                        showNeggyMessage1('Passwords do not match');
                        return;
                    }

                    // Show loading state
                    const originalText = submitForgot.textContent;
                    submitForgot.disabled = true;
                    

                    fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `reset_password=1&user_id=${encodeURIComponent(userId)}&new_password=${encodeURIComponent(newPassword)}&confirm_password=${encodeURIComponent(confirmNewPassword)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Create a countdown element
                            const countdownElement = document.createElement('div');
                            countdownElement.style.marginTop = '10px';
                            countdownElement.style.fontWeight = 'bold';

                            // Show the success message with initial countdown
                            const message = 'Password reset successfully! You will be redirected to login in 5 seconds.';
                            showNeggyMessage1(message, true);

                            // Add the countdown element to the Neggy container
                            if (neggyMessages1) {
                                neggyMessages1.appendChild(countdownElement);
                            }

                            let secondsLeft = 5;
                            countdownElement.textContent = `Redirecting in ${secondsLeft}s...`;

                            // Start the countdown
                            const countdownInterval = setInterval(() => {
                                secondsLeft--;
                                countdownElement.textContent = `Redirecting in ${secondsLeft}s...`;

                                if (secondsLeft <= 0) {
                                    clearInterval(countdownInterval);
                                    forgotPasswordContainer.style.display = 'none';
                                    loginWrapper.style.display = 'flex';
                                    resetForgotPasswordForm();
                                    positionNeggyForLogin();
                                    neggyContainer1.style.display = 'none';
                                }
                            }, 1000);
                        } else {
                            showNeggyMessage1(data.error || 'Password reset failed. Please try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNeggyMessage1('An error occurred during password reset. Please try again.');
                    })
                    .finally(() => {
                        submitForgot.disabled = false;
                        submitForgot.textContent = originalText;
                    });
                }
            });
        }

        // Signup form submission — DiariCore inline validation (no Neggy)
        if (signupForm) {
            // Hide Neggy for signup entirely
            if (neggyContainer) neggyContainer.style.display = 'none';
            signupForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Validate all fields inline
                let isValid = true;
                signupIds.forEach(id=>{ if(!validateSignupField(id)) isValid=false; });
                // extra check for email format already in validate
                updateSignupButton();
                if(!isValid){
                    const firstErr = document.querySelector('#createAccountForm .custom-error.show');
                    if(firstErr) firstErr.scrollIntoView({behavior:'smooth', block:'nearest'});
                    return;
                }
                // Availability checks (username)
                const uname = document.getElementById('username').value.trim();
                const emailVal = document.getElementById('signupEmail').value.trim();
                // For email, server will validate uniqueness on submit; for username we can pre-check
                const unameOk = await checkAvailabilityBond('username', uname);
                if(!unameOk) return;

                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating...';

                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Show recovery code inline as success (centered)
                        let successEl = document.getElementById('signupSuccessMsg');
                        if(!successEl){
                            successEl = document.createElement('div');
                            successEl.id='signupSuccessMsg';
                            successEl.style.cssText='background:#e6f4ea;border:1px solid #b7d8c2;color:#1e5a3a;padding:14px;border-radius:10px;text-align:center;margin-bottom:14px;font-size:0.95rem;line-height:1.5;';
                            signupForm.prepend(successEl);
                        }
                        successEl.innerHTML = `Registration successful! Your recovery code is: <strong>${data.recovery_code}</strong><br><span style="font-size:0.85em;opacity:0.9;">Save this code securely. Redirecting in 60s...</span>`;
                        successEl.style.display='block';
                        signupForm.reset();
                        // clear floating labels
                        document.querySelectorAll('#createAccountForm .input-wrapper').forEach(w=>w.classList.remove('has-content'));
                        document.querySelectorAll('#createAccountForm .form-input').forEach(i=>i.classList.remove('error','success'));
                        // countdown redirect
                        let sec=60;
                        const orig = successEl.innerHTML;
                        const iv=setInterval(()=>{
                            sec--; 
                            successEl.innerHTML = orig + `<br><span style="font-weight:700;">Auto-redirect in ${sec}s...</span>`;
                            if(sec<=0){ clearInterval(iv); window.location.href='index.php'; }
                        },1000);
                        // allow manual close by clicking
                        successEl.style.cursor='pointer'; successEl.title='Click to go to login';
                        successEl.onclick=()=>{ clearInterval(iv); window.location.href='index.php'; };
                    } else {
                        // Map server errors to inline fields
                        if (data.errors && data.errors.length > 0) {
                            let mapped=false;
                            data.errors.forEach(msg=>{
                                const low=msg.toLowerCase();
                                if(low.includes('username')) showErrorBond(document.getElementById('username'), msg);
                                else if(low.includes('email')) showErrorBond(document.getElementById('signupEmail'), msg);
                                else if(low.includes('first name')) showErrorBond(document.getElementById('firstName'), msg);
                                else if(low.includes('last name')) showErrorBond(document.getElementById('lastName'), msg);
                                else if(low.includes('birthday')) showErrorBond(document.getElementById('birthday'), msg);
                                else if(low.includes('gender')) showErrorBond(document.getElementById('gender'), msg);
                                else if(low.includes('password') && low.includes('match')) showErrorBond(document.getElementById('confirmPassword'), msg);
                                else if(low.includes('password')) showErrorBond(document.getElementById('createPassword'), msg);
                                else {
                                    // fallback: show under confirm
                                    const fallback = document.getElementById('confirmPassword');
                                    if(fallback) showErrorBond(fallback, msg);
                                }
                                mapped=true;
                            });
                            if(!mapped){
                                // generic
                                showErrorBond(document.getElementById('confirmPassword'), data.errors[0]);
                            }
                            const firstErr = document.querySelector('#createAccountForm .custom-error.show');
                            if(firstErr) firstErr.scrollIntoView({behavior:'smooth', block:'nearest'});
                        } else {
                            showErrorBond(document.getElementById('confirmPassword'), 'Registration failed. Please try again.');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorBond(document.getElementById('confirmPassword'), 'An error occurred. Please try again.');
                })
                .finally(() => {
                    submitBtn.textContent = originalBtnText;
                    submitBtn.disabled = false;
                });
            });
        }

        // Login form submission - MODIFIED SECTION
        if (loginForm) {
            const loginUsernameInput = document.getElementById('email');
            const loginPasswordInput = document.getElementById('password');

            // Add real-time validation for login fields
            loginUsernameInput.addEventListener('input', checkLoginCriteria);
            loginPasswordInput.addEventListener('input', checkLoginCriteria);

            function checkLoginCriteria() {
                const username = loginUsernameInput.value.trim();
                const password = loginPasswordInput.value;

                // Clear any existing timer
                clearTimeout(currentValidationTimer);

                // If criteria are met, hide the message after a short delay
                if (username.length > 0 && password.length >= 8) {
                    currentValidationTimer = setTimeout(() => {
                        neggyContainer1.style.display = 'none';
                    }, 300); // Hide after 300ms of meeting criteria
                }
            }

            // Keep your existing submit handler exactly as is
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();

                if (neggyContainer1) {
                    neggyContainer1.style.display = 'none';
                    neggyMessages1.innerHTML = '';
                }

                positionNeggyForLogin();

                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.textContent;
                submitBtn.disabled = true;
                

                fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `username=${encodeURIComponent(loginUsernameInput.value)}&password=${encodeURIComponent(loginPasswordInput.value)}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showNeggyMessage1('Login successful! Redirecting...', true);
                        setTimeout(() => {
                            window.location.href = data.redirect || 'homepage.php';
                        }, 1500);
                    } else {
                        showNeggyMessage1(data.error || 'Login failed. Please check your credentials and try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNeggyMessage1('An error occurred. Please try again.');
                })
                .finally(() => {
                    submitBtn.textContent = originalBtnText;
                    submitBtn.disabled = false;
                });
            });
        }

        // Forgot password functionality
        if (forgotPasswordButton) {
            const forgotPasswordContainer = document.getElementById('forgotPasswordContainer');
            const hideForgotPassword = document.getElementById('hideForgotPassword');

            forgotPasswordButton.addEventListener('click', function(e) {
                e.preventDefault();
                loginWrapper.style.display = 'none';
                forgotPasswordContainer.style.display = 'flex';
                resetForgotPasswordForm();
                positionNeggyForForgotPassword();
                neggyContainer1.style.display = 'none';
            });

            hideForgotPassword.addEventListener('click', function(e) {
                e.preventDefault();
                forgotPasswordContainer.style.display = 'none';
                loginWrapper.style.display = 'flex';
                resetLoginForm();
                positionNeggyForLogin();
                neggyContainer1.style.display = 'none';
            });

            function resetForgotPasswordForm() {
                forgotPasswordForm.reset();
                newPasswordFields.style.display = 'none';
                submitForgot.textContent = 'Continue';
            }

            forgotPasswordForm.addEventListener('submit', function(e) {
                e.preventDefault();

                positionNeggyForForgotPassword();

                const username = document.getElementById('forgotUsername').value.trim();
                const recoveryCode = document.getElementById('recoveryCode').value.trim();

                if (!newPasswordFields.style.display || newPasswordFields.style.display === 'none') {
                    // First step: verify username and recovery code
                    if (!username && !recoveryCode) {
                        showNeggyMessage1('Please enter both your username and recovery code');
                        return;
                    }
                    if (!username) {
                        showNeggyMessage1('Please enter your username');
                        return;
                    }
                    if (!recoveryCode) {
                        showNeggyMessage1('Please enter your recovery code');
                        return;
                    }

                    // Show loading state
                    const originalText = submitForgot.textContent;
                    submitForgot.disabled = true;
                    

                    fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `forgot_password=1&username=${encodeURIComponent(username)}&recovery_code=${encodeURIComponent(recoveryCode)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            newPasswordFields.style.display = 'block';
                            submitForgot.textContent = 'Reset Password';
                            submitForgot.setAttribute('data-user-id', data.user_id);
                            showNeggyMessage1('Verification successful. Please enter your new password.', true, false);
                        } else {
                            showNeggyMessage1(data.error || 'Verification failed. Please check your recovery code and try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNeggyMessage1('An error occurred during verification. Please try again.');
                    })
                    .finally(() => {
                        submitForgot.disabled = false;
                        if (!newPasswordFields.style.display || newPasswordFields.style.display === 'none') {
                            submitForgot.textContent = originalText;
                        }
                    });
                } else {
                    // Second step: reset password
                    const newPassword = document.getElementById('newPassword').value;
                    const confirmNewPassword = document.getElementById('confirmNewPassword').value;
                    const userId = submitForgot.getAttribute('data-user-id');

                    if (!newPassword && !confirmNewPassword) {
                        showNeggyMessage1('Please enter your new password and confirm your new password');
                        return;
                    }
                    if (!newPassword) {
                        showNeggyMessage1('Please enter your new password');
                        return;
                    }
                    if (!confirmNewPassword) {
                        showNeggyMessage1('Please confirm your new password');
                        return;
                    }
                    if (newPassword.length < 8) {
                        showNeggyMessage1('Password must be at least 8 characters long');
                        return;
                    }
                    if (newPassword !== confirmNewPassword) {
                        showNeggyMessage1('Passwords do not match');
                        return;
                    }

                    // Show loading state
                    const originalText = submitForgot.textContent;
                    submitForgot.disabled = true;
                   

                    fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `reset_password=1&user_id=${encodeURIComponent(userId)}&new_password=${encodeURIComponent(newPassword)}&confirm_password=${encodeURIComponent(confirmNewPassword)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Create a countdown element
                            const countdownElement = document.createElement('div');
                            countdownElement.style.marginTop = '10px';
                            countdownElement.style.fontWeight = 'bold';

                            // Show the success message with initial countdown
                            const message = 'Password reset successfully! You will be redirected to login in 5 seconds.';
                            showNeggyMessage1(message, true);

                            // Add the countdown element to the Neggy container
                            if (neggyMessages1) {
                                neggyMessages1.appendChild(countdownElement);
                            }

                            let secondsLeft = 5;
                            countdownElement.textContent = `Redirecting in ${secondsLeft}s...`;

                            // Start the countdown
                            const countdownInterval = setInterval(() => {
                                secondsLeft--;
                                countdownElement.textContent = `Redirecting in ${secondsLeft}s...`;

                                if (secondsLeft <= 0) {
                                    clearInterval(countdownInterval);
                                    forgotPasswordContainer.style.display = 'none';
                                    loginWrapper.style.display = 'flex';
                                    resetForgotPasswordForm();
                                    positionNeggyForLogin();
                                    neggyContainer1.style.display = 'none';
                                }
                            }, 1000);
                        } else {
                            showNeggyMessage1(data.error || 'Password reset failed. Please try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNeggyMessage1('An error occurred during password reset. Please try again.');
                    })
                    .finally(() => {
                        submitForgot.disabled = false;
                        submitForgot.textContent = originalText;
                    });
                }
            });
        }

        if (showSignupButton) {
            showSignupButton.addEventListener('click', function(e) {
                e.preventDefault();
                signupContainer.style.display = 'flex';
                loginWrapper.style.position = 'absolute';
                loginWrapper.style.left = '-9999px';
                loginWrapper.style.visibility = 'hidden';
                if (bondnestContainer) {
                    bondnestContainer.style.display = 'flex';
                }
                if (flyingBird) {
                    flyingBird.style.display = 'block';
                }
                if (flyingBirdSecondary) {
                    flyingBirdSecondary.style.display = 'block';
                }
                resetSignupForm();
                neggyContainer1.style.display = 'none';
            });
        }

        if (hideSignupButton) {
            hideSignupButton.addEventListener('click', function(e) {
                e.preventDefault();
                signupContainer.style.display = 'none';
                loginWrapper.style.position = 'static';
                loginWrapper.style.left = 'auto';
                loginWrapper.style.visibility = 'visible';
                if (bondnestContainer) {
                    bondnestContainer.style.display = 'flex';
                }
                if (flyingBird) {
                    flyingBird.style.display = 'block';
                }
                if (flyingBirdSecondary) {
                    flyingBirdSecondary.style.display = 'block';
                }
                body.style.background = 'linear-gradient(to top left, #4397d3, transparent 50%), linear-gradient(to bottom right, #7cc6c0, transparent 50%)';
                body.style.backgroundSize = '200% 200%';
                body.style.animation = 'flow 8s ease-in-out infinite alternate';
                resetLoginForm();
                neggyContainer.style.display = 'none';
            });
        }
    });

    // Password visibility toggle functionality
    function setupPasswordToggles() {
        // Select all password input containers
        const passwordContainers = document.querySelectorAll('.login-form-group, .input-container');

        passwordContainers.forEach(container => {
            const passwordInput = container.querySelector('input[type="password"]');
            if (!passwordInput) return;

            // Create toggle element
            const toggle = document.createElement('div');
            toggle.className = 'password-toggle';
            toggle.innerHTML = '<i class="fas fa-eye"></i>';

            // Insert after the input
            const inputWrapper = passwordInput.parentElement;
            inputWrapper.classList.add('password-input-wrapper');

            const newContainer = document.createElement('div');
            newContainer.className = 'password-input-container';

            // Wrap input and toggle in new container
            inputWrapper.parentNode.insertBefore(newContainer, inputWrapper);
            newContainer.appendChild(inputWrapper);
            newContainer.appendChild(toggle);

            // Add click event
            toggle.addEventListener('click', function() {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    this.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    passwordInput.type = 'password';
                    this.innerHTML = '<i class="fas fa-eye"></i>';
                }
            });

            // Show/hide based on input
            passwordInput.addEventListener('input', function() {
                if (this.value.length > 0) {
                    toggle.classList.add('visible');
                } else {
                    toggle.classList.remove('visible');
                }
            });

            // Also check on focus/blur for better UX
            passwordInput.addEventListener('focus', function() {
                if (this.value.length > 0) {
                    toggle.classList.add('visible');
                }
            });

            passwordInput.addEventListener('blur', function() {
                if (this.value.length === 0) {
                    toggle.classList.remove('visible');
                }
            });
        });
    }

    // Call the function when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        setupPasswordToggles();
    });
</script>
    
    
</body>
</html>
<?php


require_once 'db_connection.php';

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
    if (isset($_FILES['profilePicture'])) $action = 'signup';
    if (isset($_POST['username']) && isset($_POST['password']) && $action === '') $action = 'login';
    
    switch ($action) {
        case 'signup':
            $errors = [];
            $required = ['firstName', 'lastName', 'username', 'age', 'birthday', 'gender', 'createPassword', 'confirmPassword'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    $errors[] = ucfirst($field) . ' is required.';
                }
            }

            

            if ($_POST['createPassword'] !== $_POST['confirmPassword']) {
                $errors[] = 'Passwords do not match.';
            }

            if (strlen($_POST['createPassword']) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
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
                        $profile_picture = $target;
                    } else {
                        $errors[] = 'Failed to upload image.';
                    }
                } else {
                    $errors[] = 'Invalid file type. Only images allowed.';
                }
            }

            if (empty($errors)) {
                $recovery_code = generateRecoveryCode();
                $hashed_pw = password_hash($_POST['createPassword'], PASSWORD_DEFAULT);
                
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("INSERT INTO users 
                        (first_name, last_name, username, age, birthday, gender, password, profile_picture, recovery_code) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
                        htmlspecialchars($_POST['firstName']),
                        htmlspecialchars($_POST['lastName']),
                        htmlspecialchars($_POST['username']),
                        intval($_POST['age']),
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

        <form class="create-account-form" id="createAccountForm">
            <div class="form-row">
                <div class="form-group">
                    <div class="input-container">
                        <div class="icon-container">
                            <i class="fas fa-user"></i>
                        </div>
                        <input type="text" id="firstName" name="firstName" placeholder="First Name" autocomplete="off">
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <div class="icon-container">
                            <i class="fas fa-user"></i>
                        </div>
                        <input type="text" id="lastName" name="lastName" placeholder="Last Name" autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <div class="input-container">
                        <div class="icon-container">
                            <i class="fas fa-at"></i>
                        </div>
                        <input type="text" id="username" name="username" placeholder="Username" autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <div class="input-container">
                        <div class="icon-container">
                            <i class="fas fa-birthday-cake"></i>
                        </div>
                        <input type="number" id="age" name="age" placeholder="Age" autocomplete="off">
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <div class="icon-container">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <input type="date" id="birthday" name="birthday" placeholder="Birthday">
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <div class="icon-container">
                            <i class="fas fa-venus-mars"></i>
                        </div>
                        <select id="gender" name="gender">
                            <option value="" disabled>Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Non-binary">Non-binary</option>
                            <option value="Other">Other</option>
                            <option value="Prefer not to say">Prefer not to say</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <div class="input-container">
                        <div class="icon-container">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" id="createPassword" name="createPassword" placeholder="Create Password" autocomplete="off">
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-container">
                        <div class="icon-container">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password" autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="profilePicture">
                    <i class="fas fa-camera"></i>
                    <span>Upload Profile Picture</span>
                    <input type="file" id="profilePicture" name="profilePicture" accept="image/*">
                </label>
            </div>

            <button type="submit" class="create-account-button">
                <span class="button-text">Create Account</span>
                <i class="fas fa-arrow-right"></i>
            </button>

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

        // Real-time username availability check
        const usernameInput = document.getElementById('username');
        if (usernameInput) {
            usernameInput.addEventListener('input', function() {
                const username = this.value.trim();

                // Clear any previous timer
                clearTimeout(validationTimer);

                if (username.length === 0) {
                    neggyContainer.style.display = 'none';
                    return;
                }

                // Only check after user stops typing for 500ms
                validationTimer = setTimeout(() => {
                    if (username.length > 0) {
                        fetch('index.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `check_username=1&username=${encodeURIComponent(username)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.exists) {
                                showNeggyMessage('Username is already taken.');
                            } else {
                                // Hide message if username is available
                                neggyContainer.style.display = 'none';
                            }
                        })
                        .catch(error => {
                            console.error('Error checking username:', error);
                        });
                    }
                }, 500);
            });
        }

        // Password strength and match validation
        const createPasswordInput = document.getElementById('createPassword');
        const confirmNewPasswordInput = document.getElementById('confirmPassword');
        if (createPasswordInput && confirmNewPasswordInput) {
            createPasswordInput.addEventListener('input', validatePassword);
            confirmNewPasswordInput.addEventListener('input', validatePassword);

            function validatePassword() {
                const password = createPasswordInput.value;
                const confirmPassword = confirmNewPasswordInput.value;

                // Clear any previous timer
                clearTimeout(currentValidationTimer);

                // Hide message if both fields are empty
                if (password.length === 0 && confirmPassword.length === 0) {
                    neggyContainer.style.display = 'none';
                    return;
                }

                // Check password length
                if (password.length > 0 && password.length < 8) {
                    showNeggyMessage('Password must be at least 8 characters long.');
                }
                // Check password match
                else if (confirmPassword.length > 0 && password !== confirmPassword) {
                    showNeggyMessage('Passwords do not match.');
                }
                // If criteria are met, hide the message after a short delay
                else if (password.length >= 8 && password === confirmPassword) {
                    currentValidationTimer = setTimeout(() => {
                        neggyContainer.style.display = 'none';
                    }, 500); // Hide after 0.5 seconds of meeting criteria
                }
            }
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

        // Signup form submission
        if (signupForm) {
            signupForm.addEventListener('submit', function(e) {
                e.preventDefault();

                if (neggyContainer) {
                    neggyContainer.style.display = 'none';
                    neggyMessages.innerHTML = '';
                }

                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.textContent;
                submitBtn.disabled = true;
                

                fetch('index.php', { // Changed to your central endpoint
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const successMessage = `Registration successful! Your recovery code is: <strong>${data.recovery_code}</strong><br>Save this code in a secure place. You have 1 minute to note it down.`;
                        showNeggyMessage(successMessage, true);
                        signupForm.reset();
                    } else {
                        if (neggyContainer && neggyMessages) {
                            neggyContainer.style.display = 'block';
                            neggyMessages.innerHTML = '';

                            if (data.errors && data.errors.length > 0) {
                                data.errors.forEach(error => {
                                    const errorElement = document.createElement('p');
                                    errorElement.textContent = error;
                                    neggyMessages.appendChild(errorElement);
                                });
                            } else {
                                neggyMessages.innerHTML = '<p>Registration failed. Please try again.</p>';
                            }

                            neggyContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (neggyContainer && neggyMessages) {
                        neggyContainer.style.display = 'block';
                        neggyMessages.innerHTML = '<p>An error occurred. Please try again.</p>';
                    }
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
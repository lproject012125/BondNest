<?php


require_once 'db_connection.php';
require_once 'migrate.php';
runMigration($pdo);

// Handle all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Check which action is being requested
    $action = '';
    if (isset($_POST['forgot_password'])) $action = 'forgot_password';
    if (isset($_POST['reset_password'])) $action = 'reset_password';
    if (isset($_POST['check_username'])) $action = 'check_username';
    if (isset($_POST['username']) && isset($_POST['password']) && $action === '') $action = 'login';
    
    switch ($action) {
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
                    echo json_encode(['success' => false, 'error' => 'Incorrect username or password.']);
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
                    echo json_encode(['success' => false, 'error' => 'Incorrect username or password.']);
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
                <div class="login-form-group" id="loginPasswordGroup">
                    <div class="login-icon-container">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="login-input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Your password" autocomplete="current-password">
                    </div>
                </div>
                <div class="login-inline-error" id="loginInlineError"></div>
                <a href="#" class="login-forgot-link" id="forgotPasswordTrigger">Forgot password?</a>
            </div>
        
            <button type="submit" class="login-button" id="loginSubmit">Sign In</button>
        </form>
    </div>

    <div class="right-section">
        <h2>Your Network Awaits!</h2>
        <p>Join BondNest and start connecting.</p>
        <div class="signup-button-section">
            <a href="signup.php" class="signup-button" style="text-decoration:none; display:inline-block;">Sign Up</a>
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






  

<script>
    const loginWrapper = document.querySelector('.login-wrapper');
    const bondnestContainer = document.querySelector('.bondnest-title-container');
    const flyingBird = document.querySelector('.flying-bird');
    const flyingBirdSecondary = document.querySelector('.flying-bird-secondary');
    const body = document.body;
    const loginForm = document.getElementById('loginForm');
    const forgotPasswordButton = document.getElementById('forgotPasswordTrigger');
    const loginInlineError = document.getElementById('loginInlineError');
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
            if (e.target.closest('#hideForgotPassword, #forgotPasswordTrigger')) {
                clearAllTimers();
            }
        });

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

        // Inline login error helpers
        function showLoginError(msg) {
            if (!loginInlineError) return;
            loginInlineError.textContent = msg;
            loginInlineError.style.display = 'block';
            const pg = document.getElementById('loginPasswordGroup');
            if (pg) pg.classList.add('login-form-group--error');
        }
        function hideLoginError() {
            if (loginInlineError) {
                loginInlineError.textContent = '';
                loginInlineError.style.display = 'none';
            }
            const pg = document.getElementById('loginPasswordGroup');
            if (pg) pg.classList.remove('login-form-group--error');
        }

        // Login form submission - MODIFIED SECTION
        if (loginForm) {
            const loginUsernameInput = document.getElementById('email');
            const loginPasswordInput = document.getElementById('password');

            loginUsernameInput.addEventListener('input', hideLoginError);
            loginPasswordInput.addEventListener('input', hideLoginError);

            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                hideLoginError();

                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Signing In...';

                fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `username=${encodeURIComponent(loginUsernameInput.value)}&password=${encodeURIComponent(loginPasswordInput.value)}`
                })
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showNeggyMessage1('Login successful! Redirecting...', true);
                        setTimeout(() => {
                            window.location.href = data.redirect || 'homepage.php';
                        }, 1500);
                    } else {
                        showLoginError(data.error || 'Incorrect username or password.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showLoginError('An error occurred. Please try again.');
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
                hideLoginError();
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
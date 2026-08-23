<?php
require_once 'db_connection.php';
require_once 'migrate.php';
runMigration($pdo);

function generateRecoveryCode() {
    return bin2hex(random_bytes(5));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (isset($_POST['check_username'])) {
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
    }

    $errors = [];
    $required = ['firstName', 'lastName', 'username', 'email', 'birthday', 'gender', 'createPassword', 'confirmPassword'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst($field) . ' is required.';
        }
    }

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
        $errors[] = 'Password must include at least one uppercase letter (A-Z).';
    } elseif (!preg_match('/[a-z]/', $_POST['createPassword'])) {
        $errors[] = 'Password must include at least one lowercase letter (a-z).';
    } elseif (!preg_match('/[0-9]/', $_POST['createPassword'])) {
        $errors[] = 'Password must include at least one digit (0-9).';
    } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:\'",.<>?\/`~\\\\]/', $_POST['createPassword'])) {
        $errors[] = 'Password must include at least one special character (!@#$...).';
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

    if (empty($errors) && !empty($_POST['email'])) {
        try {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $check->execute([$_POST['email']]);
            if ($check->fetch()) {
                $errors[] = 'Email is already registered.';
            }
        } catch (PDOException $e) { }
        try {
            $checkU = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $checkU->execute([$_POST['username']]);
            if ($checkU->fetch()) {
                $errors[] = 'Username is already taken.';
            }
        } catch (PDOException $e) { }
    }

    if (empty($errors)) {
        $recovery_code = generateRecoveryCode();
        $hashed_pw = password_hash($_POST['createPassword'], PASSWORD_DEFAULT);
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
                null,
                $recovery_code
            ]);
            $pdo->commit();
            echo json_encode(['success' => true, 'recovery_code' => $recovery_code]);
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BondNest - Sign Up</title>
    <link rel="stylesheet" href="login-signup.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(to top left, #4397d3, transparent 50%),
                linear-gradient(to bottom right, #7cc6c0, transparent 50%);
            background-size: 200% 200%;
            animation: flow 8s ease-in-out infinite alternate;
            overflow: hidden;
        }
        .signup-page-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 90%;
            max-width: 720px;
            animation: fadeInUp 0.5s ease;
        }
        .signup-page-wrapper .signup-form-wrapper {
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="flying-bird">
        <img src="./web-images/bird.gif" alt="Flying Bird">
    </div>

    <div class="flying-bird-secondary">
        <img src="./web-images/bird.gif" alt="Flying Bird Secondary">
    </div>

    <div class="signup-page-wrapper">
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
                        <div class="input-container">
                            <div class="icon-container"><i class="fas fa-lock"></i></div>
                            <input type="password" id="createPassword" name="createPassword" class="form-input" placeholder="Password" autocomplete="new-password" required maxlength="64">
                            <i class="fas fa-eye-slash input-icon toggle-password" id="toggleCreatePassword" style="cursor:pointer; color:#9AA9A1;"></i>
                        </div>
                        <div class="custom-error" id="createPassword-error">Password is required.</div>
                    </div>

                    <div class="form-group">
                        <div class="input-container">
                            <div class="icon-container"><i class="fas fa-lock"></i></div>
                            <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" placeholder="Confirm Password" autocomplete="new-password" required maxlength="64">
                            <i class="fas fa-eye-slash input-icon toggle-password" id="toggleConfirmPassword" style="cursor:pointer; color:#9AA9A1;"></i>
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
                        Already have an account? <a href="index.php" class="link">Back to Log In</a>
                    </div>
                </div>
            </form>
        </div>

    <div class="neggy-container">
        <div class="header">
            <div class="neg-icon">N</div>
            <h4>Neggy Says...</h4>
        </div>
        <div id="neggy-messages"></div>
    </div>

<script>
    const signupForm = document.getElementById('createAccountForm');
    const neggyContainer = document.querySelector('.neggy-container');
    const neggyMessages = document.getElementById('neggy-messages');

    if (neggyContainer) neggyContainer.style.display = 'none';

    function showNeggyMessage(message, isSuccess = false) {
        if (!neggyContainer || !neggyMessages) return;
        neggyContainer.classList.remove('success', 'error');
        neggyContainer.style.display = 'block';
        neggyMessages.innerHTML = '<p>' + message + '</p>';
        if (isSuccess) {
            neggyContainer.classList.add('success');
        } else {
            neggyContainer.classList.add('error');
            setTimeout(() => { neggyContainer.style.display = 'none'; }, 10000);
        }
    }

    // Floating labels + eye toggle + birthday picker
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

    // DiariCore password policy
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
            var p=String(password||''); var c=String(confirm||''); if(!p.trim()) return 'Enter a password to continue.'; if(p.length>MAX_LEN) return 'Password must be '+MAX_LEN+' characters or fewer.'; if(isCommonPassword(p)) return 'This password is too common. Choose a less predictable password.'; var state=getChecklistState(p,personal); if(!state.len12) return 'Password must be at least '+MIN_LEN+' characters.'; if(!state.upper) return 'Password must include at least one uppercase letter (A-Z).'; if(!state.lower) return 'Password must include at least one lowercase letter (a-z).'; if(!state.digit) return 'Password must include at least one number.'; if(!state.special) return 'Password must include at least one special character (!@#$...).'; if(!state.noSpace) return 'Password must not contain spaces.'; if(!state.noPersonal){ var per=personal||{}; var hits=[]; if(containsPersonal(p.toLowerCase(),per.nickname)) hits.push('username'); if(containsPersonal(p.toLowerCase(),per.firstName)) hits.push('first name'); if(containsPersonal(p.toLowerCase(),per.lastName)) hits.push('last name'); if(containsPersonal(p.toLowerCase(),per.email)) hits.push('email'); var hint=hits.length>0?' (matched your '+hits.join(', ')+')':''; return 'Password must not contain your username, first name, last name, or email'+hint+'. Use a different password that does not include those words.'; } if(!passwordsMatch(p,c)) return 'Passwords do not match. Check confirm password.'; return '';
        }
        function isPasswordSubmitReady(p,c,personal){ var pp=String(p||''); if(pp.length>MAX_LEN) return false; var st=getChecklistState(pp,personal); var cnt=countChecklistPassed(st); if(cnt!==7) return false; if(!passwordsMatch(pp,c)) return false; if(isCommonPassword(pp)) return false; return true; }
        global.DiariPasswordPolicy={MIN_LEN:MIN_LEN, MAX_LEN:MAX_LEN, getChecklistState:getChecklistState, getStrengthScoreMeterOnly:getStrengthScoreMeterOnly, getStrengthBandMeter:getStrengthBandMeter, isCommonPassword:isCommonPassword, getPasswordBlockMessage:getPasswordBlockMessage, isPasswordSubmitReady:isPasswordSubmitReady};
    })(window);

    // Live strength meter
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

    // Inline validation
    const signupIds = ['username','signupEmail','firstName','lastName','gender','birthday','createPassword','confirmPassword'];
    let signupAvailability = { username: { val:'', ok:null, pending:null } };
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
        const body = `check_username=1&username=${encodeURIComponent(value)}`;
        state.pending = fetch('signup.php',{method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
            .then(r=>r.json().then(d=>({ok:r.ok,d})))
            .then(({ok,d})=>{
                if(!ok) return true;
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

    // Birthday calendar icon click
    const birthdayInput = document.getElementById('birthday');
    const birthdayIcon = document.getElementById('birthdayIcon');
    if(birthdayIcon && birthdayInput){
        const openPicker = (e)=>{
            e.preventDefault();
            try{ if(typeof birthdayInput.showPicker==='function') birthdayInput.showPicker(); else birthdayInput.focus(); birthdayInput.click(); }catch(_){ birthdayInput.focus(); }
        };
        birthdayIcon.addEventListener('click', openPicker);
    }

    // Signup form submission
    if (signupForm) {
        if (neggyContainer) neggyContainer.style.display = 'none';
        signupForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            let isValid = true;
            signupIds.forEach(id=>{ if(!validateSignupField(id)) isValid=false; });
            updateSignupButton();
            if(!isValid){
                const firstErr = document.querySelector('#createAccountForm .custom-error.show');
                if(firstErr) firstErr.scrollIntoView({behavior:'smooth', block:'nearest'});
                return;
            }
            const uname = document.getElementById('username').value.trim();
            const unameOk = await checkAvailabilityBond('username', uname);
            if(!unameOk) return;

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating...';

            fetch('signup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success) {
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
                    document.querySelectorAll('#createAccountForm .form-input').forEach(i=>i.classList.remove('error','success'));
                    let sec=60;
                    const orig = successEl.innerHTML;
                    const iv=setInterval(()=>{
                        sec--;
                        successEl.innerHTML = orig + `<br><span style="font-weight:700;">Auto-redirect in ${sec}s...</span>`;
                        if(sec<=0){ clearInterval(iv); window.location.href='index.php'; }
                    },1000);
                    successEl.style.cursor='pointer'; successEl.title='Click to go to login';
                    successEl.onclick=()=>{ clearInterval(iv); window.location.href='index.php'; };
                } else {
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
                                const fallback = document.getElementById('confirmPassword');
                                if(fallback) showErrorBond(fallback, msg);
                            }
                            mapped=true;
                        });
                        if(!mapped){
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
</script>
</body>
</html>

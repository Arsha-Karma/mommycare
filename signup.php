<?php
include_once 'User.php';
$userObj = new User();

// Initialize error variables
$nameErr = $emailErr = $passwordErr = $confirmErr = "";
$name = $email = $password = $confirm_password = "";
$successMsg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    $result = $userObj->signup($name, $email, $password, $confirm_password);

    if (!$result['success']) {
        $errors = $result['errors'];
        $nameErr = $errors['name'] ?? "";
        $emailErr = $errors['email'] ?? "";
        $passwordErr = $errors['password'] ?? "";
        $confirmErr = $errors['confirm_password'] ?? "";
        
        // Check if it's a duplicate user error
        if (strpos($nameErr, 'already exists') !== false || strpos($emailErr, 'already exists') !== false) {
            $nameErr = "User with this name or email already exists";
            $emailErr = "User with this name or email already exists";
        }
    } else {
        $successMsg = $result['message'];
        $name = $email = $password = $confirm_password = "";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Signup - mommycare</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #42375a;
    line-height: 1.6;
    background: #f8f5f9;
    padding-top: 80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-height: 100vh;
}

header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
}

nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 5vw;
    max-width: 1400px;
    margin: 0 auto;
}

.logo {
    font-size: 2.2rem;
    color: #f98293;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-decoration: none;
    transition: transform 0.3s ease;
}

.logo:hover {
    transform: scale(1.05);
}

nav ul {
    list-style: none;
    display: flex;
    gap: 2.5rem;
    margin: 0;
    padding: 0;
    align-items: center;
}

nav ul li a {
    text-decoration: none;
    color: #42375a;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    position: relative;
    padding: 0.5rem 0;
}

nav ul li a:hover {
    color: #f98293;
}

nav ul li a::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: #f98293;
    transition: width 0.3s ease;
}

nav ul li a:hover::after {
    width: 100%;
}

/* ===== SIGNUP FORM STYLES ===== */
.form-container { 
    background: #fff; 
    padding: 2rem; 
    border-radius: 10px; 
    box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
    width: 100%; 
    max-width: 400px; 
    margin: 2rem auto;
}
h2 { text-align: center; color: #f98293; margin-bottom: 1.5rem; }
.form-group { margin-bottom: 1rem; }
input[type=text], input[type=email], input[type=password] { 
    width: 100%; 
    padding: 12px; 
    margin: 5px 0; 
    border-radius: 8px; 
    border: 1px solid #ddd;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}
input:focus {
    outline: none;
    border-color: #f98293;
}
input.error { 
    border-color: #e74c3c; 
    background-color: #fdf2f2;
}
button { 
    width: 100%; 
    padding: 12px; 
    background: linear-gradient(135deg, #93e2bb 0%, #7dc9a5 100%); 
    border: none; 
    color: #fff; 
    font-size: 1rem; 
    border-radius: 8px; 
    cursor: pointer; 
    margin-top: 10px; 
    font-weight: 600;
    transition: all 0.3s ease;
}
button:hover {
    background: linear-gradient(135deg, #7dc9a5 0%, #6bb894 100%);
    transform: translateY(-2px);
}
.error-message { 
    color: #e74c3c; 
    font-size: 0.85rem; 
    margin-top: 5px;
    display: block;
}
.success-message { 
    color: #27ae60; 
    text-align: center; 
    margin-bottom: 1rem;
    padding: 10px;
    background: #d5f4e6;
    border-radius: 5px;
    border: 1px solid #27ae60;
}
a { color: #f98293; text-decoration: none; font-weight: 600; }
a:hover { text-decoration: underline; }
.form-footer { text-align: center; margin-top: 1.5rem; }
.existing-user-error {
    background: #fdf2f2;
    border: 1px solid #e74c3c;
    color: #e74c3c;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 1rem;
    text-align: center;
    font-weight: 600;
}
</style>
</head>
<body>
    <header>
        <nav>
            <a href="index.php" class="logo">mommycare</a>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="index.php#featured-products">Products</a></li>
                <li><a href="orders.php">Orders</a></li>
            </ul>
        </nav>
    </header>

    <div class="form-container">
        <h2>Create Account</h2>
        
        <?php if($successMsg): ?>
            <div class='success-message'><?php echo $successMsg; ?></div>
        <?php endif; ?>

        <?php if($nameErr && (strpos($nameErr, 'already exists') !== false)): ?>
            <div class='existing-user-error'>
                User with this name or email already exists
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="signupForm">
            <div class="form-group">
                <input type="text" name="name" placeholder="Full Name" value="<?php echo htmlspecialchars($name); ?>" 
                       class="<?php echo $nameErr ? 'error' : ''; ?>" required>
                <div class="error-message" id="nameErr">
                    <?php echo (strpos($nameErr, 'already exists') === false) ? $nameErr : ''; ?>
                </div>
            </div>
            
            <div class="form-group">
                <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email); ?>" 
                       class="<?php echo $emailErr ? 'error' : ''; ?>" required>
                <div class="error-message" id="emailErr">
                    <?php echo (strpos($emailErr, 'already exists') === false) ? $emailErr : ''; ?>
                </div>
            </div>
            
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" value="<?php echo htmlspecialchars($password); ?>" 
                       class="<?php echo $passwordErr ? 'error' : ''; ?>" required>
                <div class="error-message" id="passwordErr"><?php echo $passwordErr; ?></div>
            </div>
            
            <div class="form-group">
                <input type="password" name="confirm_password" placeholder="Confirm Password" 
                       value="<?php echo htmlspecialchars($confirm_password); ?>" 
                       class="<?php echo $confirmErr ? 'error' : ''; ?>" required>
                <div class="error-message" id="confirmErr"><?php echo $confirmErr; ?></div>
            </div>
            
            <button type="submit">Create Account</button>
        </form>
        
        <div class="form-footer">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>

<script>
const form = document.getElementById('signupForm');
const nameField = document.querySelector('input[name="name"]');
const emailField = document.querySelector('input[name="email"]');
const passwordField = document.querySelector('input[name="password"]');
const confirmField = document.querySelector('input[name="confirm_password"]');

const nameErr = document.getElementById('nameErr');
const emailErr = document.getElementById('emailErr');
const passwordErr = document.getElementById('passwordErr');
const confirmErr = document.getElementById('confirmErr');

function showError(field, errorDiv, message) {
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
    field.classList.add('error');
}

function hideError(field, errorDiv) {
    errorDiv.textContent = '';
    errorDiv.style.display = 'none';
    field.classList.remove('error');
}

// Live validation
nameField.addEventListener('input', () => {
    if(nameField.value.trim().length < 3) {
        showError(nameField, nameErr, "Name must be at least 3 characters");
    } else {
        hideError(nameField, nameErr);
    }
});

emailField.addEventListener('input', () => {
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if(!emailPattern.test(emailField.value.trim())) {
        showError(emailField, emailErr, "Please enter a valid email address");
    } else {
        hideError(emailField, emailErr);
    }
});

passwordField.addEventListener('input', () => {
    const pass = passwordField.value.trim();
    const confirmPass = confirmField.value.trim();

    if(pass.length < 6) {
        showError(passwordField, passwordErr, "Password must be at least 6 characters");
    } else {
        hideError(passwordField, passwordErr);
    }

    if(confirmPass !== '') {
        if(pass !== confirmPass) {
            showError(confirmField, confirmErr, "Passwords do not match");
        } else {
            hideError(confirmField, confirmErr);
        }
    }
});

confirmField.addEventListener('input', () => {
    if(confirmField.value.trim() !== passwordField.value.trim()) {
        showError(confirmField, confirmErr, "Passwords do not match");
    } else {
        hideError(confirmField, confirmErr);
    }
});

// Form submission validation
form.addEventListener('submit', function(e) {
    let isValid = true;

    if(nameField.value.trim().length < 3) {
        showError(nameField, nameErr, "Name must be at least 3 characters");
        isValid = false;
    }
    
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if(!emailPattern.test(emailField.value.trim())) {
        showError(emailField, emailErr, "Please enter a valid email address");
        isValid = false;
    }
    
    if(passwordField.value.trim().length < 6) {
        showError(passwordField, passwordErr, "Password must be at least 6 characters");
        isValid = false;
    }
    
    if(confirmField.value.trim() !== passwordField.value.trim()) {
        showError(confirmField, confirmErr, "Passwords do not match");
        isValid = false;
    }

    if(!isValid) {
        e.preventDefault();
    }
});
</script>
</body>
</html>
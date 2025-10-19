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
        $nameErr = $errors['name'];
        $emailErr = $errors['email'];
        $passwordErr = $errors['password'];
        $confirmErr = $errors['confirm_password'];
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
body { font-family: Arial, sans-serif; background: #f8f5f9; display: flex; justify-content: center; align-items: center; height: 100vh; }
.form-container { background: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
h2 { text-align: center; color: #f98293; }
input[type=text], input[type=email], input[type=password] { width: 100%; padding: 10px; margin: 5px 0; border-radius: 5px; border: 1px solid #ccc; }
input.error { border-color: red; }
button { width: 100%; padding: 10px; background: #93e2bb; border: none; color: #fff; font-size: 1rem; border-radius: 5px; cursor: pointer; margin-top: 10px; }
.error-message { color: red; font-size: 0.9rem; margin-bottom: 5px; display:none; }
.success-message { color: green; text-align: center; margin-bottom: 10px; }
a { color: #f98293; text-decoration: none; }
</style>
</head>
<body>
<div class="form-container">
    <h2>Signup</h2>
    <?php if($successMsg) echo "<div class='success-message'>$successMsg</div>"; ?>
    <form method="POST" action="" id="signupForm">
        <div>
            <input type="text" name="name" placeholder="Full Name" value="<?php echo htmlspecialchars($name); ?>" required>
            <div class="error-message" id="nameErr"><?php echo $nameErr; ?></div>
        </div>
        <div>
            <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email); ?>" required>
            <div class="error-message" id="emailErr"><?php echo $emailErr; ?></div>
        </div>
        <div>
            <input type="password" name="password" placeholder="Password" value="<?php echo htmlspecialchars($password); ?>" required>
            <div class="error-message" id="passwordErr"><?php echo $passwordErr; ?></div>
        </div>
        <div>
            <input type="password" name="confirm_password" placeholder="Confirm Password" value="<?php echo htmlspecialchars($confirm_password); ?>" required>
            <div class="error-message" id="confirmErr"><?php echo $confirmErr; ?></div>
        </div>
        <button type="submit">Signup</button>
    </form>
    <p style="text-align:center;">Already have an account? <a href="login.php">Login</a></p>
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
    const emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2,3}$/;
    if(!emailPattern.test(emailField.value.trim())) {
        showError(emailField, emailErr, "Invalid email format");
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
    const emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2,3}$/;
    if(!emailPattern.test(emailField.value.trim())) {
        showError(emailField, emailErr, "Invalid email format");
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
        e.preventDefault(); // Stop submission
    }
});
</script>
</body>
</html>

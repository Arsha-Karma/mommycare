<?php
require_once 'config.php'; 
include_once 'user.php';

$userObj = new User();

$identifierErr = $passwordErr = "";
$identifier = $password = "";
$successMsg = "";

// Admin credentials
define('ADMIN_NAME', 'Abhi');
define('ADMIN_EMAIL', 'abhi@gmail.com');
define('ADMIN_PASSWORD', 'abhi123');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier']);
    $password = trim($_POST['password']);

    // Check if it's admin login
    $isAdmin = false;
    if (($identifier === ADMIN_NAME || $identifier === ADMIN_EMAIL) && $password === ADMIN_PASSWORD) {
        $isAdmin = true;
        
        // Set admin session
        session_start();
        $_SESSION['user_id'] = 'admin';
        $_SESSION['user_name'] = ADMIN_NAME;
        $_SESSION['user_email'] = ADMIN_EMAIL;
        $_SESSION['is_admin'] = true;
        
        // Redirect to admin page
        header("Location: admin.php");
        exit;
    }

    // If not admin, check regular user login
    $result = $userObj->login($identifier, $password);

    if (!$result['success']) {
        // Use errors returned from login method
        $identifierErr = $result['errors']['identifier'] ?? "";
        $passwordErr   = $result['errors']['password'] ?? "";
    } else {
        $successMsg = $result['message'];
        // Redirect to user index page
        header("Location: index.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - mommycare</title>
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

.form-container { 
    background: #fff; 
    padding: 2rem; 
    border-radius: 10px; 
    box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
    width: 100%; 
    max-width: 400px; 
    margin: 2rem auto;
}

h2 { 
    text-align: center; 
    color: #f98293; 
    margin-bottom: 1.5rem;
}

input[type=text], input[type=password] { 
    width: 100%; 
    padding: 10px; 
    margin: 5px 0; 
    border-radius: 5px; 
    border: 1px solid #ccc; 
}

input.error { 
    border-color: red; 
}

button { 
    width: 100%; 
    padding: 10px; 
    background: #93e2bb; 
    border: none; 
    color: #fff; 
    font-size: 1rem; 
    border-radius: 5px; 
    cursor: pointer; 
    margin-top: 10px; 
}

button:hover {
    background: #7dc9a5;
}

.error-message { 
    color: red; 
    font-size: 0.9rem; 
    margin-bottom: 5px; 
}

.success-message { 
    color: green; 
    text-align: center; 
    margin-bottom: 10px; 
}

a { 
    color: #f98293; 
    text-decoration: none; 
}

a:hover {
    text-decoration: underline;
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
    <h2>Login</h2>
    <?php if($successMsg) echo "<div class='success-message'>$successMsg</div>"; ?>
    <form method="POST" action="" id="loginForm">
        <div>
            <input type="text" name="identifier" placeholder="Name or Email" value="<?php echo htmlspecialchars($identifier); ?>" class="<?php echo $identifierErr ? 'error' : ''; ?>" required>
            <div class="error-message" id="identifierErr"><?php echo $identifierErr; ?></div>
        </div>
        <div>
            <input type="password" name="password" placeholder="Password" value="<?php echo htmlspecialchars($password); ?>" class="<?php echo $passwordErr ? 'error' : ''; ?>" required>
            <div class="error-message" id="passwordErr"><?php echo $passwordErr; ?></div>
        </div>
        <button type="submit">Login</button>
    </form>
    <p style="text-align:center;">Don't have an account? <a href="signup.php">Signup</a></p>
</div>

<script>
// Client-side live validation
document.getElementById('loginForm').addEventListener('input', function(e) {
    const identifierField = this.identifier;
    const passwordField = this.password;

    const identifierErrElem = document.getElementById('identifierErr');
    const passwordErrElem   = document.getElementById('passwordErr');

    // Identifier validation
    if (identifierField.value.trim() === "") {
        identifierErrElem.textContent = "Name or Email is required.";
        identifierField.classList.add('error');
    } else {
        // Only clear if no server-side error
        if (!<?php echo json_encode($identifierErr); ?>) {
            identifierErrElem.textContent = "";
            identifierField.classList.remove('error');
        }
    }

    // Password validation
    if (passwordField.value.trim() === "") {
        passwordErrElem.textContent = "Password is required.";
        passwordField.classList.add('error');
    } else {
        if (!<?php echo json_encode($passwordErr); ?>) {
            passwordErrElem.textContent = "";
            passwordField.classList.remove('error');
        }
    }
});
</script>
</body>
</html>
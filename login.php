<?php
include_once 'User.php';

$userObj = new User();

// Initialize variables
$identifierErr = $passwordErr = "";
$identifier = $password = "";
$successMsg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier']);
    $password = trim($_POST['password']);

    $result = $userObj->login($identifier, $password);

    if (!$result['success']) {
        // Use errors returned from login method
        $identifierErr = $result['errors']['identifier'] ?? "";
        $passwordErr   = $result['errors']['password'] ?? "";
    } else {
        $successMsg = $result['message'];
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
body { font-family: Arial, sans-serif; background: #f8f5f9; display: flex; justify-content: center; align-items: center; height: 100vh; }
.form-container { background: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
h2 { text-align: center; color: #f98293; }
input[type=text], input[type=password] { width: 100%; padding: 10px; margin: 5px 0; border-radius: 5px; border: 1px solid #ccc; }
input.error { border-color: red; }
button { width: 100%; padding: 10px; background: #93e2bb; border: none; color: #fff; font-size: 1rem; border-radius: 5px; cursor: pointer; margin-top: 10px; }
.error-message { color: red; font-size: 0.9rem; margin-bottom: 5px; }
.success-message { color: green; text-align: center; margin-bottom: 10px; }
a { color: #f98293; text-decoration: none; }
</style>
</head>
<body>
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

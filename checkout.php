<?php
session_start();
include('db.php');

// Razorpay credentials
define('RAZORPAY_KEY_ID', 'rzp_test_LFEA5QeDc3uh7A');
define('RAZORPAY_KEY_SECRET', 'UcSEiMzamwDuhxLKkpPz1VUj');

$db = new Database();
$user_id = $_SESSION['user_id'] ?? null;

if(!$user_id){
    header("Location: login.php");
    exit;
}

// Fetch user details
$user_stmt = $db->conn->prepare("SELECT name, email FROM users WHERE id=?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

// Fetch cart items with product details (only active items)
$sql = "SELECT c.quantity, c.product_id, p.* FROM cart c 
        JOIN products p ON c.product_id=p.id 
        WHERE c.user_id=? AND c.status='active'";
$stmt = $db->conn->prepare($sql);
$stmt->bind_param("i",$user_id);
$stmt->execute();
$result = $stmt->get_result();
$products_in_cart = [];
while($row = $result->fetch_assoc()){
    $products_in_cart[] = $row;
}
$stmt->close();

// If cart is empty, redirect
if(empty($products_in_cart)){
    echo "<script>alert('Your cart is empty!'); window.location.href='cart.php';</script>";
    exit;
}

// Calculate totals
$grand_total = 0;
$item_count = 0;
foreach($products_in_cart as $product){
    $grand_total += $product['price'] * $product['quantity'];
    $item_count += $product['quantity'];
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $full_name = $user['name'];
    $email = $user['email'];
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Basic validation
    if(empty($phone) || empty($address)){
        $error = "All fields are required!";
    } else {
        // Store order details in session for payment verification
        $_SESSION['pending_order'] = [
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'grand_total' => $grand_total,
            'products' => $products_in_cart
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Checkout - mommycare</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<style>
/* ===== RESET & BASE STYLES ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #faf7fc 0%, #f0ebf5 100%);
    min-height: 100vh;
    color: #42375a;
    line-height: 1.6;
}

/* ===== HEADER & NAVIGATION ===== */
header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
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

.cart-link {
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    color: #fff;
    border-radius: 25px;
    padding: 0.7rem 1.5rem;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(249, 130, 147, 0.3);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.cart-link:hover {
    background: linear-gradient(135deg, #e06d7f 0%, #d45a6d 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(249, 130, 147, 0.4);
}

/* ===== HERO SECTION ===== */
.hero-section {
    text-align: center;
    padding: 9rem 5vw 3rem;
    color: #42375a;
    background: linear-gradient(135deg, rgba(250, 247, 252, 0.9) 0%, rgba(240, 235, 245, 0.9) 100%);
}

.hero-section h1 {
    font-size: 3.5rem;
    margin-bottom: 1rem;
    font-weight: 800;
    color: #f98293;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
}

.hero-section p {
    font-size: 1.3rem;
    opacity: 0.8;
    font-weight: 500;
}

/* ===== MAIN CHECKOUT CONTAINER ===== */
.checkout-container {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 3rem;
    padding: 0 5vw 5rem;
    max-width: 1400px;
    margin: 0 auto;
    align-items: start;
}

/* ===== CHECKOUT FORM SECTION ===== */
.checkout-form-section {
    background: #fff;
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: 0 10px 40px rgba(16, 16, 90, 0.15);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.checkout-form-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
}

.checkout-form-section:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 50px rgba(16, 16, 90, 0.2);
}

.checkout-form-section h2 {
    font-size: 1.8rem;
    color: #2d3748;
    margin-bottom: 2rem;
    font-weight: 700;
    position: relative;
    padding-bottom: 0.5rem;
}

.checkout-form-section h2::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 60px;
    height: 3px;
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    border-radius: 2px;
}

.back-to-cart {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    color: #f98293;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    background: rgba(249, 130, 147, 0.1);
}

.back-to-cart:hover {
    background: rgba(249, 130, 147, 0.2);
    transform: translateX(-5px);
}

/* ===== FORM STYLES ===== */
.form-group {
    margin-bottom: 2rem;
    position: relative;
}

.form-group label {
    display: block;
    margin-bottom: 0.8rem;
    color: #2d3748;
    font-weight: 600;
    font-size: 1rem;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 1rem 1.2rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    transition: all 0.3s ease;
    background: #f8fafc;
    resize: vertical;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #f98293;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(249, 130, 147, 0.1);
    transform: translateY(-2px);
}

textarea#address {
    min-height: 120px;
    line-height: 1.5;
}

/* Read-only input styles */
.form-group input[readonly] {
    background: #f1f5f9;
    color: #64748b;
    border-color: #cbd5e1;
    cursor: not-allowed;
}

/* ===== ORDER SUMMARY SECTION ===== */
.order-summary {
    background: #fff;
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: 0 10px 40px rgba(16, 16, 90, 0.15);
    height: fit-content;
    position: sticky;
    top: 7rem;
    transition: all 0.3s ease;
}

.order-summary:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 50px rgba(16, 16, 90, 0.2);
}

.order-summary h2 {
    font-size: 1.8rem;
    color: #2d3748;
    margin-bottom: 2rem;
    font-weight: 700;
    position: relative;
    padding-bottom: 0.5rem;
}

.order-summary h2::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 60px;
    height: 3px;
    background: linear-gradient(135deg, #93e2bb 0%, #7dc9a5 100%);
    border-radius: 2px;
}

/* ===== ORDER ITEMS ===== */
.order-item {
    display: flex;
    gap: 1.2rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid #f1f5f9;
    transition: all 0.3s ease;
}

.order-item:hover {
    transform: translateX(5px);
}

.order-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.order-item img {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}

.order-item:hover img {
    transform: scale(1.05);
}

.order-item-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.order-item-details h4 {
    font-size: 1rem;
    color: #2d3748;
    margin-bottom: 0.4rem;
    font-weight: 600;
    line-height: 1.4;
}

.order-item-details p {
    font-size: 0.9rem;
    color: #718096;
    font-weight: 500;
}

.order-item-price {
    font-weight: 700;
    color: #f98293;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
}

/* ===== SUMMARY ROWS ===== */
.summary-rows {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 2px solid #e2e8f0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1.2rem;
    color: #4a5568;
    font-weight: 500;
    font-size: 1rem;
}

.summary-row.total {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 3px solid #e2e8f0;
    font-size: 1.4rem;
    font-weight: 700;
    color: #f98293;
}

.shipping-free {
    color: #48bb78;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.shipping-free::before {
    content: '✓';
    font-weight: bold;
}

/* ===== PLACE ORDER BUTTON ===== */
.place-order-btn {
    width: 100%;
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    color: #fff;
    border: none;
    padding: 1.2rem 2rem;
    border-radius: 15px;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    margin-top: 2rem;
    transition: all 0.3s ease;
    box-shadow: 0 6px 20px rgba(249, 130, 147, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.8rem;
    position: relative;
    overflow: hidden;
}

.place-order-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.place-order-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(249, 130, 147, 0.6);
}

.place-order-btn:hover::before {
    left: 100%;
}

.place-order-btn:active {
    transform: translateY(-1px);
}

.place-order-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.place-order-btn:disabled:hover::before {
    left: -100%;
}

/* ===== ERROR MESSAGE ===== */
.error-message {
    background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
    color: #c53030;
    padding: 1.2rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    font-weight: 600;
    border-left: 4px solid #fc8181;
    box-shadow: 0 4px 12px rgba(252, 129, 129, 0.2);
    animation: shake 0.5s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

/* ===== LOADING ANIMATION ===== */
.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ===== SCROLLBAR STYLING ===== */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #e06d7f 0%, #d45a6d 100%);
}

/* ===== RESPONSIVE DESIGN ===== */
@media (max-width: 1200px) {
    .checkout-container {
        grid-template-columns: 1fr 350px;
        gap: 2.5rem;
    }
}

@media (max-width: 968px) {
    .checkout-container {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .order-summary {
        position: relative;
        top: 0;
        order: -1;
    }
    
    .hero-section {
        padding: 8rem 5vw 2rem;
    }
    
    .hero-section h1 {
        font-size: 2.8rem;
    }
    
    nav {
        padding: 1rem 3vw;
    }
    
    nav ul {
        gap: 1.5rem;
    }
}

@media (max-width: 768px) {
    .checkout-form-section,
    .order-summary {
        padding: 2rem;
        border-radius: 15px;
    }
    
    .hero-section {
        padding: 7rem 3vw 2rem;
    }
    
    .hero-section h1 {
        font-size: 2.3rem;
    }
    
    .hero-section p {
        font-size: 1.1rem;
    }
    
    nav {
        flex-direction: column;
        gap: 1rem;
        padding: 1rem;
    }
    
    nav ul {
        gap: 1rem;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .form-group input,
    .form-group textarea {
        padding: 0.9rem 1rem;
    }
    
    .order-item {
        gap: 1rem;
    }
    
    .order-item img {
        width: 60px;
        height: 60px;
    }
}

@media (max-width: 480px) {
    .checkout-form-section,
    .order-summary {
        padding: 1.5rem;
        margin: 0 1rem;
    }
    
    .checkout-container {
        padding: 0 1rem 3rem;
    }
    
    .hero-section {
        padding: 6rem 1rem 1.5rem;
    }
    
    .hero-section h1 {
        font-size: 2rem;
    }
    
    .hero-section p {
        font-size: 1rem;
    }
    
    .place-order-btn {
        padding: 1rem 1.5rem;
        font-size: 1rem;
    }
    
    .summary-row.total {
        font-size: 1.2rem;
    }
    
    .order-item-details h4 {
        font-size: 0.9rem;
    }
    
    .order-item-price {
        font-size: 1rem;
    }
}

/* ===== ANIMATIONS ===== */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.checkout-form-section,
.order-summary {
    animation: fadeInUp 0.6s ease;
}

.order-item {
    animation: fadeInUp 0.6s ease;
}

/* ===== FOCUS STATES FOR ACCESSIBILITY ===== */
.form-group input:focus-visible,
.form-group textarea:focus-visible,
.place-order-btn:focus-visible {
    outline: 2px solid #f98293;
    outline-offset: 2px;
}

/* ===== PRINT STYLES ===== */
@media print {
    header,
    .back-to-cart,
    .place-order-btn {
        display: none;
    }
    
    .checkout-container {
        grid-template-columns: 1fr;
        box-shadow: none;
    }
    
    .checkout-form-section,
    .order-summary {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}

/* ===== DARK MODE SUPPORT ===== */
@media (prefers-color-scheme: dark) {
    body {
        background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
        color: #e2e8f0;
    }
    
    .checkout-form-section,
    .order-summary {
        background: #2d3748;
        color: #e2e8f0;
    }
    
    .form-group input,
    .form-group textarea {
        background: #4a5568;
        border-color: #718096;
        color: #e2e8f0;
    }
    
    .form-group input:focus,
    .form-group textarea:focus {
        background: #4a5568;
        border-color: #f98293;
    }
    
    .form-group input[readonly] {
        background: #718096;
        color: #cbd5e0;
    }
    
    .checkout-form-section h2,
    .order-summary h2 {
        color: #e2e8f0;
    }
    
    .order-item-details h4 {
        color: #e2e8f0;
    }
    
    .order-item-details p {
        color: #a0aec0;
    }
    
    .summary-row {
        color: #cbd5e0;
    }
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
        <a href="cart.php" class="cart-link">🛒 Cart (<?php echo $item_count; ?>)</a>
    </nav>
</header>

<div class="hero-section">
    <h1>Checkout</h1>
    <p>Complete your order with delivery details</p>
</div>

<div class="checkout-container">
    <div class="checkout-form-section">
        <a href="cart.php" class="back-to-cart">← Back to Cart</a>
        
        <h2>Delivery Information</h2>
        
        <?php if(isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" id="checkoutForm">
            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number *</label>
                <input type="tel" id="phone" name="phone" required 
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                       placeholder="Enter your 10-digit phone number">
            </div>
            
            <div class="form-group">
                <label for="address">Delivery Address *</label>
                <textarea id="address" name="address" rows="4" required 
                          placeholder="Enter your complete delivery address including street, city, state, and PIN code"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
            </div>
            
            <button type="submit" class="place-order-btn" id="placeOrderBtn">
                💳 Pay ₹<?php echo number_format($grand_total, 2); ?>
            </button>
        </form>
    </div>
    
    <div class="order-summary">
        <h2>Order Summary</h2>
        
        <?php foreach($products_in_cart as $product): ?>
        <div class="order-item">
            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
            <div class="order-item-details">
                <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                <p>Qty: <?php echo $product['quantity']; ?></p>
            </div>
            <div class="order-item-price">
                ₹<?php echo number_format($product['price'] * $product['quantity'], 2); ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="summary-rows">
            <div class="summary-row">
                <span>Items:</span>
                <span><?php echo $item_count; ?></span>
            </div>
            
            <div class="summary-row">
                <span>Subtotal:</span>
                <span>₹<?php echo number_format($grand_total, 2); ?></span>
            </div>
            
            <div class="summary-row">
                <span>Shipping:</span>
                <span class="shipping-free">FREE</span>
            </div>
            
            <div class="summary-row total">
                <span>Total:</span>
                <span>₹<?php echo number_format($grand_total, 2); ?></span>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validate form fields
    const phone = document.getElementById('phone').value.trim();
    const address = document.getElementById('address').value.trim();
    
    if(!phone || !address) {
        alert('Please fill all required fields!');
        return;
    }
    
    // Phone number validation
    const phoneRegex = /^[6-9]\d{9}$/;
    if (!phoneRegex.test(phone)) {
        alert('Please enter a valid 10-digit Indian phone number!');
        return;
    }
    
    // Disable button to prevent double submission
    const btn = document.getElementById('placeOrderBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<div class="loading"></div> Processing...';
    
    // Submit form via AJAX to store data in session first
    const formData = new FormData(this);
    
    fetch('checkout.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text();
    })
    .then(() => {
        // After form is submitted and session is set, initiate Razorpay payment
        initiateRazorpayPayment();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Something went wrong. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

function initiateRazorpayPayment() {
    const options = {
        key: '<?php echo RAZORPAY_KEY_ID; ?>',
        amount: <?php echo $grand_total * 100; ?>, // Amount in paise
        currency: 'INR',
        name: 'mommycare',
        description: 'Order Payment',
        image: 'https://your-logo-url.com/logo.png', // Add your logo URL
        handler: function(response) {
            console.log('Payment successful:', response);
            
            // Create a form and submit it to verify_payment.php
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'verify_payment.php';
            
            const paymentId = document.createElement('input');
            paymentId.type = 'hidden';
            paymentId.name = 'razorpay_payment_id';
            paymentId.value = response.razorpay_payment_id;
            form.appendChild(paymentId);
            
            document.body.appendChild(form);
            form.submit();
        },
        prefill: {
            name: '<?php echo htmlspecialchars($user['name']); ?>',
            email: '<?php echo htmlspecialchars($user['email']); ?>',
            contact: document.getElementById('phone').value
        },
        theme: {
            color: '#f98293'
        },
        modal: {
            ondismiss: function() {
                // Re-enable button if payment is cancelled
                const btn = document.getElementById('placeOrderBtn');
                btn.disabled = false;
                btn.innerHTML = '💳 Pay ₹<?php echo number_format($grand_total, 2); ?>';
                console.log('Payment modal dismissed');
            }
        }
    };
    
    const rzp = new Razorpay(options);
    rzp.open();
    
    // Log for debugging
    console.log('Razorpay payment initiated');
}

// Add some interactive effects
document.querySelectorAll('.form-group input, .form-group textarea').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.style.transform = 'translateY(-2px)';
    });
    
    input.addEventListener('blur', function() {
        this.parentElement.style.transform = 'translateY(0)';
    });
});
</script>

</body>
</html>
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

// Fetch cart items with product details
$sql = "SELECT c.quantity, c.product_id, p.* FROM cart c 
        JOIN products p ON c.product_id=p.id 
        WHERE c.user_id=?";
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
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])){
    $full_name = $user['name'];
    $email = $user['email'];
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $payment_method = $_POST['payment_method'];
    
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
            'payment_method' => $payment_method,
            'grand_total' => $grand_total,
            'products' => $products_in_cart
        ];
        
        if($payment_method === 'cod'){
            // Process COD order directly
            $order_stmt = $db->conn->prepare("INSERT INTO orders (user_id, total_amount, full_name, email, phone, address, payment_method, order_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $order_stmt->bind_param("idsssss", $user_id, $grand_total, $full_name, $email, $phone, $address, $payment_method);
            
            if($order_stmt->execute()){
                $order_id = $order_stmt->insert_id;
                
                // Insert order items with proper product_id
                foreach($products_in_cart as $product){
                    // Use product_id if available, otherwise use id
                    $prod_id = isset($product['product_id']) ? $product['product_id'] : $product['id'];
                    $item_stmt = $db->conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                    $item_stmt->bind_param("iiid", $order_id, $prod_id, $product['quantity'], $product['price']);
                    $item_stmt->execute();
                    $item_stmt->close();
                }
                
                // IMPORTANT: Clear all cart items for this user
                $clear_stmt = $db->conn->prepare("DELETE FROM cart WHERE user_id=?");
                $clear_stmt->bind_param("i", $user_id);
                $clear_stmt->execute();
                $clear_stmt->close();
                
                // Clear pending order from session
                unset($_SESSION['pending_order']);
                
                // Redirect to success page
                header("Location: order_success.php?order_id=" . $order_id);
                exit;
            } else {
                $error = "Failed to place order. Please try again.";
            }
            $order_stmt->close();
        }
        // If online payment, the JavaScript will handle Razorpay
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
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: rgba(250,247,252,0.95);
    min-height: 100vh;
    color: #42375a;
}

header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    background: rgba(255,255,255,0.95);
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 1rem 5vw;
}

nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo {
    font-size: 2rem;
    color: #f98293;
    font-weight: bold;
    letter-spacing: 1px;
}

nav ul {
    list-style: none;
    display: flex;
    gap: 2rem;
    align-items: center;
}

nav ul li a {
    text-decoration: none;
    color: #42375a;
    font-weight: 500;
    font-size: 1rem;
    transition: color 0.3s ease;
}

nav ul li a:hover {
    color: #f98293;
}

.cart-link {
    background: #f98293;
    color: #fff;
    border-radius: 20px;
    padding: 0.6em 1.2em;
    text-decoration: none;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.3s ease;
}

.cart-link:hover {
    background: #e06d7f;
}

.hero-section {
    text-align: center;
    padding: 8rem 5vw 3rem;
    color: #42375a;
}

.hero-section h1 {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #f98293;
}

.hero-section p {
    font-size: 1.1rem;
    opacity: 0.8;
}

.checkout-container {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 2rem;
    padding: 0 5vw 4rem;
    max-width: 1400px;
    margin: 0 auto;
}

.checkout-form-section {
    background: #fff;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.checkout-form-section h2 {
    font-size: 1.5rem;
    color: #2d3748;
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #2d3748;
    font-weight: 500;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 0.8rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
    font-family: 'Segoe UI', Arial, sans-serif;
    transition: border-color 0.3s ease;
}

textarea#address {
    min-height: 100px;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #f98293;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.payment-methods {
    display: flex;
    gap: 1rem;
    margin-top: 0.5rem;
}

.payment-option {
    flex: 1;
    position: relative;
}

.payment-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.payment-option label {
    display: block;
    padding: 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.payment-option input[type="radio"]:checked + label {
    border-color: #f98293;
    background: #fef2f4;
    color: #f98293;
}

.payment-option label:hover {
    border-color: #f98293;
}

.order-summary {
    background: #fff;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    height: fit-content;
    position: sticky;
    top: 6rem;
}

.order-summary h2 {
    font-size: 1.5rem;
    color: #2d3748;
    margin-bottom: 1.5rem;
}

.order-item {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.order-item img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
}

.order-item-details {
    flex: 1;
}

.order-item-details h4 {
    font-size: 0.95rem;
    color: #2d3748;
    margin-bottom: 0.3rem;
}

.order-item-details p {
    font-size: 0.85rem;
    color: #718096;
}

.order-item-price {
    font-weight: 600;
    color: #f98293;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
    color: #4a5568;
}

.summary-row.total {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 2px solid #e2e8f0;
    font-size: 1.3rem;
    font-weight: 600;
    color: #f98293;
}

.shipping-free {
    color: #48bb78;
    font-weight: 600;
}

.place-order-btn {
    width: 100%;
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    color: #fff;
    border: none;
    padding: 1rem;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    margin-top: 1.5rem;
    transition: transform 0.2s ease;
}

.place-order-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(249, 130, 147, 0.4);
}

.place-order-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.error-message {
    background: #fed7d7;
    color: #c53030;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.back-to-cart {
    display: inline-block;
    margin-bottom: 1rem;
    color: #f98293;
    text-decoration: none;
    font-weight: 500;
}

.back-to-cart:hover {
    text-decoration: underline;
}

@media (max-width: 968px) {
    .checkout-container {
        grid-template-columns: 1fr;
    }
    
    .order-summary {
        position: relative;
        top: 0;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .payment-methods {
        flex-direction: column;
    }
}
</style>
</head>
<body>

<header>
    <nav>
        <div class="logo">mommycare</div>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="index.php#featured-products">Shop</a></li>
            <li><a href="#">Reviews</a></li>
            <li><a href="#">Contact</a></li>
        </ul>
        <a href="cart.php" class="cart-link">🛒 Cart</a>
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
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="address">Delivery Address *</label>
                <textarea id="address" name="address" rows="4" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Payment Method *</label>
                <div class="payment-methods">
                    <div class="payment-option">
                        <input type="radio" id="cod" name="payment_method" value="cod" checked>
                        <label for="cod">💵 Cash on Delivery</label>
                    </div>
                    <div class="payment-option">
                        <input type="radio" id="online" name="payment_method" value="online">
                        <label for="online">💳 Pay Online</label>
                    </div>
                </div>
            </div>
            
            <button type="submit" name="place_order" class="place-order-btn" id="placeOrderBtn">
                💰 Pay ₹<?php echo number_format($grand_total, 2); ?>
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

<script>
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    
    if(paymentMethod === 'online') {
        e.preventDefault();
        
        // Validate form fields
        const phone = document.getElementById('phone').value.trim();
        const address = document.getElementById('address').value.trim();
        
        if(!phone || !address) {
            alert('Please fill all required fields!');
            return;
        }
        
        // Disable button to prevent double submission
        const btn = document.getElementById('placeOrderBtn');
        btn.disabled = true;
        btn.textContent = 'Processing...';
        
        // Razorpay options
        const options = {
            key: '<?php echo RAZORPAY_KEY_ID; ?>',
            amount: <?php echo $grand_total * 100; ?>, // Amount in paise
            currency: 'INR',
            name: 'mommycare',
            description: 'Order Payment',
            image: 'https://your-logo-url.com/logo.png',
            handler: function(response) {
                // Payment successful, redirect to verification page
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
                    btn.disabled = false;
                    btn.textContent = '💰 Pay ₹<?php echo number_format($grand_total, 2); ?>';
                }
            }
        };
        
        const rzp = new Razorpay(options);
        rzp.open();
    }
    // If COD, form will submit normally
});
</script>

</body>
</html>
<?php

require_once 'config.php'; 
include('db.php');


$db = new Database();
$user_id = $_SESSION['user_id'] ?? null;
if(!$user_id){
    die("Please log in to access your cart.");
}

// --- Add product to cart ---
if(isset($_GET['add'])){
    $product_id = (int)$_GET['add'];

    // Check if product already exists in cart with 'active' status
    $stmt = $db->conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=? AND status='active'");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $stmt->store_result();
    if($stmt->num_rows>0){
        $stmt->bind_result($qty);
        $stmt->fetch();
        $new_qty = $qty + 1;
        $update = $db->conn->prepare("UPDATE cart SET quantity=? WHERE user_id=? AND product_id=? AND status='active'");
        $update->bind_param("iii",$new_qty,$user_id,$product_id);
        $update->execute();
        $update->close();
    } else {
        // Insert new item with 'active' status
        $insert = $db->conn->prepare("INSERT INTO cart (user_id, product_id, quantity, status) VALUES (?,?,1,'active')");
        $insert->bind_param("ii",$user_id,$product_id);
        $insert->execute();
        $insert->close();
    }
    $stmt->close();
    header("Location: cart.php");
    exit;
}

// --- Increment quantity ---
if(isset($_POST['increment_product_id'])){
    $product_id = (int)$_POST['increment_product_id'];
    
    // Get current quantity and product stock
    $check = $db->conn->prepare("SELECT c.quantity, p.stock FROM cart c 
                                  JOIN products p ON c.product_id = p.id 
                                  WHERE c.user_id=? AND c.product_id=? AND c.status='active'");
    $check->bind_param("ii", $user_id, $product_id);
    $check->execute();
    $check->bind_result($current_qty, $stock);
    $check->fetch();
    $check->close();
    
    // Only increment if stock is available
    if($current_qty < $stock){
        $new_qty = $current_qty + 1;
        $update = $db->conn->prepare("UPDATE cart SET quantity=? WHERE user_id=? AND product_id=? AND status='active'");
        $update->bind_param("iii", $new_qty, $user_id, $product_id);
        $update->execute();
        $update->close();
        echo json_encode(['success' => true, 'quantity' => $new_qty]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Out of stock']);
    }
    exit;
}

// --- Decrement quantity ---
if(isset($_POST['decrement_product_id'])){
    $product_id = (int)$_POST['decrement_product_id'];
    
    // Get current quantity
    $check = $db->conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=? AND status='active'");
    $check->bind_param("ii", $user_id, $product_id);
    $check->execute();
    $check->bind_result($current_qty);
    $check->fetch();
    $check->close();
    
    if($current_qty > 1){
        $new_qty = $current_qty - 1;
        $update = $db->conn->prepare("UPDATE cart SET quantity=? WHERE user_id=? AND product_id=? AND status='active'");
        $update->bind_param("iii", $new_qty, $user_id, $product_id);
        $update->execute();
        $update->close();
        echo json_encode(['success' => true, 'quantity' => $new_qty]);
    } else {
        
        echo json_encode(['success' => false, 'message' => 'Minimum quantity is 1', 'quantity' => 1]);
    }
    exit;
}


// --- Remove product from cart ---
if(isset($_POST['remove_product_id'])){
    $remove_id = (int)$_POST['remove_product_id'];
    $stmt = $db->conn->prepare("DELETE FROM cart WHERE user_id=? AND product_id=? AND status='active'");
    $stmt->bind_param("ii",$user_id,$remove_id);
    $stmt->execute();
    $stmt->close();
    header("Location: cart.php");
    exit;
}

// --- Clear all cart items ---
if(isset($_POST['clear_cart'])){
    $stmt = $db->conn->prepare("DELETE FROM cart WHERE user_id=? AND status='active'");
    $stmt->bind_param("i",$user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: cart.php");
    exit;
}

// --- Fetch cart items with product details (only active items) ---
$sql = "SELECT c.quantity, c.product_id, c.status, p.* FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id=? AND c.status='active'";
$stmt = $db->conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$products_in_cart = [];
while($row = $result->fetch_assoc()){
    $products_in_cart[] = $row;
}
$stmt->close();

// Calculate totals
$grand_total = 0;
$item_count = 0;
foreach($products_in_cart as $product){
    $grand_total += $product['price'] * $product['quantity'];
    $item_count += $product['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Shopping Cart - mommycare</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    border: none;
}

.cart-link:hover {
    background: #e06d7f;
}

.hero-section {
    text-align: center;
    padding: 8rem 5vw 3rem;
    color: #42375a;
    margin-bottom: 2rem;
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

.cart-container {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 2rem;
    padding: 0 5vw 4rem;
    max-width: 1400px;
    margin: 0 auto;
}

.cart-items-section {
    background: #fff;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.cart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.cart-header h2 {
    font-size: 1.5rem;
    color: #2d3748;
}

.clear-cart-btn {
    background: #fff;
    color: #f98293;
    border: 2px solid #f98293;
    padding: 0.6rem 1.2rem;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.clear-cart-btn:hover {
    background: #fef2f4;
}

.cart-item {
    display: grid;
    grid-template-columns: 100px 1fr auto;
    gap: 1.5rem;
    padding: 1.5rem;
    background: #f7fafc;
    border-radius: 12px;
    margin-bottom: 1rem;
    align-items: center;
}

.cart-item img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 8px;
}

.item-details h3 {
    font-size: 1.1rem;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.item-details p {
    color: #718096;
    font-size: 0.9rem;
    margin-bottom: 0.8rem;
}

.item-price {
    font-size: 1.2rem;
    color: #f98293;
    font-weight: 600;
}

.stock-info {
    font-size: 0.85rem;
    color: #718096;
    margin-top: 0.3rem;
}

.stock-warning {
    color: #f56565;
    font-weight: 500;
}

.stock-available {
    color: #48bb78;
}

.item-actions {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    align-items: flex-end;
}

.quantity-controls {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    background: #fff;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.3rem 0.6rem;
}

.qty-btn {
    background: #f98293;
    color: #fff;
    border: none;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    font-weight: bold;
}

.qty-btn:hover:not(:disabled) {
    background: #e06d7f;
    transform: scale(1.1);
}

.qty-btn:disabled {
    background: #cbd5e0;
    cursor: not-allowed;
    opacity: 0.5;
}

.qty-display {
    color: #2d3748;
    font-weight: 600;
    font-size: 1rem;
    min-width: 35px;
    text-align: center;
}

.remove-btn {
    background: #fff;
    color: #f98293;
    border: 1px solid #f98293;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.remove-btn:hover {
    background: #fef2f4;
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
    margin-bottom: 2rem;
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

.checkout-btn {
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
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
}

.checkout-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(249, 130, 147, 0.4);
}

.continue-shopping-btn {
    width: 100%;
    background: #fff;
    color: #f98293;
    border: 2px solid #f98293;
    padding: 1rem;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    margin-top: 1rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.continue-shopping-btn:hover {
    background: #f7fafc;
}

.empty-cart {
    text-align: center;
    padding: 4rem 2rem;
    background: #fff;
    border-radius: 16px;
    grid-column: 1 / -1;
}

.empty-cart-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.empty-cart h2 {
    color: #2d3748;
    margin-bottom: 1rem;
}

.empty-cart p {
    color: #718096;
    margin-bottom: 2rem;
}

.notification {
    position: fixed;
    top: 100px;
    right: 20px;
    background: #fff;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    z-index: 9999;
    display: none;
    animation: slideIn 0.3s ease;
}

.notification.show {
    display: block;
}

.notification.error {
    border-left: 4px solid #f56565;
}

.notification.success {
    border-left: 4px solid #48bb78;
}

@keyframes slideIn {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@media (max-width: 968px) {
    .cart-container {
        grid-template-columns: 1fr;
    }
    
    .order-summary {
        position: relative;
        top: 0;
    }
    
    .cart-item {
        grid-template-columns: 80px 1fr;
        gap: 1rem;
    }
    
    .item-actions {
        grid-column: 1 / -1;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}
</style>
</head>
<body>

<div id="notification" class="notification"></div>

<header>
    <nav>
        <div class="logo">mommycare</div>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="index.php#featured-products">Products</a></li>
            <li><a href="orders.php">Orders</a></li>
            <li><a href="cart.php" class="cart-link">🛒 Cart (<span id="cart-count"><?php echo $item_count; ?></span>)</a></li>
        </ul>
    </nav>
</header>

<div class="hero-section">
    <h1>Your Cart</h1>
    <p>Review your selected items</p>
</div>

<div class="cart-container">
    <?php if(!empty($products_in_cart)): ?>
        <div class="cart-items-section">
            <div class="cart-header">
                <h2>Cart Items (<span id="item-count-header"><?php echo $item_count; ?></span>)</h2>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="clear_cart" value="1">
                    <button type="submit" class="clear-cart-btn" onclick="return confirm('Clear all items from cart?')">
                        🗑️ Clear Cart
                    </button>
                </form>
            </div>

            <?php foreach($products_in_cart as $product): 
                $in_stock = $product['stock'] > 0;
                $can_increment = $product['quantity'] < $product['stock'];
            ?>
            <div class="cart-item" data-product-id="<?php echo $product['product_id']; ?>">
                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                <div class="item-details">
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p><?php echo htmlspecialchars($product['description'] ?? 'Premium quality product'); ?></p>
                    <div class="item-price">₹<?php echo number_format($product['price'],2); ?></div>
                    <div class="stock-info <?php echo $in_stock ? 'stock-available' : 'stock-warning'; ?>">
                        <?php 
                        if($in_stock) {
                            echo "In Stock: " . $product['stock'] . " available";
                        } else {
                            echo "Out of Stock";
                        }
                        ?>
                    </div>
                </div>
                <div class="item-actions">
                    <div class="quantity-controls">
                        <button class="qty-btn decrement-btn" data-product-id="<?php echo $product['product_id']; ?>">−</button>
                        <span class="qty-display" data-product-id="<?php echo $product['product_id']; ?>"><?php echo $product['quantity']; ?></span>
                        <button class="qty-btn increment-btn" 
                                data-product-id="<?php echo $product['product_id']; ?>"
                                data-stock="<?php echo $product['stock']; ?>"
                                <?php echo !$can_increment ? 'disabled' : ''; ?>>+</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="remove_product_id" value="<?php echo $product['product_id']; ?>">
                        <button type="submit" class="remove-btn">🗑️ Remove</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="order-summary">
            <h2>Order Summary</h2>
            
            <div class="summary-row">
                <span>Items:</span>
                <span id="summary-items"><?php echo $item_count; ?></span>
            </div>
            
            <div class="summary-row">
                <span>Subtotal:</span>
                <span id="summary-subtotal">₹<?php echo number_format($grand_total,2); ?></span>
            </div>
            
            <div class="summary-row">
                <span>Shipping:</span>
                <span class="shipping-free">FREE</span>
            </div>
            
            <div class="summary-row total">
                <span>Total:</span>
                <span id="summary-total">₹<?php echo number_format($grand_total,2); ?></span>
            </div>

            <a href="checkout.php" class="checkout-btn">
                🔒 Proceed to Checkout
            </a>

            <a href="index.php" class="continue-shopping-btn">
                ← Continue Shopping
            </a>
        </div>

    <?php else: ?>
        <div class="empty-cart">
            <div class="empty-cart-icon">🛒</div>
            <h2>Your cart is empty</h2>
            <p>Add some products to get started!</p>
            <a href="index.php" class="continue-shopping-btn" style="max-width: 300px; margin: 0 auto;">
                ← Continue Shopping
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
// Show notification
function showNotification(message, type = 'success') {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = `notification ${type} show`;
    
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

// Update cart totals
function updateCartTotals() {
    let totalItems = 0;
    let totalPrice = 0;
    
    document.querySelectorAll('.cart-item').forEach(item => {
        const productId = item.dataset.productId;
        const qtyDisplay = item.querySelector(`.qty-display[data-product-id="${productId}"]`);
        const priceText = item.querySelector('.item-price').textContent;
        const price = parseFloat(priceText.replace('₹', '').replace(',', ''));
        const quantity = parseInt(qtyDisplay.textContent);
        
        totalItems += quantity;
        totalPrice += price * quantity;
    });
    
    document.getElementById('cart-count').textContent = totalItems;
    document.getElementById('item-count-header').textContent = totalItems;
    document.getElementById('summary-items').textContent = totalItems;
    document.getElementById('summary-subtotal').textContent = '₹' + totalPrice.toFixed(2);
    document.getElementById('summary-total').textContent = '₹' + totalPrice.toFixed(2);
}

// Increment quantity
document.querySelectorAll('.increment-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const productId = this.dataset.productId;
        const stock = parseInt(this.dataset.stock);
        const qtyDisplay = document.querySelector(`.qty-display[data-product-id="${productId}"]`);
        const currentQty = parseInt(qtyDisplay.textContent);
        
        if(currentQty >= stock) {
            showNotification('Cannot add more. Out of stock!', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('increment_product_id', productId);
        
        try {
            const response = await fetch('cart.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if(result.success) {
                qtyDisplay.textContent = result.quantity;
                
                // Disable increment button if reached stock limit
                if(result.quantity >= stock) {
                    this.disabled = true;
                }
                
                updateCartTotals();
                showNotification('Quantity updated!', 'success');
            } else {
                showNotification(result.message, 'error');
            }
        } catch(error) {
            showNotification('Error updating quantity', 'error');
        }
    });
});

// Decrement quantity
document.querySelectorAll('.decrement-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const productId = this.dataset.productId;
        const qtyDisplay = document.querySelector(`.qty-display[data-product-id="${productId}"]`);
        const currentQty = parseInt(qtyDisplay.textContent);
        
        // Prevent decrement if quantity is already 1
        if(currentQty <= 1) {
            showNotification('Minimum quantity is 1. Use remove button to delete item.', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('decrement_product_id', productId);
        
        try {
            const response = await fetch('cart.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if(result.success) {
                qtyDisplay.textContent = result.quantity;
                
                // Enable increment button
                const incrementBtn = document.querySelector(`.increment-btn[data-product-id="${productId}"]`);
                incrementBtn.disabled = false;
                
                updateCartTotals();
                showNotification('Quantity updated!', 'success');
            } else {
                showNotification(result.message, 'error');
            }
        } catch(error) {
            showNotification('Error updating quantity', 'error');
        }
    });
});
</script>

</body>
</html>
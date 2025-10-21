<?php
require_once 'config.php'; 
include('db.php');

$db = new Database();
$user_id = $_SESSION['user_id'] ?? null;
$order_id = $_GET['order_id'] ?? 0;

if(!$user_id){
    header("Location: login.php");
    exit;
}

// Fetch order details with product information
$order_sql = "SELECT o.*, p.name as product_name, p.image, p.price, p.description 
              FROM orders o 
              JOIN products p ON o.product_id = p.id 
              WHERE o.id = ? AND o.user_id = ?";
$order_stmt = $db->conn->prepare($order_sql);
$order_stmt->bind_param("ii", $order_id, $user_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$order_items = [];

$order_total = 0;
$order_details = null;

while($row = $order_result->fetch_assoc()){
    $order_items[] = $row;
    $order_total += $row['total_amount'];
    $order_details = $row; // Store first row for order details
}
$order_stmt->close();

if(empty($order_items)){
    echo "<script>alert('Order not found!'); window.location.href='index.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Success - mommycare</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    }

    .cart-link:hover {
        background: linear-gradient(135deg, #e06d7f 0%, #d45a6d 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(249, 130, 147, 0.4);
    }

    /* ===== SUCCESS HERO SECTION ===== */
    .success-hero {
        text-align: center;
        padding: 10rem 5vw 4rem;
        background: linear-gradient(135deg, rgba(147, 226, 187, 0.1) 0%, rgba(249, 130, 147, 0.1) 100%);
        position: relative;
        overflow: hidden;
    }

    .success-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="%23f98293" opacity="0.05"><path d="M30,30 Q50,10 70,30 T90,50 T70,70 T50,90 T30,70 T10,50 T30,30 Z"/></svg>');
        background-size: 200px;
        animation: float 20s infinite linear;
    }

    @keyframes float {
        0% { transform: translateY(0px) rotate(0deg); }
        100% { transform: translateY(-100px) rotate(360deg); }
    }

    .success-icon {
        font-size: 5rem;
        margin-bottom: 1.5rem;
        animation: bounce 2s infinite;
    }

    @keyframes bounce {
        0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
        40% { transform: translateY(-10px); }
        60% { transform: translateY(-5px); }
    }

    .success-hero h1 {
        font-size: 3.5rem;
        margin-bottom: 1rem;
        font-weight: 800;
        background: linear-gradient(135deg, #93e2bb 0%, #f98293 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .success-hero p {
        font-size: 1.3rem;
        color: #666;
        margin-bottom: 2rem;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }

    .order-id {
        background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
        color: #fff;
        padding: 0.8rem 2rem;
        border-radius: 50px;
        font-size: 1.5rem;
        font-weight: 700;
        display: inline-block;
        margin: 1rem 0;
        box-shadow: 0 6px 20px rgba(249, 130, 147, 0.4);
    }

    /* ===== ORDER DETAILS SECTION ===== */
    .order-details {
        padding: 3rem 5vw;
        max-width: 1200px;
        margin: 0 auto;
    }

    .order-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-bottom: 3rem;
    }

    .info-card {
        background: #fff;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 8px 30px rgba(16, 16, 90, 0.12);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .info-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    }

    .info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(16, 16, 90, 0.2);
    }

    .info-card h3 {
        font-size: 1.3rem;
        color: #2d3748;
        margin-bottom: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-detail {
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .info-detail:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .info-label {
        font-weight: 600;
        color: #4a5568;
        margin-bottom: 0.3rem;
        font-size: 0.9rem;
    }

    .info-value {
        font-weight: 500;
        color: #2d3748;
        font-size: 1rem;
    }

    /* ===== ORDER ITEMS SECTION ===== */
    .order-items {
        background: #fff;
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: 0 8px 30px rgba(16, 16, 90, 0.12);
        margin-bottom: 3rem;
    }

    .order-items h2 {
        font-size: 1.8rem;
        color: #2d3748;
        margin-bottom: 2rem;
        font-weight: 700;
        position: relative;
        padding-bottom: 0.5rem;
    }

    .order-items h2::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 60px;
        height: 3px;
        background: linear-gradient(135deg, #93e2bb 0%, #7dc9a5 100%);
        border-radius: 2px;
    }

    .order-item {
        display: flex;
        gap: 1.5rem;
        margin-bottom: 2rem;
        padding-bottom: 2rem;
        border-bottom: 2px solid #f1f5f9;
        transition: all 0.3s ease;
    }

    .order-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .order-item:hover {
        transform: translateX(10px);
    }

    .item-image {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 15px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        transition: transform 0.3s ease;
    }

    .order-item:hover .item-image {
        transform: scale(1.05);
    }

    .item-details {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .item-name {
        font-size: 1.2rem;
        color: #2d3748;
        margin-bottom: 0.5rem;
        font-weight: 700;
    }

    .item-description {
        color: #718096;
        font-size: 0.9rem;
        margin-bottom: 0.8rem;
        line-height: 1.5;
    }

    .item-meta {
        display: flex;
        gap: 2rem;
        font-size: 0.9rem;
        color: #4a5568;
    }

    .item-price {
        font-weight: 700;
        color: #f98293;
        font-size: 1.1rem;
        margin-left: auto;
        display: flex;
        align-items: center;
    }

    /* ===== ORDER TOTAL ===== */
    .order-total {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 15px;
        padding: 2rem;
        margin-top: 2rem;
        border: 2px solid #e2e8f0;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 1rem;
        font-size: 1.1rem;
        font-weight: 600;
        color: #4a5568;
    }

    .total-row.grand-total {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 3px solid #e2e8f0;
        font-size: 1.4rem;
        font-weight: 800;
        color: #f98293;
    }

    /* ===== ACTION BUTTONS ===== */
    .action-buttons {
        display: flex;
        gap: 1.5rem;
        justify-content: center;
        margin-top: 3rem;
        flex-wrap: wrap;
    }

    .btn {
        padding: 1rem 2.5rem;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 700;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.8rem;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }

    .btn-primary {
        background: linear-gradient(135deg, #93e2bb 0%, #7dc9a5 100%);
        color: #fff;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #7dc9a5 0%, #6bb894 100%);
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(147, 226, 187, 0.4);
    }

    .btn-secondary {
        background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
        color: #fff;
    }

    .btn-secondary:hover {
        background: linear-gradient(135deg, #e06d7f 0%, #d45a6d 100%);
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(249, 130, 147, 0.4);
    }

    .btn-outline {
        background: transparent;
        color: #f98293;
        border: 2px solid #f98293;
    }

    .btn-outline:hover {
        background: #f98293;
        color: #fff;
        transform: translateY(-3px);
    }

    /* ===== FOOTER ===== */
    footer {
        background: linear-gradient(135deg, #42375a 0%, #352a4a 100%);
        color: #fff;
        text-align: center;
        padding: 2.5rem 1rem;
        margin-top: 4rem;
    }

    footer p {
        margin: 0;
        font-weight: 500;
        opacity: 0.9;
    }

    /* ===== RESPONSIVE DESIGN ===== */
    @media (max-width: 768px) {
        .success-hero {
            padding: 8rem 3vw 3rem;
        }

        .success-hero h1 {
            font-size: 2.5rem;
        }

        .success-icon {
            font-size: 4rem;
        }

        .order-details {
            padding: 2rem 3vw;
        }

        .order-info-grid {
            grid-template-columns: 1fr;
        }

        .order-item {
            flex-direction: column;
            text-align: center;
        }

        .item-price {
            margin-left: 0;
            justify-content: center;
        }

        .item-meta {
            justify-content: center;
        }

        .action-buttons {
            flex-direction: column;
            align-items: center;
        }

        .btn {
            width: 100%;
            max-width: 300px;
            justify-content: center;
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
    }

    @media (max-width: 480px) {
        .success-hero h1 {
            font-size: 2rem;
        }

        .order-id {
            font-size: 1.2rem;
            padding: 0.6rem 1.5rem;
        }

        .info-card,
        .order-items {
            padding: 1.5rem;
        }

        .item-image {
            width: 80px;
            height: 80px;
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

    .success-hero,
    .info-card,
    .order-items,
    .action-buttons {
        animation: fadeInUp 0.6s ease;
    }

    .order-item {
        animation: fadeInUp 0.6s ease;
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
    </style>
</head>
<body>

<header>
    <nav>
        <a href="index.php" class="logo">mommycare</a>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="index.php#featured-products">Shop</a></li>
            
        </ul>
        <a href="cart.php" class="cart-link">🛒 View Cart</a>
    </nav>
</header>

<!-- Success Hero Section -->
<section class="success-hero">
    <div class="success-icon">🎉</div>
    <h1>Order Placed Successfully!</h1>
    <p>Thank you for your purchase! Your order has been confirmed and will be shipped soon.</p>
    <div class="order-id">Order #<?php echo $order_id; ?></div>
</section>

<!-- Order Details Section -->
<section class="order-details">
    <div class="order-info-grid">
        <!-- Delivery Information -->
        <div class="info-card">
            <h3>🚚 Delivery Information</h3>
            <div class="info-detail">
                <div class="info-label">Full Name</div>
                <div class="info-value"><?php echo htmlspecialchars($order_details['full_name']); ?></div>
            </div>
            <div class="info-detail">
                <div class="info-label">Email</div>
                <div class="info-value"><?php echo htmlspecialchars($order_details['email']); ?></div>
            </div>
            <div class="info-detail">
                <div class="info-label">Phone</div>
                <div class="info-value"><?php echo htmlspecialchars($order_details['phone']); ?></div>
            </div>
            <div class="info-detail">
                <div class="info-label">Delivery Address</div>
                <div class="info-value"><?php echo nl2br(htmlspecialchars($order_details['address'])); ?></div>
            </div>
        </div>

        <!-- Order Information -->
        <div class="info-card">
            <h3>📦 Order Information</h3>
            <div class="info-detail">
                <div class="info-label">Order Date</div>
                <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($order_details['created_at'])); ?></div>
            </div>
            <div class="info-detail">
                <div class="info-label">Order Status</div>
                <div class="info-value" style="color: #48bb78; font-weight: 700;">✅ <?php echo ucfirst($order_details['order_status']); ?></div>
            </div>
            <div class="info-detail">
                <div class="info-label">Payment Method</div>
                <div class="info-value">💳 <?php echo ucfirst($order_details['payment_method']); ?></div>
            </div>
            <div class="info-detail">
                <div class="info-label">Payment ID</div>
                <div class="info-value" style="font-family: monospace; font-size: 0.9rem;"><?php echo htmlspecialchars($order_details['payment_id']); ?></div>
            </div>
        </div>
    </div>

    <!-- Order Items -->
    <div class="order-items">
        <h2>📋 Order Items</h2>
        <?php foreach($order_items as $item): ?>
        <div class="order-item">
            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="item-image">
            <div class="item-details">
                <h3 class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></h3>
                <?php if(!empty($item['description'])): ?>
                    <p class="item-description"><?php echo htmlspecialchars($item['description']); ?></p>
                <?php endif; ?>
                <div class="item-meta">
                    <span>Qty: <?php echo $item['quantity']; ?></span>
                    <span>Price: ₹<?php echo number_format($item['price'], 2); ?></span>
                </div>
            </div>
            <div class="item-price">
                ₹<?php echo number_format($item['total_amount'], 2); ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Order Total -->
        <div class="order-total">
            <div class="total-row">
                <span>Items Total:</span>
                <span>₹<?php echo number_format($order_total, 2); ?></span>
            </div>
            <div class="total-row">
                <span>Shipping:</span>
                <span style="color: #48bb78;">FREE</span>
            </div>
            <div class="total-row grand-total">
                <span>Grand Total:</span>
                <span>₹<?php echo number_format($order_total, 2); ?></span>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="index.php" class="btn btn-primary">
            🛍️ Continue Shopping
        </a>
        <a href="orders.php" class="btn btn-secondary">
            📦 View All Orders
        </a>
        <a href="index.php#featured-products" class="btn btn-outline">
            🔍 Browse More Products
        </a>
    </div>
</section>

<footer>
    <p>&copy; 2025 mommycare. Thank you for shopping with us! 💝</p>
</footer>

<script>
// Add some interactive effects
document.addEventListener('DOMContentLoaded', function() {
    // Add staggered animation to order items
    const orderItems = document.querySelectorAll('.order-item');
    orderItems.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
    });

    // Add confetti effect on page load
    setTimeout(() => {
        createConfetti();
    }, 1000);

    function createConfetti() {
        const colors = ['#f98293', '#93e2bb', '#bcd9ea', '#ffd166'];
        for (let i = 0; i < 50; i++) {
            const confetti = document.createElement('div');
            confetti.style.cssText = `
                position: fixed;
                width: 10px;
                height: 10px;
                background: ${colors[Math.floor(Math.random() * colors.length)]};
                top: -20px;
                left: ${Math.random() * 100}vw;
                border-radius: 50%;
                animation: fall linear forwards;
                animation-duration: ${Math.random() * 3 + 2}s;
                z-index: 9999;
            `;
            document.body.appendChild(confetti);

            // Remove confetti after animation
            setTimeout(() => {
                confetti.remove();
            }, 5000);
        }
    }

    // Add CSS for confetti animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fall {
            to {
                transform: translateY(100vh) rotate(${Math.random() * 360}deg);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
});
</script>

</body>
</html>
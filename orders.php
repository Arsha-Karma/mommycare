<?php
session_start();
include('db.php');

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

// Fetch all orders for the user with product details
$orders_sql = "SELECT o.*, p.name as product_name, p.image, p.price, p.description 
               FROM orders o 
               JOIN products p ON o.product_id = p.id 
               WHERE o.user_id = ? 
               ORDER BY o.created_at DESC";
$orders_stmt = $db->conn->prepare($orders_sql);
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

// Group orders by order_id
$orders = [];
while($row = $orders_result->fetch_assoc()){
    $order_id = $row['id'];
    if(!isset($orders[$order_id])){
        $orders[$order_id] = [
            'order_info' => $row,
            'items' => []
        ];
    }
    $orders[$order_id]['items'][] = $row;
}
$orders_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders - mommycare</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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

    /* ===== ORDERS CONTAINER ===== */
    .orders-container {
        padding: 0 5vw 5rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    /* ===== NO ORDERS MESSAGE ===== */
    .no-orders {
        text-align: center;
        padding: 4rem 2rem;
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 8px 30px rgba(16, 16, 90, 0.12);
    }

    .no-orders-icon {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        opacity: 0.5;
    }

    .no-orders h2 {
        color: #666;
        margin-bottom: 1rem;
        font-size: 1.8rem;
    }

    .no-orders p {
        color: #888;
        margin-bottom: 2rem;
    }

    /* ===== ORDER CARD ===== */
    .order-card {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 8px 30px rgba(16, 16, 90, 0.12);
        margin-bottom: 2rem;
        overflow: hidden;
        transition: all 0.3s ease;
        position: relative;
    }

    .order-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(16, 16, 90, 0.2);
    }

    .order-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    }

    .order-header {
        padding: 1.5rem 2rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .order-meta {
        display: flex;
        gap: 2rem;
        flex-wrap: wrap;
    }

    .order-id {
        font-size: 1.3rem;
        font-weight: 700;
        color: #f98293;
    }

    .order-date {
        color: #666;
        font-weight: 500;
    }

    .order-status {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
        margin-left: auto;
    }

    .status-completed {
        background: #c6f6d5;
        color: #22543d;
    }

    .status-pending {
        background: #fed7d7;
        color: #742a2a;
    }

    .status-processing {
        background: #bee3f8;
        color: #1a365d;
    }

    .order-actions {
        display: flex;
        gap: 1rem;
        margin-left: auto;
    }

    /* ===== BUTTON STYLES ===== */
    .btn {
        padding: 0.7rem 1.5rem;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .btn-primary {
        background: linear-gradient(135deg, #93e2bb 0%, #7dc9a5 100%);
        color: #fff;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #7dc9a5 0%, #6bb894 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(147, 226, 187, 0.4);
    }

    .btn-secondary {
        background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
        color: #fff;
    }

    .btn-secondary:hover {
        background: linear-gradient(135deg, #e06d7f 0%, #d45a6d 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(249, 130, 147, 0.4);
    }

    .btn-outline {
        background: transparent;
        color: #f98293;
        border: 2px solid #f98293;
    }

    .btn-outline:hover {
        background: #f98293;
        color: #fff;
        transform: translateY(-2px);
    }

    /* ===== ORDER ITEMS ===== */
    .order-items {
        padding: 2rem;
    }

    .order-item {
        display: flex;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .order-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .item-image {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .item-details {
        flex: 1;
    }

    .item-name {
        font-size: 1.1rem;
        color: #2d3748;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }

    .item-meta {
        display: flex;
        gap: 1.5rem;
        color: #666;
        font-size: 0.9rem;
    }

    .item-price {
        font-weight: 700;
        color: #f98293;
        font-size: 1.1rem;
        min-width: 100px;
        text-align: right;
    }

    /* ===== ORDER SUMMARY ===== */
    .order-summary {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        padding: 1.5rem 2rem;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .summary-total {
        font-size: 1.3rem;
        font-weight: 700;
        color: #f98293;
    }

    /* ===== RECEIPT MODAL ===== */
    .receipt-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 10000;
        backdrop-filter: blur(5px);
    }

    .receipt-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #fff;
        border-radius: 20px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .receipt-header {
        padding: 2rem;
        background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
        color: #fff;
        text-align: center;
        border-radius: 20px 20px 0 0;
    }

    .receipt-header h2 {
        font-size: 1.8rem;
        margin-bottom: 0.5rem;
    }

    .receipt-body {
        padding: 2rem;
    }

    .receipt-actions {
        padding: 1.5rem 2rem;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        gap: 1rem;
        justify-content: center;
        border-radius: 0 0 20px 20px;
    }

    .close-modal {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: #fff;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        font-size: 1.2rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .close-modal:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.1);
    }

    /* ===== PRINT STYLES ===== */
    @media print {
        body * {
            visibility: hidden;
        }
        .receipt-content,
        .receipt-content * {
            visibility: visible;
        }
        .receipt-content {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            max-width: 100%;
            box-shadow: none;
            border-radius: 0;
        }
        .receipt-actions {
            display: none;
        }
    }

    /* ===== RESPONSIVE DESIGN ===== */
    @media (max-width: 768px) {
        .hero-section {
            padding: 8rem 3vw 2rem;
        }

        .hero-section h1 {
            font-size: 2.5rem;
        }

        .order-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .order-actions {
            margin-left: 0;
            width: 100%;
            justify-content: center;
        }

        .order-meta {
            gap: 1rem;
        }

        .order-item {
            flex-direction: column;
            text-align: center;
        }

        .item-price {
            text-align: center;
        }

        .receipt-content {
            width: 95%;
            margin: 2.5% auto;
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
        .hero-section h1 {
            font-size: 2rem;
        }

        .order-items {
            padding: 1.5rem;
        }

        .item-image {
            width: 60px;
            height: 60px;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            font-size: 0.8rem;
        }

        .receipt-body {
            padding: 1.5rem;
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

    .order-card {
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
            <li><a href="#">Reviews</a></li>
        </ul>
        <a href="cart.php" class="cart-link">🛒 View Cart</a>
    </nav>
</header>

<!-- Hero Section -->
<section class="hero-section">
    <h1>My Orders</h1>
    <p>Track and manage your purchases</p>
</section>

<!-- Orders Container -->
<section class="orders-container">
    <?php if(empty($orders)): ?>
        <div class="no-orders">
            <div class="no-orders-icon">📦</div>
            <h2>No Orders Yet</h2>
            <p>You haven't placed any orders yet. Start shopping to see your orders here!</p>
            <a href="index.php#featured-products" class="btn btn-primary">Start Shopping</a>
        </div>
    <?php else: ?>
        <?php foreach($orders as $order_id => $order_data): 
            $first_item = $order_data['items'][0];
            $order_total = array_sum(array_column($order_data['items'], 'total_amount'));
        ?>
            <div class="order-card" id="order-<?php echo $order_id; ?>">
                <!-- Order Header -->
                <div class="order-header">
                    <div class="order-meta">
                        <div class="order-id">Order #<?php echo $order_id; ?></div>
                        <div class="order-date"><?php echo date('F j, Y g:i A', strtotime($first_item['created_at'])); ?></div>
                    </div>
                    <div class="order-status status-<?php echo $first_item['order_status']; ?>">
                        <?php echo ucfirst($first_item['order_status']); ?>
                    </div>
                    <div class="order-actions">
                        <button class="btn btn-outline view-receipt" data-order-id="<?php echo $order_id; ?>">
                            👁️ View Receipt
                        </button>
                       
                    </div>
                </div>

                <!-- Order Items -->
                <div class="order-items">
                    <?php foreach($order_data['items'] as $item): ?>
                        <div class="order-item">
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="item-image">
                            <div class="item-details">
                                <h3 class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></h3>
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
                </div>

                <!-- Order Summary -->
                <div class="order-summary">
                    <div class="order-address">
                        <strong>Delivery to:</strong> <?php echo htmlspecialchars($first_item['full_name']); ?>, <?php echo htmlspecialchars($first_item['address']); ?>
                    </div>
                    <div class="summary-total">
                        Total: ₹<?php echo number_format($order_total, 2); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<!-- Receipt Modal -->
<div class="receipt-modal" id="receiptModal">
    <div class="receipt-content" id="receiptContent">
        <button class="close-modal" onclick="closeReceiptModal()">×</button>
        <div class="receipt-header">
            <h2>mommycare</h2>
            <p>Order Receipt</p>
        </div>
        <div class="receipt-body" id="receiptBody">
            <!-- Receipt content will be loaded here dynamically -->
        </div>
        <div class="receipt-actions">
            <button class="btn btn-secondary" onclick="printReceipt()">🖨️ Print Receipt</button>
        
            <button class="btn btn-outline" onclick="closeReceiptModal()">Close</button>
        </div>
    </div>
</div>

<footer style="background: linear-gradient(135deg, #42375a 0%, #352a4a 100%); color: #fff; text-align: center; padding: 2rem 1rem; margin-top: 4rem;">
    <p>&copy; 2025 mommycare. Your trusted partner for baby care. 💝</p>
</footer>

<script>
// Receipt data storage
let currentReceiptData = null;

// View Receipt
document.querySelectorAll('.view-receipt').forEach(button => {
    button.addEventListener('click', function() {
        const orderId = this.getAttribute('data-order-id');
        showReceipt(orderId);
    });
});


// Show receipt in modal
function showReceipt(orderId) {
    const orderCard = document.getElementById(`order-${orderId}`);
    if (!orderCard) return;

    // Get order data from the page
    const orderData = {
        orderId: orderId,
        orderDate: orderCard.querySelector('.order-date').textContent,
        customerName: orderCard.querySelector('.order-address').textContent.split(': ')[1].split(',')[0],
        address: orderCard.querySelector('.order-address').textContent.split(', ').slice(1).join(', '),
        items: [],
        total: orderCard.querySelector('.summary-total').textContent.split('₹')[1]
    };

    // Get items data
    orderCard.querySelectorAll('.order-item').forEach(item => {
        orderData.items.push({
            name: item.querySelector('.item-name').textContent,
            quantity: item.querySelector('.item-meta span:first-child').textContent.split(': ')[1],
            price: item.querySelector('.item-meta span:last-child').textContent.split('₹')[1],
            total: item.querySelector('.item-price').textContent.split('₹')[1]
        });
    });

    currentReceiptData = orderData;
    displayReceipt(orderData);
    document.getElementById('receiptModal').style.display = 'block';
}

// Display receipt in modal
function displayReceipt(orderData) {
    const receiptBody = document.getElementById('receiptBody');
    
    let receiptHTML = `
        <div style="margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                <div>
                    <strong>Order ID:</strong> #${orderData.orderId}<br>
                    <strong>Order Date:</strong> ${orderData.orderDate}
                </div>
                <div style="text-align: right;">
                    <strong>Customer:</strong> ${orderData.customerName}<br>
                    <strong>Address:</strong> ${orderData.address}
                </div>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 2rem;">
            <thead>
                <tr style="background: #f8fafc;">
                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #e2e8f0;">Product</th>
                    <th style="padding: 1rem; text-align: center; border-bottom: 2px solid #e2e8f0;">Qty</th>
                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid #e2e8f0;">Price</th>
                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid #e2e8f0;">Total</th>
                </tr>
            </thead>
            <tbody>
    `;

    orderData.items.forEach(item => {
        receiptHTML += `
            <tr>
                <td style="padding: 1rem; border-bottom: 1px solid #f1f5f9;">${item.name}</td>
                <td style="padding: 1rem; text-align: center; border-bottom: 1px solid #f1f5f9;">${item.quantity}</td>
                <td style="padding: 1rem; text-align: right; border-bottom: 1px solid #f1f5f9;">₹${parseFloat(item.price).toFixed(2)}</td>
                <td style="padding: 1rem; text-align: right; border-bottom: 1px solid #f1f5f9;">₹${parseFloat(item.total).toFixed(2)}</td>
            </tr>
        `;
    });

    receiptHTML += `
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="padding: 1rem; text-align: right; font-weight: bold; border-top: 2px solid #e2e8f0;">Grand Total:</td>
                    <td style="padding: 1rem; text-align: right; font-weight: bold; font-size: 1.2rem; color: #f98293; border-top: 2px solid #e2e8f0;">₹${parseFloat(orderData.total).toFixed(2)}</td>
                </tr>
            </tfoot>
        </table>

        <div style="text-align: center; color: #666; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e2e8f0;">
            <p>Thank you for shopping with mommycare! 💝</p>
            <p>For any queries, contact us at support@mommycare.com</p>
        </div>
    `;

    receiptBody.innerHTML = receiptHTML;
}

// Close receipt modal
function closeReceiptModal() {
    document.getElementById('receiptModal').style.display = 'none';
}

// Print receipt
function printReceipt() {
    window.print();
}



// Direct download without opening modal
function downloadReceiptPDF(orderId) {
    const orderCard = document.getElementById(`order-${orderId}`);
    if (!orderCard) return;

    // Get order data
    const orderData = {
        orderId: orderId,
        orderDate: orderCard.querySelector('.order-date').textContent,
        customerName: orderCard.querySelector('.order-address').textContent.split(': ')[1].split(',')[0],
        address: orderCard.querySelector('.order-address').textContent.split(', ').slice(1).join(', '),
        items: [],
        total: orderCard.querySelector('.summary-total').textContent.split('₹')[1]
    };

    // Get items data
    orderCard.querySelectorAll('.order-item').forEach(item => {
        orderData.items.push({
            name: item.querySelector('.item-name').textContent,
            quantity: item.querySelector('.item-meta span:first-child').textContent.split(': ')[1],
            price: item.querySelector('.item-meta span:last-child').textContent.split('₹')[1],
            total: item.querySelector('.item-price').textContent.split('₹')[1]
        });
    });

    // Create temporary receipt element
    const tempReceipt = document.createElement('div');
    tempReceipt.style.padding = '20px';
    tempReceipt.style.background = '#fff';
    
    let receiptHTML = `
        <div style="text-align: center; margin-bottom: 2rem; background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%); color: white; padding: 2rem; border-radius: 10px;">
            <h1 style="margin: 0; font-size: 2rem;">mommycare</h1>
            <p style="margin: 0.5rem 0 0 0; font-size: 1.2rem;">Order Receipt</p>
        </div>
        
        <div style="margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                <div>
                    <strong>Order ID:</strong> #${orderData.orderId}<br>
                    <strong>Order Date:</strong> ${orderData.orderDate}
                </div>
                <div style="text-align: right;">
                    <strong>Customer:</strong> ${orderData.customerName}<br>
                    <strong>Address:</strong> ${orderData.address}
                </div>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 2rem;">
            <thead>
                <tr style="background: #f8fafc;">
                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #e2e8f0;">Product</th>
                    <th style="padding: 1rem; text-align: center; border-bottom: 2px solid #e2e8f0;">Qty</th>
                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid #e2e8f0;">Price</th>
                    <th style="padding: 1rem; text-align: right; border-bottom: 2px solid #e2e8f0;">Total</th>
                </tr>
            </thead>
            <tbody>
    `;

    orderData.items.forEach(item => {
        receiptHTML += `
            <tr>
                <td style="padding: 1rem; border-bottom: 1px solid #f1f5f9;">${item.name}</td>
                <td style="padding: 1rem; text-align: center; border-bottom: 1px solid #f1f5f9;">${item.quantity}</td>
                <td style="padding: 1rem; text-align: right; border-bottom: 1px solid #f1f5f9;">₹${parseFloat(item.price).toFixed(2)}</td>
                <td style="padding: 1rem; text-align: right; border-bottom: 1px solid #f1f5f9;">₹${parseFloat(item.total).toFixed(2)}</td>
            </tr>
        `;
    });

    receiptHTML += `
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="padding: 1rem; text-align: right; font-weight: bold; border-top: 2px solid #e2e8f0;">Grand Total:</td>
                    <td style="padding: 1rem; text-align: right; font-weight: bold; font-size: 1.2rem; color: #f98293; border-top: 2px solid #e2e8f0;">₹${parseFloat(orderData.total).toFixed(2)}</td>
                </tr>
            </tfoot>
        </table>

        <div style="text-align: center; color: #666; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e2e8f0;">
            <p>Thank you for shopping with mommycare! 💝</p>
            <p>For any queries, contact us at support@mommycare.com</p>
        </div>
    `;

    tempReceipt.innerHTML = receiptHTML;

    // Generate PDF
    const opt = {
        margin: [10, 10, 10, 10],
        filename: `mommycare-order-${orderId}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(tempReceipt).save();
}

// Close modal when clicking outside
document.getElementById('receiptModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReceiptModal();
    }
});

// Add keyboard event to close modal with ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReceiptModal();
    }
});
</script>

</body>
</html>
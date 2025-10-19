<?php
session_start();
include('db.php');

$db = new Database();
$user_id = $_SESSION['user_id'] ?? null;

if(!$user_id){
    header("Location: login.php");
    exit;
}

// Get order ID from URL
$order_id = $_GET['order_id'] ?? null;

if(!$order_id){
    header("Location: index.php");
    exit;
}

// Fetch order details
$order_stmt = $db->conn->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
$order_stmt->bind_param("ii", $order_id, $user_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$order = $order_result->fetch_assoc();
$order_stmt->close();

if(!$order){
    header("Location: index.php");
    exit;
}

// Fetch order items
$items_stmt = $db->conn->prepare("SELECT oi.*, p.name, p.image FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$order_items = [];
while($row = $items_result->fetch_assoc()){
    $order_items[] = $row;
}
$items_stmt->close();

// Calculate total items
$total_items = 0;
foreach($order_items as $item){
    $total_items += $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order Success - mommycare</title>
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

.success-container {
    max-width: 900px;
    margin: 6rem auto 3rem;
    padding: 0 2rem;
}

.success-header {
    text-align: center;
    padding: 3rem 2rem;
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    border-radius: 20px 20px 0 0;
    color: white;
}

.success-icon {
    width: 80px;
    height: 80px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 3rem;
    animation: scaleIn 0.5s ease;
}

@keyframes scaleIn {
    from {
        transform: scale(0);
    }
    to {
        transform: scale(1);
    }
}

.success-header h1 {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.success-header p {
    font-size: 1.1rem;
    opacity: 0.95;
}

.order-details-card {
    background: white;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    padding: 2.5rem;
}

.order-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 2rem;
    margin-bottom: 2.5rem;
    padding-bottom: 2rem;
    border-bottom: 2px solid #e2e8f0;
}

.info-block h3 {
    font-size: 0.85rem;
    color: #718096;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 0.5rem;
}

.info-block p {
    font-size: 1.1rem;
    color: #2d3748;
    font-weight: 600;
}

.order-id {
    color: #f98293;
    font-size: 1.3rem;
}

.status-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: capitalize;
}

.status-confirmed {
    background: #c6f6d5;
    color: #22543d;
}

.status-pending {
    background: #feebc8;
    color: #744210;
}

.order-items-section h2 {
    font-size: 1.4rem;
    color: #2d3748;
    margin-bottom: 1.5rem;
}

.order-item {
    display: flex;
    gap: 1.5rem;
    padding: 1.5rem;
    background: #f7fafc;
    border-radius: 12px;
    margin-bottom: 1rem;
}

.order-item img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 10px;
}

.item-details {
    flex: 1;
}

.item-details h4 {
    font-size: 1.1rem;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.item-details .item-meta {
    color: #718096;
    font-size: 0.95rem;
}

.item-price {
    text-align: right;
}

.item-price .price {
    font-size: 1.2rem;
    color: #f98293;
    font-weight: 600;
}

.item-price .quantity {
    font-size: 0.9rem;
    color: #718096;
}

.order-summary {
    background: #f7fafc;
    padding: 1.5rem;
    border-radius: 12px;
    margin-top: 2rem;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    color: #4a5568;
}

.summary-row.total {
    border-top: 2px solid #cbd5e0;
    margin-top: 0.5rem;
    padding-top: 1rem;
    font-size: 1.3rem;
    font-weight: 600;
    color: #2d3748;
}

.action-buttons {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-top: 2.5rem;
}

.btn {
    padding: 1rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: all 0.3s ease;
    display: inline-block;
}

.btn-primary {
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(249, 130, 147, 0.4);
}

.btn-secondary {
    background: white;
    color: #2d3748;
    border: 2px solid #e2e8f0;
}

.btn-secondary:hover {
    border-color: #f98293;
    color: #f98293;
}

.delivery-info {
    background: #fff5f7;
    padding: 1.5rem;
    border-radius: 12px;
    margin-top: 2rem;
    border-left: 4px solid #f98293;
}

.delivery-info h3 {
    font-size: 1.1rem;
    color: #2d3748;
    margin-bottom: 0.75rem;
}

.delivery-info p {
    color: #4a5568;
    line-height: 1.6;
}

/* Print Styles */
@media print {
    body {
        background: white;
    }
    
    header, .action-buttons {
        display: none;
    }
    
    .success-container {
        margin: 0;
        padding: 0;
    }
    
    .order-details-card {
        box-shadow: none;
        border: 1px solid #e2e8f0;
    }
}

@media (max-width: 768px) {
    .order-info-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .action-buttons {
        grid-template-columns: 1fr;
    }
    
    .order-item {
        flex-direction: column;
    }
    
    .item-price {
        text-align: left;
    }
}
</style>
</head>
<body>

<header class="no-print">
    <nav>
        <div class="logo">mommycare</div>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="index.php#featured-products">Shop</a></li>
            <li><a href="#">Orders</a></li>
            <li><a href="#">Contact</a></li>
        </ul>
    </nav>
</header>

<div class="success-container">
    <div class="success-header">
        <div class="success-icon">✓</div>
        <h1>Order Placed Successfully!</h1>
        <p>Thank you for your purchase. Your order has been confirmed.</p>
    </div>
    
    <div class="order-details-card" id="orderInvoice">
        <div class="order-info-grid">
            <div class="info-block">
                <h3>Order ID</h3>
                <p class="order-id">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></p>
            </div>
            
            <div class="info-block">
                <h3>Order Date</h3>
                <p><?php echo date('d M Y, g:i A', strtotime($order['created_at'])); ?></p>
            </div>
            
            <div class="info-block">
                <h3>Payment Method</h3>
                <p><?php echo $order['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Online Payment'; ?></p>
            </div>
            
            <div class="info-block">
                <h3>Order Status</h3>
                <p>
                    <span class="status-badge status-<?php echo $order['order_status']; ?>">
                        <?php echo ucfirst($order['order_status']); ?>
                    </span>
                </p>
            </div>
        </div>
        
        <div class="delivery-info">
            <h3>📍 Delivery Address</h3>
            <p><strong><?php echo htmlspecialchars($order['full_name']); ?></strong></p>
            <p><?php echo nl2br(htmlspecialchars($order['address'])); ?></p>
            <p>Phone: <?php echo htmlspecialchars($order['phone']); ?></p>
            <p>Email: <?php echo htmlspecialchars($order['email']); ?></p>
        </div>
        
        <div class="order-items-section">
            <h2>Order Items (<?php echo $total_items; ?> items)</h2>
            
            <?php foreach($order_items as $item): ?>
            <div class="order-item">
                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                <div class="item-details">
                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                    <p class="item-meta">Quantity: <?php echo $item['quantity']; ?> × ₹<?php echo number_format($item['price'], 2); ?></p>
                </div>
                <div class="item-price">
                    <div class="price">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="order-summary">
            <div class="summary-row">
                <span>Subtotal (<?php echo $total_items; ?> items)</span>
                <span>₹<?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
            <div class="summary-row">
                <span>Shipping</span>
                <span style="color: #48bb78; font-weight: 600;">FREE</span>
            </div>
            <div class="summary-row">
                <span>Taxes</span>
                <span>Included</span>
            </div>
            <div class="summary-row total">
                <span>Total Amount</span>
                <span>₹<?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
        </div>
        
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-secondary">🖨️ Print Order</button>
            <a href="download_invoice.php?order_id=<?php echo $order['id']; ?>" target="_blank" class="btn btn-secondary">📥 Download Invoice</a>
            <a href="index.php" class="btn btn-primary">🏠 Continue Shopping</a>
        </div>
    </div>
</div>

<script>
function downloadInvoice() {
    // Create a printable version
    const printWindow = window.open('', '', 'width=800,height=600');
    const invoiceContent = document.getElementById('orderInvoice').innerHTML;
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Invoice - Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; padding: 20px; }
                .invoice-header { text-align: center; margin-bottom: 30px; }
                .invoice-header h1 { color: #f98293; font-size: 2rem; }
                .order-info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
                .info-block h3 { font-size: 0.85rem; color: #666; margin-bottom: 5px; }
                .info-block p { font-size: 1.1rem; color: #333; font-weight: 600; }
                .delivery-info { background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 20px 0; }
                .order-item { display: flex; gap: 15px; padding: 15px; background: #f9f9f9; margin-bottom: 10px; }
                .order-summary { background: #f5f5f5; padding: 15px; margin-top: 20px; }
                .summary-row { display: flex; justify-content: space-between; padding: 8px 0; }
                .summary-row.total { border-top: 2px solid #333; margin-top: 10px; padding-top: 10px; font-weight: bold; font-size: 1.2rem; }
            </style>
        </head>
        <body>
            <div class="invoice-header">
                <h1>mommycare</h1>
                <p>Order Invoice</p>
            </div>
            ${invoiceContent}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}
</script>

</body>
</html>
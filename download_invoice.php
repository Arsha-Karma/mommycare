<?php
session_start();
include('db.php');

$db = new Database();
$user_id = $_SESSION['user_id'] ?? null;

if(!$user_id){
    header("Location: login.php");
    exit;
}

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

$total_items = 0;
foreach($order_items as $item){
    $total_items += $item['quantity'];
}

// Set headers for PDF download
header('Content-Type: text/html');
header('Content-Disposition: inline; filename="invoice_' . str_pad($order['id'], 6, '0', STR_PAD_LEFT) . '.html"');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice - Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            background: white;
            color: #333;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .invoice-header {
            background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .invoice-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }
        
        .invoice-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .invoice-body {
            padding: 40px;
        }
        
        .invoice-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .meta-block h3 {
            font-size: 0.85rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .meta-block p {
            font-size: 1.1rem;
            color: #2d3748;
            font-weight: 600;
        }
        
        .order-id-large {
            color: #f98293;
            font-size: 1.5rem;
        }
        
        .delivery-section {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #f98293;
        }
        
        .delivery-section h3 {
            font-size: 1.1rem;
            color: #2d3748;
            margin-bottom: 15px;
        }
        
        .delivery-section p {
            color: #4a5568;
            line-height: 1.8;
            margin-bottom: 5px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .items-table thead {
            background: #2d3748;
            color: white;
        }
        
        .items-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .items-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .items-table tbody tr:hover {
            background: #f7fafc;
        }
        
        .item-name {
            font-weight: 600;
            color: #2d3748;
        }
        
        .text-right {
            text-align: right;
        }
        
        .summary-table {
            width: 100%;
            max-width: 400px;
            margin-left: auto;
        }
        
        .summary-table tr {
            border-bottom: 1px solid #e2e8f0;
        }
        
        .summary-table td {
            padding: 12px 0;
            color: #4a5568;
        }
        
        .summary-table .total-row {
            border-top: 3px solid #2d3748;
            font-size: 1.3rem;
            font-weight: bold;
            color: #2d3748;
        }
        
        .summary-table .total-row td {
            padding: 20px 0 10px 0;
        }
        
        .free-shipping {
            color: #48bb78;
            font-weight: 600;
        }
        
        .invoice-footer {
            background: #f7fafc;
            padding: 30px;
            text-align: center;
            border-top: 2px solid #e2e8f0;
        }
        
        .invoice-footer p {
            color: #718096;
            line-height: 1.8;
            margin-bottom: 10px;
        }
        
        .invoice-footer .company-name {
            color: #f98293;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
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
        
        @media print {
            body {
                padding: 0;
            }
            
            .invoice-container {
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <h1>mommycare</h1>
            <p>Tax Invoice / Order Confirmation</p>
        </div>
        
        <div class="invoice-body">
            <div class="invoice-meta">
                <div class="meta-block">
                    <h3>Invoice Number</h3>
                    <p class="order-id-large">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></p>
                </div>
                
                <div class="meta-block">
                    <h3>Invoice Date</h3>
                    <p><?php echo date('d M Y, g:i A', strtotime($order['created_at'])); ?></p>
                </div>
                
                <div class="meta-block">
                    <h3>Payment Method</h3>
                    <p><?php echo $order['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Online Payment'; ?></p>
                    <?php if($order['payment_id']): ?>
                    <p style="font-size: 0.85rem; color: #718096;">Payment ID: <?php echo htmlspecialchars($order['payment_id']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="meta-block">
                    <h3>Order Status</h3>
                    <p>
                        <span class="status-badge status-<?php echo $order['order_status']; ?>">
                            <?php echo ucfirst($order['order_status']); ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <div class="delivery-section">
                <h3>📍 Billing & Delivery Address</h3>
                <p><strong><?php echo htmlspecialchars($order['full_name']); ?></strong></p>
                <p><?php echo nl2br(htmlspecialchars($order['address'])); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
            </div>
            
            <h3 style="margin-bottom: 15px; color: #2d3748;">Order Details</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">Product Name</th>
                        <th style="width: 15%; text-align: center;">Quantity</th>
                        <th style="width: 17%; text-align: right;">Unit Price</th>
                        <th style="width: 18%; text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($order_items as $item): ?>
                    <tr>
                        <td class="item-name"><?php echo htmlspecialchars($item['name']); ?></td>
                        <td style="text-align: center;"><?php echo $item['quantity']; ?></td>
                        <td class="text-right">₹<?php echo number_format($item['price'], 2); ?></td>
                        <td class="text-right">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <table class="summary-table">
                <tr>
                    <td>Subtotal (<?php echo $total_items; ?> items)</td>
                    <td class="text-right">₹<?php echo number_format($order['total_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td>Shipping Charges</td>
                    <td class="text-right free-shipping">FREE</td>
                </tr>
                <tr>
                    <td>Tax (Included)</td>
                    <td class="text-right">₹0.00</td>
                </tr>
                <tr class="total-row">
                    <td><strong>Grand Total</strong></td>
                    <td class="text-right"><strong>₹<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                </tr>
            </table>
        </div>
        
        <div class="invoice-footer">
            <p class="company-name">mommycare</p>
            <p>Thank you for shopping with us!</p>
            <p>For any queries, please contact us at support@mommycare.com</p>
            <p style="margin-top: 20px; font-size: 0.85rem;">
                This is a computer-generated invoice and does not require a signature.
            </p>
        </div>
    </div>
    
    <script>
        // Auto-print when page loads
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
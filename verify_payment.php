<?php
require_once 'config.php'; 
include('db.php');

// Razorpay credentials
define('RAZORPAY_KEY_ID', 'rzp_test_LFEA5QeDc3uh7A');
define('RAZORPAY_KEY_SECRET', 'UcSEiMzamwDuhxLKkpPz1VUj');

$db = new Database();
$user_id = $_SESSION['user_id'] ?? null;

// Debug session data
error_log("Session Data: " . print_r($_SESSION, true));
error_log("User ID: " . $user_id);
error_log("Pending Order: " . (isset($_SESSION['pending_order']) ? 'Set' : 'Not Set'));

if(!$user_id || !isset($_SESSION['pending_order'])){
    error_log("Error: User ID or pending order missing");
    echo "<script>alert('Session expired or no pending order! Please try again.'); window.location.href='cart.php';</script>";
    exit;
}

// Get payment ID from POST
$payment_id = $_POST['razorpay_payment_id'] ?? null;

if(!$payment_id){
    echo "<script>alert('Payment ID not found!'); window.location.href='checkout.php';</script>";
    exit;
}

// Get order data from session
$order_data = $_SESSION['pending_order'];

// For testing, simulate successful payment
$payment_verified = true;

if($payment_verified){
    // Start transaction
    $db->conn->begin_transaction();
    
    try {
        $full_name = $order_data['full_name'];
        $email = $order_data['email'];
        $phone = $order_data['phone'];
        $address = $order_data['address'];
        $payment_method = 'online';
        $grand_total = $order_data['grand_total'];
        
        error_log("Processing order for: $full_name, Total: $grand_total");
        
        // STEP 1: Get all active cart items for this user
        $cart_items_stmt = $db->conn->prepare("SELECT c.*, p.price, p.name FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? AND c.status = 'active'");
        $cart_items_stmt->bind_param("i", $user_id);
        $cart_items_stmt->execute();
        $cart_items_result = $cart_items_stmt->get_result();
        $cart_items = [];
        
        while($row = $cart_items_result->fetch_assoc()){
            $cart_items[] = $row;
            error_log("Cart Item - Product ID: {$row['product_id']}, Qty: {$row['quantity']}");
        }
        $cart_items_stmt->close();
        
        if(empty($cart_items)){
            throw new Exception("No active cart items found for user $user_id!");
        }
        
        $last_order_id = null;
        
        // STEP 2: Insert EACH cart item into orders table
        foreach($cart_items as $cart_item){
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            $price = $cart_item['price'];
            $item_total = $price * $quantity;
            
            error_log("Inserting into orders - Product: $product_id, Qty: $quantity, Total: $item_total");
            
            // Insert into orders table
            $order_stmt = $db->conn->prepare("INSERT INTO orders (user_id, product_id, quantity, total_amount, full_name, email, phone, address, payment_method, payment_id, order_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())");
            
            if(!$order_stmt){
                throw new Exception("Prepare failed for order: " . $db->conn->error);
            }
            
            $order_stmt->bind_param("iiidssssss", $user_id, $product_id, $quantity, $item_total, $full_name, $email, $phone, $address, $payment_method, $payment_id);
            
            if(!$order_stmt->execute()){
                throw new Exception("Execute failed for order: " . $order_stmt->error);
            }
            
            $last_order_id = $db->conn->insert_id;
            $order_stmt->close();
            
            error_log("Successfully created order ID: $last_order_id for product $product_id");
        }
        
        // STEP 3: Update cart status to 'completed'
        error_log("Updating cart status to 'completed' for user $user_id");
        
        $update_cart_stmt = $db->conn->prepare("UPDATE cart SET status = 'completed' WHERE user_id = ? AND status = 'active'");
        
        if(!$update_cart_stmt){
            throw new Exception("Prepare failed for cart update: " . $db->conn->error);
        }
        
        $update_cart_stmt->bind_param("i", $user_id);
        
        if(!$update_cart_stmt->execute()){
            throw new Exception("Execute failed for cart update: " . $update_cart_stmt->error);
        }
        
        $affected_rows = $update_cart_stmt->affected_rows;
        $update_cart_stmt->close();
        
        error_log("Cart update successful. Affected rows: $affected_rows");
        
        // Commit transaction
        $db->conn->commit();
        error_log("Transaction committed successfully");
        
        // Clear pending order session
        unset($_SESSION['pending_order']);
        
        // Redirect to success page
        if($last_order_id){
            header("Location: order_success.php?order_id=" . $last_order_id);
        } else {
            header("Location: order_success.php");
        }
        exit;
        
    } catch (Exception $e) {
        // Rollback on error
        $db->conn->rollback();
        error_log("Transaction failed: " . $e->getMessage());
        
        $_SESSION['payment_error'] = "Failed to create order. Please contact support. Error: " . $e->getMessage();
        header("Location: checkout.php");
        exit;
    }
} else {
    // Payment verification failed
    $_SESSION['payment_error'] = "Payment verification failed. Please try again or contact support.";
    header("Location: checkout.php");
    exit;
}
?>
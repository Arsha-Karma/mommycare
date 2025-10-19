<?php
session_start();
include('db.php');

// Razorpay credentials
define('RAZORPAY_KEY_ID', 'rzp_test_LFEA5QeDc3uh7A');
define('RAZORPAY_KEY_SECRET', 'UcSEiMzamwDuhxLKkpPz1VUj');

$db = new Database();
$user_id = $_SESSION['user_id'] ?? null;

if(!$user_id || !isset($_SESSION['pending_order'])){
    header("Location: cart.php");
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['razorpay_payment_id'])){
    $payment_id = $_POST['razorpay_payment_id'];
    $order_data = $_SESSION['pending_order'];
    
    // Verify payment signature (optional but recommended)
    // For basic implementation, we'll proceed with payment_id
    
    // Fetch payment details from Razorpay API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.razorpay.com/v1/payments/" . $payment_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ":" . RAZORPAY_KEY_SECRET);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $payment_data = json_decode($response, true);
    
    // Check if payment was successful
    if($payment_data && isset($payment_data['status']) && $payment_data['status'] === 'captured'){
        // Insert order into database
        $full_name = $order_data['full_name'];
        $email = $order_data['email'];
        $phone = $order_data['phone'];
        $address = $order_data['address'];
        $payment_method = 'online';
        $grand_total = $order_data['grand_total'];
        
        $order_stmt = $db->conn->prepare("INSERT INTO orders (user_id, total_amount, full_name, email, phone, address, payment_method, payment_id, order_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())");
        $order_stmt->bind_param("idssssss", $user_id, $grand_total, $full_name, $email, $phone, $address, $payment_method, $payment_id);
        
        if($order_stmt->execute()){
            $order_id = $order_stmt->insert_id;
            
            // Insert order items with proper product_id from cart
            foreach($order_data['products'] as $product){
                // Use product_id if available, otherwise use id
                $prod_id = isset($product['product_id']) ? $product['product_id'] : $product['id'];
                $item_stmt = $db->conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $item_stmt->bind_param("iiid", $order_id, $prod_id, $product['quantity'], $product['price']);
                $item_stmt->execute();
                $item_stmt->close();
            }
            
            // IMPORTANT: Clear ALL cart items for this user after successful payment
            $clear_stmt = $db->conn->prepare("DELETE FROM cart WHERE user_id=?");
            $clear_stmt->bind_param("i", $user_id);
            $clear_stmt->execute();
            $clear_stmt->close();
            
            // Verify cart is empty (optional check)
            $verify_stmt = $db->conn->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id=?");
            $verify_stmt->bind_param("i", $user_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            $verify_data = $verify_result->fetch_assoc();
            $verify_stmt->close();
            
            // Clear pending order session
            unset($_SESSION['pending_order']);
            
            // Redirect to success page with order confirmation
            header("Location: order_success.php?order_id=" . $order_id);
            exit;
        }
        $order_stmt->close();
    } else {
        // Payment failed
        $_SESSION['payment_error'] = "Payment verification failed. Please try again.";
        header("Location: checkout.php");
        exit;
    }
} else {
    header("Location: checkout.php");
    exit;
}
?>
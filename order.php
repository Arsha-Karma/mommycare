<?php
require_once 'db.php';

class Order {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->conn;
    }
    
    public function createOrder($user_id, $order_data) {
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // Insert order
            $stmt = $this->conn->prepare("INSERT INTO orders (user_id, total_amount, full_name, email, phone, address, payment_method, payment_id, order_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $payment_id = $order_data['payment_id'] ?? null;
            $order_status = $order_data['order_status'] ?? 'pending';
            
            $stmt->bind_param("idsssssss", 
                $user_id, 
                $order_data['total_amount'], 
                $order_data['full_name'], 
                $order_data['email'], 
                $order_data['phone'], 
                $order_data['address'], 
                $order_data['payment_method'],
                $payment_id,
                $order_status
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create order");
            }
            
            $order_id = $stmt->insert_id;
            $stmt->close();
            
            // Insert order items
            foreach ($order_data['products'] as $product) {
                $product_id = $product['product_id'] ?? $product['id'];
                $item_stmt = $this->conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, created_at) VALUES (?, ?, ?, ?, NOW())");
                $item_stmt->bind_param("iiid", $order_id, $product_id, $product['quantity'], $product['price']);
                
                if (!$item_stmt->execute()) {
                    throw new Exception("Failed to insert order items");
                }
                $item_stmt->close();
            }
            
            // Clear cart after successful order
            $this->clearUserCart($user_id);
            
            // Commit transaction
            $this->conn->commit();
            
            return $order_id;
            
        } catch (Exception $e) {
            // Rollback on error
            $this->conn->rollback();
            error_log("Order creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function clearUserCart($user_id) {
        $stmt = $this->conn->prepare("DELETE FROM cart WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    public function getOrderById($order_id, $user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        $stmt->close();
        
        return $order;
    }
    
    public function getOrderItems($order_id) {
        $stmt = $this->conn->prepare("SELECT oi.*, p.name, p.image FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        
        return $items;
    }
    
    public function getUserOrders($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();
        
        return $orders;
    }
}
<?php
require_once 'config.php'; 
include('db.php');

// ------------------ Product Class ------------------
class Product {
    private $db;

    public function __construct(Database $database) {
        $this->db = $database->conn;
    }

    public function getAllProducts() {
        $sql = "SELECT * FROM products ORDER BY id ASC";
        $result = $this->db->query($sql);
        $products = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
        }
        return $products;
    }
}

// ------------------ Get Cart Count ------------------
function getCartCount($db, $user_id) {
    if (!$user_id) return 0;
    
    $stmt = $db->conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id=? AND status='active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['total'] ? (int)$row['total'] : 0;
}

$db = new Database();
$productObj = new Product($db);
$products = $productObj->getAllProducts();

// Get current cart count
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    $cartCount = getCartCount($db, $_SESSION['user_id']);
}

// ------------------ Cart Logic ------------------
if(isset($_GET['add'])) {
    if(!isset($_SESSION['user_id'])){
        header("Location: login.php");
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $product_id = (int)$_GET['add'];

    // Check if product exists and has stock
    $checkProduct = $db->conn->prepare("SELECT stock FROM products WHERE id=?");
    $checkProduct->bind_param("i", $product_id);
    $checkProduct->execute();
    $productResult = $checkProduct->get_result();
    
    if ($productResult->num_rows > 0) {
        $productData = $productResult->fetch_assoc();
        
        if ($productData['stock'] > 0) {
            // Check if product already exists in cart with ACTIVE status
            $stmt = $db->conn->prepare("SELECT id, quantity FROM cart WHERE user_id=? AND product_id=? AND status='active'");
            $stmt->bind_param("ii", $user_id, $product_id);
            $stmt->execute();
            $stmt->store_result();

            if($stmt->num_rows > 0){
                // Product already exists in active cart - don't add again
                $_SESSION['cart_message'] = 'Item already in cart! Update quantity from cart page.';
            } else {
                // Check if there's a completed order for same product
                $checkCompleted = $db->conn->prepare("SELECT id FROM cart WHERE user_id=? AND product_id=? AND status='completed'");
                $checkCompleted->bind_param("ii", $user_id, $product_id);
                $checkCompleted->execute();
                $checkCompleted->store_result();
                
                if($checkCompleted->num_rows > 0) {
                    // Update the completed record to active with quantity 1
                    $checkCompleted->bind_result($completed_id);
                    $checkCompleted->fetch();
                    $update = $db->conn->prepare("UPDATE cart SET quantity=1, status='active', added_at=NOW() WHERE id=?");
                    $update->bind_param("i", $completed_id);
                    $update->execute();
                    $update->close();
                    $_SESSION['cart_message'] = 'Item added to cart successfully!';
                } else {
                    // Product not in cart at all, insert new
                    $insert = $db->conn->prepare("INSERT INTO cart(user_id, product_id, quantity, status) VALUES(?,?,1,'active')");
                    $insert->bind_param("ii", $user_id, $product_id);
                    $insert->execute();
                    $insert->close();
                    $_SESSION['cart_message'] = 'Item added to cart successfully!';
                }
                $checkCompleted->close();
            }
            $stmt->close();
        } else {
            $_SESSION['cart_message'] = 'Sorry, this item is out of stock!';
        }
    }
    $checkProduct->close();
    
    // Update cart count after adding item
    $cartCount = getCartCount($db, $user_id);
    
    header("Location: index.php#featured-products");
    exit;
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>mommycare - New Born Baby Clothes</title>
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
    color: #42375a;
    line-height: 1.6;
    background: #fff;
    overflow-x: hidden;
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

/* ===== CART & USER STYLES ===== */
.header-actions {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.cart-container {
    position: relative;
    display: inline-block;
}

.cart-btn {
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    color: #fff;
    border: none;
    border-radius: 25px;
    padding: 0.7rem 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 15px rgba(249, 130, 147, 0.3);
}

.cart-btn:hover {
    background: linear-gradient(135deg, #e06d7f 0%, #d45a6d 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(249, 130, 147, 0.4);
}

.cart-count {
    position: absolute;
    top: -8px;
    right: -8px;
    background: linear-gradient(135deg, #93e2bb 0%, #7dc9a5 100%);
    color: #fff;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    border: 3px solid #fff;
    box-shadow: 0 2px 8px rgba(147, 226, 187, 0.4);
}

.login-link {
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    color: #fff;
    border-radius: 25px;
    padding: 0.7rem 1.5rem;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    display: inline-block;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(249, 130, 147, 0.3);
}

.login-link:hover {
    background: linear-gradient(135deg, #e06d7f 0%, #d45a6d 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(249, 130, 147, 0.4);
}

.user-container {
    position: relative;
    display: inline-block;
}

.user-avatar {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    color: #fff;
    font-weight: 700;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 1.3rem;
    cursor: pointer;
    text-transform: uppercase;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(249, 130, 147, 0.3);
}

.user-avatar:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(249, 130, 147, 0.4);
}

.user-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 55px;
    background: #fff;
    min-width: 160px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
    border-radius: 12px;
    z-index: 1001;
    overflow: hidden;
    border: 1px solid #f0f0f0;
}

.user-dropdown a {
    color: #42375a;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
    font-size: 0.95rem;
    font-weight: 500;
    transition: all 0.3s ease;
    border-bottom: 1px solid #f8f8f8;
}

.user-dropdown a:last-child {
    border-bottom: none;
}

.user-dropdown a:hover {
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    color: #fff;
    transform: translateX(5px);
}

/* ===== TOAST NOTIFICATION ===== */
.toast {
    position: fixed;
    top: 90px;
    right: 30px;
    background: linear-gradient(135deg, #93e2bb 0%, #7dc9a5 100%);
    color: #fff;
    padding: 1rem 1.8rem;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(147, 226, 187, 0.4);
    z-index: 10000;
    animation: slideIn 0.4s ease, slideOut 0.4s ease 2.6s;
    font-weight: 600;
    font-size: 0.95rem;
    max-width: 300px;
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

@keyframes slideOut {
    from { 
        transform: translateX(0); 
        opacity: 1; 
    }
    to { 
        transform: translateX(400px); 
        opacity: 0; 
    }
}

/* ===== HERO SECTION ===== */
.hero {
    height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), 
                url('image/img4.jpg') center center/cover no-repeat;
    color: #fff;
    padding: 0 2rem;
    margin-top: 0;
    position: relative;
}

.hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.3);
}

.hero > * {
    position: relative;
    z-index: 2;
}

.hero h1 {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.5);
    font-weight: 800;
    letter-spacing: 1px;
}

.hero p {
    font-size: 1.5rem;
    margin-bottom: 2.5rem;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
    max-width: 600px;
    line-height: 1.8;
}

.shop-btn {
    background: linear-gradient(135deg, #93e2bb 0%, #7dc9a5 100%);
    color: #fff;
    padding: 1.2rem 3rem;
    border: none;
    border-radius: 35px;
    font-size: 1.3rem;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 700;
    box-shadow: 0 8px 25px rgba(147, 226, 187, 0.4);
    display: inline-block;
}

.shop-btn:hover {
    background: linear-gradient(135deg, #7dc9a5 0%, #6bb894 100%);
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(147, 226, 187, 0.6);
}

/* ===== FEATURED PRODUCTS ===== */
.products {
    padding: 6rem 5vw;
    background: linear-gradient(135deg, #faf7fc 0%, #f0ebf5 100%);
    position: relative;
}

.products h2 {
    text-align: center;
    color: #f98293;
    margin-bottom: 3rem;
    font-size: 2.8rem;
    font-weight: 800;
    position: relative;
}

.products h2::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    border-radius: 2px;
}

.product-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2.5rem;
    max-width: 1400px;
    margin: 0 auto;
}

.product-card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 8px 30px rgba(16, 16, 90, 0.12);
    padding: 1.8rem;
    text-align: center;
    transition: all 0.4s ease;
    position: relative;
    overflow: hidden;
}

.product-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
}

.product-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(16, 16, 90, 0.2);
}

.product-card img {
    width: 85%;
    height: 220px;
    object-fit: cover;
    border-radius: 15px;
    margin-bottom: 1.5rem;
    transition: transform 0.4s ease;
}

.product-card:hover img {
    transform: scale(1.08);
}

.product-card h3 {
    margin-bottom: 0.8rem;
    font-size: 1.3rem;
    color: #42375a;
    font-weight: 700;
}

.product-card .price {
    color: #93e2bb;
    font-weight: 800;
    font-size: 1.5rem;
    margin: 0.8rem 0;
}

.product-card .stock-info {
    color: #888;
    font-size: 0.9rem;
    margin: 0.5rem 0;
    font-weight: 500;
}

.add-to-cart-btn {
    display: inline-block;
    margin-top: 1rem;
    padding: 0.8rem 2rem;
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    color: #fff;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    font-size: 1rem;
    box-shadow: 0 4px 15px rgba(249, 130, 147, 0.3);
}

.add-to-cart-btn:hover {
    background: linear-gradient(135deg, #e06d7f 0%, #d45a6d 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(249, 130, 147, 0.4);
}

.out-of-stock {
    display: inline-block;
    margin-top: 1rem;
    padding: 0.8rem 2rem;
    background: #e0e0e0;
    color: #999;
    border-radius: 25px;
    font-weight: 600;
    cursor: not-allowed;
}

/* ===== ABOUT SECTION ===== */
.about {
    background: linear-gradient(135deg, #bcd9ea 0%, #a8c9e0 100%);
    color: #42375a;
    text-align: center;
    padding: 5rem 2rem;
    position: relative;
}

.about h2 {
    font-size: 2.8rem;
    margin-bottom: 2.5rem;
    font-weight: 800;
    color: #42375a;
    position: relative;
}

.about h2::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: linear-gradient(135deg, #42375a 0%, #352a4a 100%);
    border-radius: 2px;
}

.about ul {
    display: inline-block;
    text-align: left;
    margin-top: 2rem;
    list-style: none;
}

.about li {
    font-size: 1.3rem;
    margin: 1.2rem 0;
    padding-left: 2rem;
    position: relative;
    font-weight: 600;
}

.about li::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #f98293;
    font-weight: bold;
    font-size: 1.5rem;
}

/* ===== FOOTER ===== */
footer {
    background: linear-gradient(135deg, #42375a 0%, #352a4a 100%);
    color: #fff;
    text-align: center;
    padding: 2.5rem 1rem;
    font-size: 1.1rem;
}

footer p {
    margin: 0;
    font-weight: 500;
    opacity: 0.9;
}

/* ===== RESPONSIVE DESIGN ===== */
@media (max-width: 1200px) {
    .hero h1 {
        font-size: 3.5rem;
    }
    
    .product-list {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
}

@media (max-width: 900px) {
    nav {
        padding: 1rem 3vw;
    }
    
    nav ul {
        gap: 1.5rem;
    }
    
    .hero h1 {
        font-size: 2.8rem;
    }
    
    .hero p {
        font-size: 1.3rem;
    }
    
    .products h2,
    .about h2 {
        font-size: 2.3rem;
    }
    
    .product-list {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 2rem;
    }
}

@media (max-width: 768px) {
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
    
    .header-actions {
        gap: 1rem;
    }
    
    .hero {
        height: 70vh;
        padding: 0 1rem;
    }
    
    .hero h1 {
        font-size: 2.2rem;
    }
    
    .hero p {
        font-size: 1.1rem;
    }
    
    .shop-btn {
        padding: 1rem 2rem;
        font-size: 1.1rem;
    }
    
    .products {
        padding: 4rem 3vw;
    }
    
    .product-list {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
    }
    
    .about {
        padding: 3rem 1rem;
    }
    
    .about li {
        font-size: 1.1rem;
    }
}

@media (max-width: 480px) {
    .logo {
        font-size: 1.8rem;
    }
    
    .hero h1 {
        font-size: 1.8rem;
    }
    
    .hero p {
        font-size: 1rem;
    }
    
    .products h2,
    .about h2 {
        font-size: 1.8rem;
    }
    
    .product-list {
        grid-template-columns: 1fr;
        max-width: 300px;
        margin: 0 auto;
    }
    
    .cart-btn,
    .login-link {
        padding: 0.6rem 1.2rem;
        font-size: 0.9rem;
    }
    
    .toast {
        right: 15px;
        left: 15px;
        max-width: none;
    }
}

/* ===== LOADING ANIMATION ===== */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.product-card {
    animation: fadeIn 0.6s ease;
}

/* ===== SCROLLBAR STYLING ===== */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #e06d7f 0%, #d45a6d 100%);
}
.search-container {
    position: relative;
    flex: 1;
    max-width: 400px;
    margin: 0 2rem;
}

.search-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-input {
    width: 100%;
    padding: 0.8rem 1.2rem 0.8rem 3rem;
    border: 2px solid #e2e8f0;
    border-radius: 25px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    outline: none;
    background: #fff;
}

.search-input:focus {
    border-color: #f98293;
    box-shadow: 0 0 0 3px rgba(249, 130, 147, 0.1);
}

.search-icon {
    position: absolute;
    left: 1rem;
    color: #718096;
    font-size: 1.1rem;
    pointer-events: none;
}

.clear-search {
    position: absolute;
    right: 1rem;
    background: #f98293;
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.2s ease;
}

.clear-search:hover {
    background: #e06d7f;
    transform: scale(1.1);
}

.clear-search.show {
    display: flex;
}

.search-results-info {
    text-align: center;
    margin-bottom: 2rem;
    color: #42375a;
    font-size: 1.1rem;
    font-weight: 600;
}

.search-results-info .search-term {
    color: #f98293;
    font-weight: 700;
}

.no-results {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem 2rem;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.no-results-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.no-results h3 {
    color: #42375a;
    margin-bottom: 0.5rem;
    font-size: 1.5rem;
}

.no-results p {
    color: #718096;
    font-size: 1rem;
}

@media (max-width: 480px) {
    .logo {
        font-size: 1.8rem;
    }
    
    .hero h1 {
        font-size: 1.8rem;
    }
    
    .hero p {
        font-size: 1rem;
    }
    
    .products h2,
    .about h2 {
        font-size: 1.8rem;
    }
    
    .product-list {
        grid-template-columns: 1fr;
        max-width: 300px;
        margin: 0 auto;
    }
    
    .cart-btn,
    .login-link {
        padding: 0.6rem 1.2rem;
        font-size: 0.9rem;
    }
    
    .toast {
        right: 15px;
        left: 15px;
        max-width: none;
    }
}

/* ===== SEARCH RESULTS COMPACT VIEW ===== */
.product-list.search-active .product-card {
    padding: 1rem;
    max-width: 280px;
    margin: 0 auto;
}

.product-list.search-active .product-card img {
    width: 60%;
    height: 120px;
    margin-bottom: 0.8rem;
}

.product-list.search-active .product-card h3 {
    font-size: 1rem;
    margin-bottom: 0.4rem;
}

.product-list.search-active .product-card .price {
    font-size: 1.2rem;
    margin: 0.4rem 0;
}

.product-list.search-active .product-card .stock-info {
    font-size: 0.8rem;
    margin: 0.3rem 0;
}

.product-list.search-active .product-card .add-to-cart-btn,
.product-list.search-active .product-card .out-of-stock {
    padding: 0.5rem 1.2rem;
    font-size: 0.85rem;
    margin-top: 0.6rem;
}

@media (min-width: 769px) {
    .product-list.search-active {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1.5rem;
        max-width: 1200px;
    }
}

@media (max-width: 768px) {
    .product-list.search-active .product-card {
        max-width: 100%;
    }
    
    .product-list.search-active .product-card img {
        width: 50%;
        height: 100px;
    }
}

@media (max-width: 480px) {
    .product-list.search-active .product-card img {
        width: 55%;
        height: 110px;
    }
}
</style>
</head>
<body>

<?php if(isset($_SESSION['cart_message'])): ?>
<div class="toast" id="toast">
    <?php 
    echo htmlspecialchars($_SESSION['cart_message']); 
    unset($_SESSION['cart_message']);
    ?>
</div>
<?php endif; ?>

<header>
    <nav>
        <a href="index.php" class="logo">mommycare</a>
        
        <!-- Search Bar -->
        <div class="search-container">
            <div class="search-wrapper">
                <span class="search-icon">🔍</span>
                <input 
                    type="text" 
                    id="searchInput" 
                    class="search-input" 
                    placeholder="Search products..."
                    autocomplete="off"
                >
                <button class="clear-search" id="clearSearch" aria-label="Clear search">×</button>
            </div>
        </div>
        
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="#featured-products">Products</a></li>
            <li><a href="orders.php">Orders</a></li>
        </ul>
        <div class="header-actions">
            <div class="cart-container">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="cart.php" class="cart-btn">🛒 Cart</a>
                <?php else: ?>
                    <button class="cart-btn" id="cartBtn">🛒 Cart</button>
                <?php endif; ?>
                
                <?php if ($cartCount > 0): ?>
                    <span class="cart-count" id="cartCount">
                        <?php echo $cartCount; ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if(isset($_SESSION['user_name'])): ?>
            <div class="user-container">
                <div class="user-avatar" id="userAvatar">
                    <?php echo strtoupper($_SESSION['user_name'][0]); ?>
                </div>
                <div class="user-dropdown" id="userDropdown">
                    <a href="orders.php">📦 My Orders</a>
                    <a href="logout.php">🚪 Logout</a>
                </div>
            </div>
            <?php else: ?>
                <a href="login.php" class="login-link">🔐 Login</a>
            <?php endif; ?>
        </div>
    </nav>
</header>
<div class="hero">
    <h1>Welcome to mommycare</h1>
    <p>Your loving store for New Born Baby Clothes – Comfort, Style & Care</p>
    <a href="#featured-products" class="shop-btn">Shop Now</a>
</div>

<main>
    <section id="featured-products" class="products">
    <h2>Featured Products</h2>
    <div id="searchResultsInfo" class="search-results-info" style="display: none;">
        Showing results for "<span class="search-term" id="searchTerm"></span>"
        <span id="resultCount"></span>
    </div>
    <div class="product-list" id="productList">
        <?php if (!empty($products)): ?>
            <?php foreach ($products as $product): ?>
                <div class="product-card" data-product-name="<?php echo strtolower(htmlspecialchars($product['name'])); ?>" data-product-id="<?php echo $product['id']; ?>">
                    <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p class="price">₹<?php echo number_format($product['price'], 2); ?></p>
                    <p class="stock-info">Stock: <?php echo (int)$product['stock']; ?> available</p>
                    <?php if ($product['stock'] > 0): ?>
                        <a href="index.php?add=<?php echo $product['id']; ?>" class="add-to-cart-btn">
                            🛒 Add to Cart
                        </a>
                    <?php else: ?>
                        <span class="out-of-stock">❌ Out of Stock</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align:center; grid-column: 1/-1; font-size: 1.2rem; color: #666;">
                No products available right now. Please check back later.
            </p>
        <?php endif; ?>
    </div>
</section>


    <section class="about">
        <h2>Why Choose mommycare?</h2>
        <ul>
            <li>Handpicked, 100% organic fabrics for sensitive skin</li>
            <li>Adorable, modern styles for boys & girls</li>
            <li>Hassle-free returns and fast shipping</li>
        </ul>
    </section>
</main>

<footer>
    <p>&copy; 2025 mommycare. Designed with care for your little one! 💝</p>
</footer>

<script>
// Auto-hide toast notification
const toast = document.getElementById('toast');
if (toast) {
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Toggle user dropdown menu
const userAvatar = document.getElementById('userAvatar');
const userDropdown = document.getElementById('userDropdown');

if(userAvatar) {
    userAvatar.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
            userDropdown.style.display = 'none';
        }
    });
}

// Cart button for non-logged in users
const cartBtn = document.getElementById('cartBtn');
if(cartBtn) {
    cartBtn.addEventListener('click', function() {
        alert('Please login to view your cart.');
        window.location.href = 'login.php';
    });
}

// Smooth scrolling for navigation links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Header scroll effect
window.addEventListener('scroll', () => {
    const header = document.querySelector('header');
    if (window.scrollY > 100) {
        header.style.background = 'rgba(255, 255, 255, 0.95)';
        header.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.1)';
    } else {
        header.style.background = 'rgba(255, 255, 255, 0.98)';
        header.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.1)';
    }
});
// Search functionality
const searchInput = document.getElementById('searchInput');
const clearSearchBtn = document.getElementById('clearSearch');
const productCards = document.querySelectorAll('.product-card');
const searchResultsInfo = document.getElementById('searchResultsInfo');
const searchTerm = document.getElementById('searchTerm');
const resultCount = document.getElementById('resultCount');
const productList = document.getElementById('productList');

// Show/hide clear button
searchInput.addEventListener('input', function() {
    if (this.value.length > 0) {
        clearSearchBtn.classList.add('show');
    } else {
        clearSearchBtn.classList.remove('show');
    }
    filterProducts();
});

// Clear search
clearSearchBtn.addEventListener('click', function() {
    searchInput.value = '';
    clearSearchBtn.classList.remove('show');
    filterProducts();
    searchInput.focus();
});

// Filter products based on search input
function filterProducts() {
    const searchValue = searchInput.value.toLowerCase().trim();
    let visibleCount = 0;
    let hasNoResults = false;

    // Remove any existing no-results message
    const existingNoResults = document.querySelector('.no-results');
    if (existingNoResults) {
        existingNoResults.remove();
    }

    if (searchValue === '') {
        // Show all products when search is empty
        productCards.forEach(card => {
            card.style.display = 'block';
        });
        searchResultsInfo.style.display = 'none';
        return;
    }

    // Filter products
    productCards.forEach(card => {
        const productName = card.dataset.productName;
        
        // Check if product name starts with search term or contains it
        if (productName.startsWith(searchValue) || productName.includes(searchValue)) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Show search results info
    searchResultsInfo.style.display = 'block';
    searchTerm.textContent = searchInput.value;
    resultCount.textContent = ` (${visibleCount} ${visibleCount === 1 ? 'product' : 'products'} found)`;

    // Show no results message if no products match
    if (visibleCount === 0) {
        const noResultsDiv = document.createElement('div');
        noResultsDiv.className = 'no-results';
        noResultsDiv.innerHTML = `
            <div class="no-results-icon">🔍</div>
            <h3>No products found</h3>
            <p>Try searching with different keywords</p>
        `;
        productList.appendChild(noResultsDiv);
    }

    // Scroll to products section
    document.getElementById('featured-products').scrollIntoView({ 
        behavior: 'smooth', 
        block: 'start' 
    });
}

// Clear search when clicking on logo
document.querySelector('.logo').addEventListener('click', function() {
    searchInput.value = '';
    clearSearchBtn.classList.remove('show');
    filterProducts();
});

// Keyboard shortcut: Press '/' to focus search
document.addEventListener('keydown', function(e) {
    if (e.key === '/' && document.activeElement !== searchInput) {
        e.preventDefault();
        searchInput.focus();
    }
    
    // Press 'Escape' to clear search
    if (e.key === 'Escape' && document.activeElement === searchInput) {
        searchInput.value = '';
        clearSearchBtn.classList.remove('show');
        filterProducts();
        searchInput.blur();
    }
});
</script>

</body>
</html>
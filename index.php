<?php
session_start(); // Start session
include('db.php'); // Database connection

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

$db = new Database();
$productObj = new Product($db);
$products = $productObj->getAllProducts();

// ------------------ Cart Logic ------------------
// Ensure user is logged in before adding to cart
if(isset($_GET['add'])) {
    if(!isset($_SESSION['user_id'])){
        header("Location: login.php");
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $product_id = (int)$_GET['add'];

    // Check if product already exists in cart
    $stmt = $db->conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $stmt->store_result();

    if($stmt->num_rows > 0){
        // Product exists, increment quantity
        $stmt->bind_result($qty);
        $stmt->fetch();
        $new_qty = $qty + 1;
        $update = $db->conn->prepare("UPDATE cart SET quantity=? WHERE user_id=? AND product_id=?");
        $update->bind_param("iii", $new_qty, $user_id, $product_id);
        $update->execute();
        $update->close();
    } else {
        // Product not in cart, insert new
        $insert = $db->conn->prepare("INSERT INTO cart(user_id, product_id, quantity) VALUES(?,?,1)");
        $insert->bind_param("ii", $user_id, $product_id);
        $insert->execute();
        $insert->close();
    }

    $stmt->close();
    header("Location: index.php"); // Prevent duplicate add on refresh
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
html { scroll-behavior: smooth; }

body {
  margin: 0;
  font-family: 'Segoe UI', Arial, sans-serif;
  color: #42375a;
}

/* Fixed header */
header {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  z-index: 1000;
  background: rgba(255,255,255,0.95);
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 5vw;
}

nav .logo { font-size: 2rem; color: #f98293; font-weight: bold; letter-spacing: 1px; }

nav ul {
  list-style: none;
  display: flex;
  gap: 2rem;
  margin: 0;
  padding: 0;
}

nav ul li a {
  text-decoration: none;
  color: #42375a;
  font-weight: 500;
  font-size: 1rem;
}

/* Cart button */
#cartBtn {
  background: #f98293;
  color: #fff;
  border: none;
  border-radius: 20px;
  padding: 0.6em 1.2em;
  cursor: pointer;
}

/* Login link styled like cart button */
.login-link {
  background: #f98293;
  color: #fff;
  border: none;
  border-radius: 20px;
  padding: 0.6em 1.2em;
  text-decoration: none;
  font-weight: 500;
  cursor: pointer;
  display: inline-block;
  transition: background 0.3s ease;
}

.login-link:hover {
  background: #e06d7f;
}

/* User avatar */
.user-container {
  position: relative;
  display: inline-block;
}

.user-avatar {
  width: 40px;
  height: 40px;
  background-color: #f98293;
  color: #fff;
  font-weight: bold;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 1.2rem;
  cursor: pointer;
  text-transform: uppercase;
}

/* Dropdown menu */
.user-dropdown {
  display: none;
  position: absolute;
  right: 0;
  top: 50px;
  background-color: #fff;
  min-width: 140px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.2);
  border-radius: 8px;
  z-index: 1001;
}

.user-dropdown a {
  color: #42375a;
  padding: 10px 15px;
  text-decoration: none;
  display: block;
  font-size: 0.95rem;
}

.user-dropdown a:hover {
  background-color: #f98293;
  color: #fff;
}

/* Hero section */
.hero {
  height: 80vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  background: url('image/img4.jpg') center center/cover no-repeat;
  color: #fff;
  padding: 0 2rem;
}

.hero h1 { font-size: 3rem; margin-bottom: 1rem; }
.hero p { font-size: 1.3rem; margin-bottom: 2rem; }
.shop-btn {
  background: #93e2bb;
  color: #fff;
  padding: 1rem 2.5rem;
  border: none;
  border-radius: 25px;
  font-size: 1.2rem;
  text-decoration: none;
  cursor: pointer;
}

/* Featured Products */
.products {
  padding: 3rem 5vw;
  background: rgba(250,247,252,0.95);
}
.products h2 {
  text-align: center;
  color: #f98293;
  margin-bottom: 2rem;
}
.product-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 2rem;
  justify-content: center;
}
.product-card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(16,16,90,0.09);
  padding: 1rem;
  text-align: center;
}
.product-card img {
  width: 80%;
  border-radius: 10px;
  margin-bottom: 1rem;
}
.product-card h3 { margin-bottom: 0.4rem; font-size: 1.1rem; color: #42375a; }
.product-card p { color: #93e2bb; font-weight: bold; }
.product-card button {
  margin-top: 0.5rem;
  padding: 0.5em 1.2em;
  background: #f98293;
  color: #fff;
  border: none;
  border-radius: 18px;
  cursor: pointer;
}

/* About Section */
.about {
  background: #bcd9ea;
  color: #42375a;
  text-align: center;
  padding: 2rem 1vw;
}
.about ul {
  display: inline-block;
  text-align: left;
  margin-top: 1rem;
}
.about li { font-size: 1.1rem; margin: 0.6rem 0; }

/* Footer */
footer { background: #42375a; color: #fff; text-align: center; padding: 1rem 0; }

@media (max-width: 900px) {
  .product-list { flex-direction: column; align-items: center; }
  nav ul { gap: 1rem; }
  .hero h1 { font-size: 2.2rem; }
  .hero p { font-size: 1.1rem; }
}
.add-to-cart-btn {
  display: inline-block;
  margin-top: 0.5rem;
  padding: 0.5em 1.2em;
  background: #f98293;
  color: #fff;
  border-radius: 18px;
  text-decoration: none;
  font-weight: 500;
  cursor: pointer;
}

.add-to-cart-btn:hover {
  background: #e06d7f;
}

.out-of-stock {
  display: inline-block;
  margin-top: 0.5rem;
  padding: 0.5em 1.2em;
  background: #ccc;
  color: #fff;
  border-radius: 18px;
}

</style>
</head>
<body>

<header>
  <nav>
    <div class="logo">mommycare</div>
    <ul>
      <li><a href="#">Home</a></li>
      <li><a href="#featured-products">Shop</a></li>
      <li><a href="#">Reviews</a></li>
      <li><a href="#">Contact</a></li>
    </ul>
    <div style="display:flex; align-items:center; gap:1rem;">
      <button id="cartBtn">🛒 Cart</button>
      <?php if(isset($_SESSION['user_name'])): ?>
      <div class="user-container">
        <div class="user-avatar" id="userAvatar"><?php echo strtoupper($_SESSION['user_name'][0]); ?></div>
        <div class="user-dropdown" id="userDropdown">
          <a href="orders.php">My Orders</a>
          <a href="logout.php">Logout</a>
        </div>
      </div>
      <?php else: ?>
        <a href="login.php" class="login-link">Login</a>
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
  <!-- Featured Products Section -->
  <section id="featured-products" class="products">
    <h2>Featured Products</h2>
    <div class="product-list">
      <?php if (!empty($products)) { ?>
        <?php foreach ($products as $product) { ?>
          <div class="product-card">
            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
            <p>₹<?php echo number_format($product['price'], 2); ?></p>
            <?php if ($product['stock'] > 0) { ?>
              <a href="cart.php?add=<?php echo $product['id']; ?>" class="add-to-cart-btn">Add to Cart</a>
            <?php } else { ?>
              <span class="out-of-stock">Out of Stock</span>
            <?php } ?>
          </div>
        <?php } ?>
      <?php } else { ?>
        <p style="text-align:center;">No products available right now.</p>
      <?php } ?>
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
  <p>&copy; 2025 mommycare. Designed with care for your little one!</p>
</footer>

<script>
// Toggle user dropdown menu
const userAvatar = document.getElementById('userAvatar');
const userDropdown = document.getElementById('userDropdown');

if(userAvatar) {
  userAvatar.addEventListener('click', () => {
    userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
  });

  // Close dropdown if click outside
  window.addEventListener('click', function(e) {
    if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
      userDropdown.style.display = 'none';
    }
  });
}

// Product "Add to Cart" alerts
document.querySelectorAll('.product-card button').forEach(btn => {
  btn.addEventListener('click', function() {
    if (this.disabled) return;
    alert("This item has been added to your cart!");
  });
});

document.getElementById('cartBtn').addEventListener('click', function() {
  alert('Your cart is currently empty.');
});
</script>

</body>
</html>

<?php
require_once 'config.php';
include('db.php');

// Check if user is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

$db = new Database();
$message = '';
$messageType = '';

// Handle Delete Product
if (isset($_GET['delete'])) {
    $product_id = (int)$_GET['delete'];
    
    // Get image path before deleting
    $stmt = $db->conn->prepare("SELECT image FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Delete the product
        $deleteStmt = $db->conn->prepare("DELETE FROM products WHERE id = ?");
        $deleteStmt->bind_param("i", $product_id);
        
        if ($deleteStmt->execute()) {
            // Optionally delete the image file
            if (file_exists($product['image'])) {
                unlink($product['image']);
            }
            $message = "Product deleted successfully!";
            $messageType = "success";
        } else {
            $message = "Error deleting product!";
            $messageType = "error";
        }
        $deleteStmt->close();
    }
    $stmt->close();
    
    header("Location: admin.php?msg=" . urlencode($message) . "&type=" . $messageType);
    exit;
}

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);
    $stock = trim($_POST['stock']);
    
    $errors = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = "Product name is required";
    } elseif (strlen($name) < 3) {
        $errors[] = "Product name must be at least 3 letters";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if (empty($price)) {
        $errors[] = "Price is required";
    } elseif (!is_numeric($price) || $price <= 1) {
        $errors[] = "Price must be greater than ₹1";
    }
    
    if (empty($stock)) {
        $errors[] = "Stock is required";
    } elseif (!is_numeric($stock) || $stock <= 1) {
        $errors[] = "Stock must be greater than 1";
    }
    
    // Image validation
    if (!isset($_FILES['image']) || $_FILES['image']['error'] == UPLOAD_ERR_NO_FILE) {
        $errors[] = "Product image is required";
    } else {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = "Only JPG, JPEG, PNG, and GIF images are allowed";
        }
        
        if ($_FILES['image']['size'] > $max_size) {
            $errors[] = "Image size must be less than 5MB";
        }
    }
    
    // If no errors, process the upload
    if (empty($errors)) {
        $upload_dir = 'image/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
        $target_file = $upload_dir . $unique_filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            // Insert into database
            $stmt = $db->conn->prepare("INSERT INTO products (name, description, price, stock, image) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdis", $name, $description, $price, $stock, $target_file);
            
            if ($stmt->execute()) {
                $message = "Product added successfully!";
                $messageType = "success";
                // Clear form
                $_POST = array();
            } else {
                $message = "Error adding product to database!";
                $messageType = "error";
                unlink($target_file); // Delete uploaded file
            }
            $stmt->close();
        } else {
            $errors[] = "Failed to upload image";
        }
    }
    
    if (!empty($errors)) {
        $message = implode("<br>", $errors);
        $messageType = "error";
    }
}

// Get all products
$products_query = "SELECT * FROM products ORDER BY id DESC";
$products_result = $db->conn->query($products_query);
$products = [];
if ($products_result && $products_result->num_rows > 0) {
    while ($row = $products_result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Display message from redirect
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? 'success';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Product Management</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #faf7fc 0%, #f0ebf5 100%);
    color: #42375a;
    line-height: 1.6;
    padding-top: 80px;
}

/* Header */
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
    font-size: 2rem;
    color: #f98293;
    font-weight: 800;
    text-decoration: none;
}

.nav-links {
    display: flex;
    gap: 2rem;
    align-items: center;
}

.nav-links a {
    text-decoration: none;
    color: #42375a;
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.nav-links a:hover {
    background: #f98293;
    color: #fff;
}

.logout-btn {
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    color: #fff !important;
    padding: 0.6rem 1.5rem !important;
    border-radius: 25px;
    box-shadow: 0 4px 15px rgba(249, 130, 147, 0.3);
}

/* Container */
.container {
    max-width: 1400px;
    margin: 2rem auto;
    padding: 0 2rem;
}

.page-title {
    text-align: center;
    color: #f98293;
    font-size: 2.5rem;
    margin-bottom: 2rem;
    font-weight: 800;
}

/* Message Alert */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    font-weight: 600;
    animation: slideDown 0.4s ease;
}

.alert-success {
    background: linear-gradient(135deg, #93e2bb 0%, #7dc9a5 100%);
    color: #fff;
}

.alert-error {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
    color: #fff;
}

@keyframes slideDown {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Form Section - Made Smaller */
.form-section {
    background: #fff;
    padding: 1.5rem;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(16, 16, 90, 0.1);
    margin-bottom: 2.5rem;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}

.form-section h2 {
    color: #42375a;
    margin-bottom: 1.2rem;
    font-size: 1.5rem;
    border-bottom: 2px solid #f98293;
    padding-bottom: 0.4rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 0.4rem;
    color: #42375a;
    font-size: 0.85rem;
}

.form-group input,
.form-group textarea {
    padding: 0.6rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    font-family: inherit;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #f98293;
    box-shadow: 0 0 0 3px rgba(249, 130, 147, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.file-input-wrapper {
    position: relative;
    overflow: hidden;
    display: inline-block;
    width: 100%;
}

.file-input-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.6rem;
    background: linear-gradient(135deg, #bcd9ea 0%, #a8c9e0 100%);
    color: #42375a;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.file-input-label:hover {
    background: linear-gradient(135deg, #a8c9e0 0%, #95bdd5 100%);
}

.file-input-wrapper input[type="file"] {
    position: absolute;
    left: -9999px;
}

.file-name {
    margin-top: 0.4rem;
    font-size: 0.85rem;
    color: #666;
    font-style: italic;
}

.submit-btn {
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    color: #fff;
    padding: 0.8rem 2rem;
    border: none;
    border-radius: 20px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(249, 130, 147, 0.3);
    margin-top: 0.5rem;
}

.submit-btn:hover {
    background: linear-gradient(135deg, #e06d7f 0%, #d45a6d 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(249, 130, 147, 0.4);
}

/* Products Table */
.products-section {
    background: #fff;
    padding: 2rem;
    border-radius: 20px;
    box-shadow: 0 8px 30px rgba(16, 16, 90, 0.12);
}

.products-section h2 {
    color: #42375a;
    margin-bottom: 1.5rem;
    font-size: 1.8rem;
    border-bottom: 3px solid #f98293;
    padding-bottom: 0.5rem;
}

.table-wrapper {
    overflow-x: auto;
}

.products-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.products-table thead {
    background: linear-gradient(135deg, #f98293 0%, #e06d7f 100%);
    color: #fff;
}

.products-table th,
.products-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

.products-table th {
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.9rem;
    letter-spacing: 0.5px;
}

.products-table tbody tr {
    transition: all 0.3s ease;
}

.products-table tbody tr:hover {
    background: #faf7fc;
}

.product-image {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.delete-btn {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
    color: #fff;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.delete-btn:hover {
    background: linear-gradient(135deg, #ee5a6f 0%, #dc4a5d 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(238, 90, 111, 0.4);
}

.no-products {
    text-align: center;
    padding: 3rem;
    color: #666;
    font-size: 1.1rem;
}

/* Responsive */
@media (max-width: 768px) {
    nav {
        flex-direction: column;
        gap: 1rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-section {
        padding: 1rem;
    }
    
    .table-wrapper {
        font-size: 0.9rem;
    }
    
    .products-table th,
    .products-table td {
        padding: 0.7rem 0.5rem;
    }
}
</style>
</head>
<body>

<header>
    <nav>
        <a href="index.php" class="logo">mommycare</a>
        <div class="nav-links">
            <a href="index.php">🏠 Home</a>
            <a href="logout.php" class="logout-btn">🚪 Logout</a>
        </div>
    </nav>
</header>

<div class="container">
    <h1 class="page-title">Product Management</h1>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Add Product Form -->
    <div class="form-section">
        <h2>➕ Add New Product</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Product Name * </label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        placeholder="Enter product name"
                        minlength="3"
                        value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="price">Price (₹) * </label>
                    <input 
                        type="number" 
                        id="price" 
                        name="price" 
                        placeholder="Enter price"
                        step="0.01"
                        min="1.01"
                        value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="stock">Stock * </label>
                    <input 
                        type="number" 
                        id="stock" 
                        name="stock" 
                        placeholder="Enter stock quantity"
                        min="2"
                        value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : ''; ?>"
                        required
                    >
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description * </label>
                <textarea 
                    id="description" 
                    name="description" 
                    placeholder="Enter product description"
                    required
                ><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Product Image * </label>
                <div class="file-input-wrapper">
                    <label for="image" class="file-input-label">
                        📷 Choose Image
                    </label>
                    <input 
                        type="file" 
                        id="image" 
                        name="image" 
                        accept="image/*"
                        required
                        onchange="displayFileName(this)"
                    >
                </div>
                <div class="file-name" id="fileName">No file chosen</div>
            </div>
            
            <button type="submit" name="add_product" class="submit-btn">
                ✅ Add Product
            </button>
        </form>
    </div>
    
    <!-- Products List -->
    <div class="products-section">
        <h2>📦 Existing Products</h2>
        
        <?php if (!empty($products)): ?>
        <div class="table-wrapper">
            <table class="products-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo $product['id']; ?></td>
                        <td>
                            <img 
                                src="<?php echo htmlspecialchars($product['image']); ?>" 
                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                                class="product-image"
                            >
                        </td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars(substr($product['description'], 0, 50)) . '...'; ?></td>
                        <td>₹<?php echo number_format($product['price'], 2); ?></td>
                        <td><?php echo $product['stock']; ?></td>
                        <td>
                            <a 
                                href="admin.php?delete=<?php echo $product['id']; ?>" 
                                class="delete-btn"
                                onclick="return confirm('Are you sure you want to delete this product?')"
                            >
                                🗑️ Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="no-products">
            <p>No products found. Add your first product above!</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Display selected file name
function displayFileName(input) {
    const fileName = document.getElementById('fileName');
    if (input.files && input.files[0]) {
        fileName.textContent = input.files[0].name;
        fileName.style.color = '#93e2bb';
        fileName.style.fontWeight = '600';
    } else {
        fileName.textContent = 'No file chosen';
        fileName.style.color = '#666';
        fileName.style.fontWeight = 'normal';
    }
}

// Auto-hide alert after 5 seconds
const alert = document.querySelector('.alert');
if (alert) {
    setTimeout(() => {
        alert.style.animation = 'slideDown 0.4s ease reverse';
        setTimeout(() => alert.remove(), 400);
    }, 5000);
}

// Client-side validation
document.querySelector('form').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const price = parseFloat(document.getElementById('price').value);
    const stock = parseInt(document.getElementById('stock').value);
    const description = document.getElementById('description').value.trim();
    const image = document.getElementById('image').files.length;
    
    let errors = [];
    
    // Product name validation
    if (name.length < 3) {
        errors.push('Product name must be at least 3 letters');
    }
    
    // Price validation
    if (isNaN(price) || price <= 1) {
        errors.push('Price must be greater than ₹1');
    }
    
    // Stock validation
    if (isNaN(stock) || stock <= 1) {
        errors.push('Stock must be greater than 1');
    }
    
    // Description validation
    if (description.length === 0) {
        errors.push('Description is required');
    }
    
    // Image validation
    if (image === 0) {
        errors.push('Product image is required');
    }
    
    if (errors.length > 0) {
        e.preventDefault();
        alert('Please fix the following errors:\n\n' + errors.join('\n'));
        return false;
    }
});

// Real-time validation feedback
document.getElementById('name').addEventListener('input', function() {
    if (this.value.trim().length > 0 && this.value.trim().length < 3) {
        this.style.borderColor = '#ff6b6b';
    } else {
        this.style.borderColor = '#e2e8f0';
    }
});

document.getElementById('price').addEventListener('input', function() {
    const value = parseFloat(this.value);
    if (!isNaN(value) && value <= 1) {
        this.style.borderColor = '#ff6b6b';
    } else {
        this.style.borderColor = '#e2e8f0';
    }
});

document.getElementById('stock').addEventListener('input', function() {
    const value = parseInt(this.value);
    if (!isNaN(value) && value <= 1) {
        this.style.borderColor = '#ff6b6b';
    } else {
        this.style.borderColor = '#e2e8f0';
    }
});
</script>

</body>
</html>
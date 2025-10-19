<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<header>
  <nav style="display:flex; justify-content:space-between; align-items:center; padding:1rem 5vw; background:#fff; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
    <div class="logo" style="font-size:1.8rem; color:#f98293; font-weight:bold;">mommycare</div>
    <ul style="list-style:none; display:flex; gap:2rem; margin:0; padding:0;">
      <li><a href="index.php" style="text-decoration:none; color:#42375a; font-weight:500;">Home</a></li>
      <li><a href="orders.php" style="text-decoration:none; color:#42375a; font-weight:500;">Orders</a></li>
      <li><a href="products.php" style="text-decoration:none; color:#42375a; font-weight:500;">Products</a></li>
    </ul>
    <div>
      <?php if(isset($_SESSION['user_name'])): ?>
        <span style="font-weight:bold; margin-right:1rem;">Hello, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a href="logout.php" style="text-decoration:none; background:#f98293; color:#fff; padding:0.5rem 1rem; border-radius:20px;">Logout</a>
      <?php else: ?>
        <a href="login.php" style="text-decoration:none; background:#f98293; color:#fff; padding:0.5rem 1rem; border-radius:20px;">Login</a>
      <?php endif; ?>
    </div>
  </nav>
</header>

<?php
// header.php
if(session_status() == PHP_SESSION_NONE) session_start();
require_once './includes/database.php';
require_once './includes/config.php';

// Kết nối DB
$db = new Database();
$conn = $db->connect();

// Tính tổng sản phẩm trong giỏ hàng
$cartCount = 0;
if(isset($_SESSION['user'])){
    $user_id = $_SESSION['user']['id_ND'];
    $stmt = $conn->prepare("
        SELECT SUM(ctgh.so_Luong) AS total
        FROM gio_hang gh
        JOIN gio_hang_chi_tiet ctgh ON gh.id_GH = ctgh.id_GH
        WHERE gh.id_ND = ?
    ");
    $stmt->execute([$user_id]);
    $cartCount = $stmt->fetchColumn() ?? 0;
}
?>

<header class="top-header" style="display:flex; align-items:center; justify-content:space-between; padding:10px 20px; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
    <!-- Logo -->
    <div class="logo">
        <a href="index.php">
            <img src="https://file.hstatic.net/1000253775/file/logo_no_bf-05_3e6797f31bda4002a22464d6f2787316.png" alt="Logo" style="height:50px;">
        </a>
    </div>

    <!-- Thanh tìm kiếm -->
    <div class="search-box" style="flex:1; margin:0 20px;">
        <form action="" method="GET" style="display:flex;">
            <input type="text" name="search" placeholder="Bạn đang tìm gì..." value="<?= htmlspecialchars($search ?? '') ?>" style="flex:1; padding:6px 10px; ">
            <button type="submit" style="padding:6px 12px; border:none; background:#4361ee; color:#fff; border-radius:0 4px 4px 0; cursor:pointer;">
                <i class="fas fa-search"></i>
            </button>
        </form>
    </div>

    <!-- Icon -->
    <div class="header-icons" style="display:flex; align-items:center; gap:15px;">
        <?php if(isset($_SESSION['user'])): ?>
            <a href="index.php?page=profile" style="text-decoration:none; color:#333;">
                <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['user']['ho_Ten'] ?? 'Người dùng') ?>
            </a>
            <a href="logout.php" style="text-decoration:none; color:#333;">
                <i class="fas fa-sign-out-alt"></i> Đăng xuất
            </a>
        <?php else: ?>
            <a href="login.php" style="text-decoration:none; color:#333;">
                <i class="fas fa-user"></i> Đăng nhập
            </a>
        <?php endif; ?>

        <!-- Giỏ hàng -->
        <a href="index.php?page=gioHang" style="position:relative; text-decoration:none; color:#333;">
            <i class="fas fa-shopping-cart" style="font-size:18px;"></i> Giỏ hàng
            <span id="cartCountBadge" style="
                background:red;
                color:white;
                padding:2px 6px;
                border-radius:50%;
                font-size:12px;
                position:absolute;
                top:-6px;
                right:-10px;
                <?= $cartCount==0?'display:none;':'' ?>
            "><?= $cartCount ?></span>
        </a>
    </div>
</header>

<script>
// Hàm cập nhật badge giỏ hàng realtime
function updateCartCount(count){
    const badge = document.getElementById('cartCountBadge');
    if(count > 0){
        badge.textContent = count;
        badge.style.display = 'inline-block';
    } else {
        badge.style.display = 'none';
    }
}

// Nếu bạn dùng form POST thêm vào giỏ hàng từ chiTietSanPham.php
document.addEventListener('DOMContentLoaded', function(){
    const cartForms = document.querySelectorAll('form.add-to-cart-form');
    cartForms.forEach(form => {
        form.addEventListener('submit', function(e){
            e.preventDefault();
            const formData = new FormData(this);
            fetch(this.action, { method:'POST', body:formData })
            .then(res => res.json())
            .then(data=>{
                if(data.cartCount !== undefined){
                    updateCartCount(data.cartCount);
                }
                alert('Đã thêm vào giỏ hàng!');
            });
        });
    });
});
</script>

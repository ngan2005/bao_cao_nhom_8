<?php
session_start();
require_once './includes/database.php';
require_once './includes/config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
if ($_SESSION['user']['vai_Tro'] !== 'admin') {
    header("Location: TrangChu.php");
    exit;
}

// DB
$db = new Database();
$conn = $db->connect();

// Lấy thông tin admin
$stmt = $conn->prepare("SELECT ho_Ten, email FROM nguoi_dung WHERE id_ND = ?");
$stmt->execute([$_SESSION['user']['id_ND']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// ROUTING
$page = $_GET['page'] ?? 'quanLySanPham';

$valid_pages = [
    'quanLySanPham' => ['file' => 'pages/admin/quanLySanPham.php', 'title' => 'Quản lý sản phẩm', 'icon' => '<i class="fas fa-plus-circle"></i>'],
    'quanLyDanhMuc' => ['file' => 'pages/admin/quanLyDanhMuc.php', 'title' => 'Quản lý danh mục', 'icon' => '<i class="fas fa-folder-open"></i>'],
    'quanLyMaGiamGia' => ['file' => 'pages/admin/quanLyMaGiamGia.php', 'title' => 'Mã giảm giá', 'icon' => '<i class="fas fa-ticket-alt"></i>'],
    'quanLyDonHang' => ['file' => 'pages/admin/quanLyDonHang.php', 'title' => 'Quản lý đơn hàng', 'icon' => '<i class="fas fa-receipt"></i>'],
    'quanLyTaiKhoan' => ['file' => 'pages/admin/quanLyTaiKhoan.php', 'title' => 'Quản lý tài khoản', 'icon' => '<i class="fas fa-users-cog"></i>'],
    'quanLyBinhLuan' => ['file' => 'pages/admin/quanLy_binh_luan.php', 'title' => 'Quản lý bình luận', 'icon' => '<i class="fas fa-comments"></i>'],
    'quanLyDanhThu' => ['file' => 'pages/admin/quanLyDanhThu.php', 'title' => 'Quản lý doanh thu', 'icon' => '<i class="fas fa-chart-line"></i>']
];

if (!array_key_exists($page, $valid_pages)) {
    $page = 'quanLySanPham';
}

$current_page = $valid_pages[$page];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin | <?= $current_page['title'] ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/Admin_css/adminLayout.css">
</head>

<body>
<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="logo">
        <h2><a href="../160STORE/index.php?page=TrangChu">

        <img src="https://file.hstatic.net/1000253775/file/logo_no_bf-05_3e6797f31bda4002a22464d6f2787316.png">
       </a></h2>
    </div>

    <div class="admin-info">
        <div class="admin-avatar"><i class="fas fa-user-shield"></i></div>
        <h3><?= $admin['ho_Ten'] ?></h3>
        <p><?= $admin['email'] ?></p>
    </div>

    <ul class="nav-menu">
        <?php foreach ($valid_pages as $key => $info): ?>
            <li class="<?= $page === $key ? 'active' : '' ?>">
                <a href="?page=<?= $key ?>">
                    <?= $info['icon'] ?> <span><?= $info['title'] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">
    <div class="topbar">
        <h1><?= $current_page['icon'] ?> <?= $current_page['title'] ?></h1>
        <button onclick="location.href='logout.php'" class="logout"><i class="fas fa-sign-out-alt"></i> Đăng xuất</button>
    </div>

    <div class="page-area">
        <?php include $current_page['file']; ?>
    </div>
</main>
</body>
</html>

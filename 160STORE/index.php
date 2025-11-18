<?php
$page = $_GET['page'] ?? 'TrangChu';

$pages = [
    'TrangChu' => 'TrangChu.php',
    'danhSachCombo' => 'pages/danhSachCombo.php',
    'danhSachAoNam' => 'pages/danhSachAoNam.php',
    'danhSachQuanNam' => 'pages/danhSachQuanNam.php',
    'danhSachPhuKien' => 'pages/danhSachPhuKien.php',
    'profile' => 'pages/profile.php',
    'gioHang' => 'pages/gioHang.php',
    'chiTietSanPham' => 'pages/chiTietSanPham.php',
    'checkout' => 'pages/checkout.php',
];

include __DIR__ . '/layouts/header.php';
include __DIR__ . '/layouts/navbar.php';

if (array_key_exists($page, $pages)) {
    // ✅ Trang chủ chỉ được load khi page là 'TrangChu' hoặc không có query
    include __DIR__ . '/' . $pages[$page];
} else {
    echo "<h1>404 - Trang không tồn tại</h1>";
}

include __DIR__ . '/layouts/footer.php';
?>
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/stylee.css">
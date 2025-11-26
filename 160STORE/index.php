<?php
/* ===== ROUTER CỦA TRANG CHÍNH (CLIENT SIDE) ===== */
/**
 * FILE NÀY LÀ ROUTER CHO TRANG KHÁCH HÀNG (không phải admin)
 * Nó lấy tham số 'page' từ URL (?page=...) và tải trang tương ứng
 * 
 * KHÁC BIỆT với admin.php:
 * - admin.php: điều hướng các trang quản trị (quản lý sản phẩm, bình luận, v.v.)
 * - index.php: điều hướng các trang khách hàng (danh sách sản phẩm, giỏ hàng, thanh toán, v.v.)
 */

// Lấy tham số 'page' từ URL (?page=...)
// Nếu không có tham số → mặc định là 'TrangChu' (trang chủ)
// Ví dụ:
//   index.php?page=gioHang → hiển thị trang giỏ hàng
//   index.php              → hiển thị trang chủ (TrangChu)
$page = $_GET['page'] ?? 'TrangChu';

/* ===== BẢNG ĐỊNH TUYẾN (ROUTING TABLE) ===== */
/**
 * Danh sách tất cả trang khách hàng hợp lệ
 * 
 * Cấu trúc:
 * 'key_trang' => 'đường_dẫn_file'
 * 
 * Để thêm trang mới, chỉ cần thêm một dòng:
 * 'tenTrang' => 'pages/tenFile.php'
 */
$pages = [
    // Trang chủ
    'TrangChu' => 'TrangChu.php',
    // Danh sách các loại sản phẩm
    'danhSachCombo' => 'pages/danhSachCombo.php',      // Combo sản phẩm
    'danhSachAoNam' => 'pages/danhSachAoNam.php',      // Áo nam
    'danhSachQuanNam' => 'pages/danhSachQuanNam.php',  // Quần nam
    'danhSachPhuKien' => 'pages/danhSachPhuKien.php',  // Phụ kiện
    // Trang người dùng
    'profile' => 'pages/profile.php',                  // Hồ sơ cá nhân
    'gioHang' => 'pages/gioHang.php',                  // Giỏ hàng
    // Trang sản phẩm chi tiết
    'chiTietSanPham' => 'pages/chiTietSanPham.php',    // Chi tiết sản phẩm
    // Trang thanh toán
    'checkout' => 'pages/checkout.php',                // Thanh toán
];

/* ===== INCLUDE LAYOUT CẶP: HEADER VÀ NAVBAR ===== */
// Nạp header (chứa HTML head, CSS, meta tags)
include __DIR__ . '/layouts/header.php';
// Nạp navbar (thanh điều hướng ở trên trang)
include __DIR__ . '/layouts/navbar.php';

/* ===== ROUTER: LỰA CHỌN VÀ INCLUDE TRANG THÍCH HỢP ===== */
/**
 * Kiểm tra tham số 'page' có trong bảng định tuyến không
 */
if (array_key_exists($page, $pages)) {
    // ✅ NẾU TRANG HỢP LỆ: Include file tương ứng
    // Ví dụ: nếu $page = 'gioHang' → include 'pages/gioHang.php'
    include __DIR__ . '/' . $pages[$page];
} else {
    // ❌ NẾU TRANG KHÔNG HỢP LỆ: Hiển thị lỗi 404
    echo "<h1>404 - Trang không tồn tại</h1>";
}

// Nạp footer (chứa thông tin cuối trang, script)
include __DIR__ . '/layouts/footer.php';
?>
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/stylee.css">
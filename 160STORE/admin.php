<?php
/* ===== KHỞI TẠO SESSION & NẠP CÁC FILE CẦN THIẾT ===== */
// Bắt đầu session để sử dụng $_SESSION
session_start();
// Nạp lớp Database để kết nối MySQL
require_once './includes/database.php';
// Nạp file cấu hình chung
require_once './includes/config.php';

/* ===== KIỂM TRA QUYỀN ADMIN ===== */
// Nếu chưa đăng nhập (_SESSION['user'] chưa tồn tại), chuyển hướng về trang login
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit; // Dừng script để tránh tiếp tục chạy code phía dưới
}

// Nếu đã đăng nhập nhưng không phải admin (vai_Tro !== 'admin'), chuyển hướng về trang chủ
if ($_SESSION['user']['vai_Tro'] !== 'admin') {
    header("Location: TrangChu.php");
    exit;
}

/* ===== KẾT NỐI DATABASE VÀ LẤY THÔNG TIN ADMIN ===== */
// Tạo đối tượng Database để kết nối
$db = new Database();
$conn = $db->connect();

// Lấy thông tin admin hiện tại (tên đầy đủ và email) từ bảng nguoi_dung
$stmt = $conn->prepare("SELECT ho_Ten, email FROM nguoi_dung WHERE id_ND = ?");
$stmt->execute([$_SESSION['user']['id_ND']]); // Tham số là ID người dùng đang đăng nhập
$admin = $stmt->fetch(PDO::FETCH_ASSOC); // Lấy kết quả dưới dạng mảng

/* ===== ROUTER: XỬ LÝ ĐIỀU HƯỚNG TRANG ===== */
/**
 * ROUTER: Lấy tham số 'page' từ URL (?page=...) để xác định trang nào cần hiển thị
 * Giá trị mặc định là 'quanLySanPham' nếu không được chỉ định
 * 
 * Ví dụ:
 * - admin.php?page=quanLySanPham  → hiển thị trang quản lý sản phẩm
 * - admin.php?page=quanLyBinhLuan → hiển thị trang quản lý bình luận
 * - admin.php (không tham số)     → mặc định hiển thị quản lý sản phẩm
 */
$page = $_GET['page'] ?? 'quanLySanPham';

/**
 * BẢNG ĐỊNH TUYẾN (Routing Table)
 * Chứa danh sách tất cả các trang admin hợp lệ + thông tin của chúng
 * 
 * Cấu trúc mỗi mục:
 * 'key_trang' => [
 *   'file'  => đường dẫn tới file PHP cần include
 *   'title' => tiêu đề trang (hiển thị ở topbar và sidebar)
 *   'icon'  => HTML của icon FontAwesome
 * ]
 */
$valid_pages = [
    // Trang quản lý sản phẩm
    'quanLySanPham' => [
        'file' => 'pages/admin/quanLySanPham.php', 
        'title' => 'Quản lý sản phẩm', 
        'icon' => '<i class="fas fa-plus-circle"></i>'
    ],
    // Trang quản lý danh mục sản phẩm
    'quanLyDanhMuc' => [
        'file' => 'pages/admin/quanLyDanhMuc.php', 
        'title' => 'Quản lý danh mục', 
        'icon' => '<i class="fas fa-folder-open"></i>'
    ],
    // Trang quản lý mã giảm giá
    'quanLyMaGiamGia' => [
        'file' => 'pages/admin/quanLyMaGiamGia.php', 
        'title' => 'Mã giảm giá', 
        'icon' => '<i class="fas fa-ticket-alt"></i>'
    ],
    // Trang quản lý đơn hàng
    'quanLyDonHang' => [
        'file' => 'pages/admin/quanLyDonHang.php', 
        'title' => 'Quản lý đơn hàng', 
        'icon' => '<i class="fas fa-receipt"></i>'
    ],
    // Trang quản lý tài khoản người dùng
    'quanLyTaiKhoan' => [
        'file' => 'pages/admin/quanLyTaiKhoan.php', 
        'title' => 'Quản lý tài khoản', 
        'icon' => '<i class="fas fa-users-cog"></i>'
    ],
    // Trang quản lý bình luận sản phẩm
    'quanLyBinhLuan' => [
        'file' => 'pages/admin/quanLy_binh_luan.php', 
        'title' => 'Quản lý bình luận', 
        'icon' => '<i class="fas fa-comments"></i>'
    ],
    // Trang quản lý doanh thu
    'quanLyDanhThu' => [
        'file' => 'pages/admin/quanLyDanhThu.php', 
        'title' => 'Quản lý doanh thu', 
        'icon' => '<i class="fas fa-chart-line"></i>'
    ]
];

/**
 * KIỂM TRA HỢP LỆ: Nếu tham số 'page' không có trong $valid_pages,
 * đặt lại về trang mặc định (quanLySanPham) để tránh lỗi
 */
if (!array_key_exists($page, $valid_pages)) {
    $page = 'quanLySanPham';
}

// Lấy thông tin chi tiết của trang hiện tại từ bảng định tuyến
$current_page = $valid_pages[$page];
?>
<!-- ===== TRANG HTML: GIAO DIỆN ADMIN ===== -->
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <!-- Tiêu đề trang = "Admin | " + tên trang hiện tại (ví dụ: "Admin | Quản lý sản phẩm") -->
    <title>Admin | <?= $current_page['title'] ?></title>

    <!-- Nạp CSS FontAwesome 6.5.0 cho icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Nạp font Google: Inter (nhiều độ đậm) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Nạp CSS tùy chỉnh cho layout admin -->
    <link rel="stylesheet" href="assets/Admin_css/adminLayout.css">
</head>

<body>
<!-- ===== SIDEBAR (THANH BÊN TRÁI) ===== -->
<aside class="sidebar">
    <!-- LOGO & TIÊU ĐỀ -->
    <div class="logo">
        <!-- Logo hiển thị ở sidebar, click để về trang chủ -->
        <h2><a href="../160STORE/index.php?page=TrangChu">
            <img src="https://file.hstatic.net/1000253775/file/logo_no_bf-05_3e6797f31bda4002a22464d6f2787316.png" alt="160STORE Logo">
        </a></h2>
    </div>

    <!-- THÔNG TIN ADMIN -->
    <div class="admin-info">
        <!-- Avatar icon (trái: icon, phải: tên + email) -->
        <div class="admin-avatar"><i class="fas fa-user-shield"></i></div>
        <!-- Tên đầy đủ admin (lấy từ DB) -->
        <h3><?= $admin['ho_Ten'] ?></h3>
        <!-- Email admin (lấy từ DB) -->
        <p><?= $admin['email'] ?></p>
    </div>

    <!-- MENU ĐIỀU HƯỚNG -->
    <ul class="nav-menu">
        <!-- Lặp qua tất cả các trang trong $valid_pages để tạo menu items -->
        <?php foreach ($valid_pages as $key => $info): ?>
            <li class="<?= $page === $key ? 'active' : '' ?>">
                <!-- Mỗi mục là một link đến trang tương ứng (ví dụ: ?page=quanLySanPham) -->
                <!-- Thêm class 'active' nếu trang hiện tại khớp với mục này -->
                <a href="?page=<?= $key ?>">
                    <!-- Hiển thị icon + tiêu đề trang -->
                    <?= $info['icon'] ?> <span><?= $info['title'] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</aside>

<!-- ===== MAIN CONTENT AREA (NỘI DUNG CHÍNH) ===== -->
<main class="main-content">
    <!-- TOPBAR (THANH TRÊN) -->
    <div class="topbar">
        <!-- Tiêu đề trang + icon -->
        <h1><?= $current_page['icon'] ?> <?= $current_page['title'] ?></h1>
        <!-- Nút đăng xuất -->
        <button onclick="location.href='logout.php'" class="logout">
            <i class="fas fa-sign-out-alt"></i> Đăng xuất
        </button>
    </div>

    <!-- VỊ TRÍ INCLUDE FILE NỘI DUNG TRANG -->
    <div class="page-area">
        <!-- Dòng dưới sẽ INCLUDE file tương ứng với trang được chọn -->
        <!-- Ví dụ: nếu ?page=quanLySanPham → include pages/admin/quanLySanPham.php -->
        <!-- Đây chính là cơ chế ROUTER: khi người dùng click vào menu, file tương ứng được tải vào đây -->
        <?php include $current_page['file']; ?>
    </div>
</main>
</body>
</html>

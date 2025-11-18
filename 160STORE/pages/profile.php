<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php?page=login");
    exit;
}

$db = new Database();
$conn = $db->connect();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$user = $_SESSION['user'];
$msg = "";

// LẤY THÔNG TIN MỚI NHẤT
$stmt = $conn->prepare("SELECT * FROM nguoi_dung WHERE id_ND = ?");
$stmt->execute([$user['id_ND']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// CẬP NHẬT THÔNG TIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $ho_Ten = trim($_POST['ho_Ten']);
    $email = trim($_POST['email']);
    $sdt = trim($_POST['sdt']);
    $dia_Chi = trim($_POST['dia_Chi']);
    $mat_Khau = trim($_POST['mat_Khau']);

    try {
        if (empty($mat_Khau)) {
            $stmt = $conn->prepare("UPDATE nguoi_dung SET ho_Ten=?, email=?, sdt=?, dia_Chi=? WHERE id_ND=?");
            $stmt->execute([$ho_Ten, $email, $sdt, $dia_Chi, $user['id_ND']]);
        } else {
            $stmt = $conn->prepare("UPDATE nguoi_dung SET ho_Ten=?, email=?, sdt=?, dia_Chi=?, mat_Khau=? WHERE id_ND=?");
            $stmt->execute([$ho_Ten, $email, $sdt, $dia_Chi, $mat_Khau, $user['id_ND']]);
        }
        $msg = "<div class='alert alert-success'>Cập nhật thành công!</div>";

        $_SESSION['user']['ho_Ten'] = $ho_Ten;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['sdt'] = $sdt;
        $_SESSION['user']['dia_Chi'] = $dia_Chi;
    } catch (Exception $e) {
        $msg = "<div class='alert alert-danger'>Lỗi: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// LẤY ĐƠN HÀNG CỦA USER
$stmt = $conn->prepare("SELECT * FROM don_hang WHERE id_ND = ? ORDER BY id_DH DESC");
$stmt->execute([$user['id_ND']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container py-4">
    <!-- Thông tin user -->
    <div class="card shadow-sm mb-4">
        <div class="card-body text-center">
            <div class="avatar rounded-circle bg-primary text-white fs-1 mb-2" style="width:80px;height:80px;line-height:80px;margin:auto;">
                <?= strtoupper(substr($user['ho_Ten'], 0, 1)) ?>
            </div>
            <h3><?= htmlspecialchars($user['ho_Ten']) ?></h3>
            <p class="text-muted"><?= htmlspecialchars($user['email']) ?></p>
            <span class="badge bg-<?= $user['vai_Tro']==='admin'?'danger':'secondary' ?>">
                <?= $user['vai_Tro']==='admin'?'QUẢN TRỊ VIÊN':'KHÁCH HÀNG' ?>
            </span>
        </div>
    </div>

    <?= $msg ?>

    <!-- Form cập nhật thông tin -->
    <div class="card mb-4">
        <div class="card-header">Thông tin cá nhân</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="update_profile" value="1">
                <div class="mb-3">
                    <label class="form-label">Tên đăng nhập</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['ten_Dang_Nhap']) ?>" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Họ và tên</label>
                    <input type="text" name="ho_Ten" class="form-control" value="<?= htmlspecialchars($user['ho_Ten']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" name="sdt" class="form-control" value="<?= htmlspecialchars($user['sdt']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Địa chỉ nhận hàng</label>
                    <input type="text" name="dia_Chi" class="form-control" value="<?= htmlspecialchars($user['dia_Chi']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Mật khẩu mới (để trống nếu không đổi)</label>
                    <input type="password" name="mat_Khau" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary">LƯU THAY ĐỔI</button>
            </form>
        </div>
    </div>

    <!-- Bảng đơn hàng -->
    <div class="card">
        <div class="card-header">Danh sách đơn hàng của bạn</div>
        <div class="card-body table-responsive">
            <table class="table table-striped table-bordered text-center align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Ngày đặt</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th>Địa chỉ giao</th>
                        <th>Sản phẩm & chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($orders)): ?>
                    <tr><td colspan="6">Bạn chưa có đơn hàng nào.</td></tr>
                <?php else: ?>
                    <?php
                    foreach($orders as $o):
                        // Lấy chi tiết sản phẩm cho từng đơn hàng
                        $stmt = $conn->prepare("
                            SELECT cthd.*, sp.ten_San_Pham
                            FROM chi_tiet_hoa_don cthd
                            LEFT JOIN san_pham sp ON cthd.id_SP = sp.id_SP
                            WHERE cthd.id_DH = ?
                        ");
                        $stmt->execute([$o['id_DH']]);
                        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                        <tr>
                            <td><?= $o['id_DH'] ?></td>
                            <td><?= $o['ngay_Dat'] ?></td>
                            <td><?= number_format($o['tong_Tien'],0,',','.') ?></td>
                            <td><?= htmlspecialchars($o['trang_Thai']) ?></td>
                            <td><?= htmlspecialchars($o['dia_Chi_Giao']) ?></td>
                            <td>
                                <ul class="list-group list-group-flush">
                                    <?php foreach($details as $d): ?>
                                        <li class="list-group-item">
                                            <?= htmlspecialchars($d['ten_San_Pham'] ?? '-') ?> -
                                            <?= htmlspecialchars($d['mau_sac'] ?? '-') ?> -
                                            <?= htmlspecialchars($d['kich_thuoc'] ?? '-') ?> -
                                            Số lượng: <?= $d['so_Luong'] ?? 0 ?> -
                                            Giá: <?= number_format($d['gia_Ban'] ?? 0,0,',','.') ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <a href="index.php?page=TrangChu" class="btn btn-secondary mt-3">Quay lại trang chủ</a>
</div>

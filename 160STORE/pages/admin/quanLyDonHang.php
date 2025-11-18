<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/config.php';

$db = new Database();
$conn = $db->connect();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// =================== CẬP NHẬT TRẠNG THÁI ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $stmt = $conn->prepare("UPDATE don_hang SET trang_Thai = ? WHERE id_DH = ?");
    $stmt->execute([$_POST['trang_Thai'], $_POST['id_DH']]);
}

// =================== XÓA ĐƠN HÀNG ===================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->prepare("DELETE FROM chi_tiet_hoa_don WHERE id_DH=?")->execute([$id]);
    $conn->prepare("DELETE FROM don_hang WHERE id_DH=?")->execute([$id]);
}

// =================== ĐƠN TRONG 2 GIỜ ===================
$newOrders = $conn->query("
    SELECT id_DH, tong_Tien, ngay_Dat
    FROM don_hang
    WHERE ngay_Dat >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY ngay_Dat DESC
")->fetchAll(PDO::FETCH_ASSOC);

$newCount = count($newOrders);

// =================== LẤY DANH SÁCH ĐƠN ===================
$orders = $conn->query("SELECT * FROM don_hang ORDER BY id_DH DESC")->fetchAll(PDO::FETCH_ASSOC);
$statuses = ['Chờ xác nhận', 'Đang giao', 'Đã giao', 'Đã hủy'];
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.dropdown-menu {
    width: 260px;
}
.dropdown-item:hover {
    background: #eee;
}
</style>

<div class="container mt-4">

    <!-- 🔔 CHUÔNG THÔNG BÁO -->
    <div class="d-flex justify-content-end mb-3">
        <div class="dropdown">
            <button class="btn btn-outline-dark position-relative" data-bs-toggle="dropdown">
                <i class="fa-solid fa-bell fa-lg"></i>

                <?php if ($newCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?= $newCount ?>
                </span>
                <?php endif; ?>
            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow">     
                <li class="dropdown-header fw-bold">🔔 Đơn hàng mới trong 2 giờ</li>

                <?php if ($newCount == 0): ?>
                    <li><span class="dropdown-item-text text-muted">Không có đơn mới</span></li>
                <?php else: ?>
                    <?php foreach ($newOrders as $order): ?>
                        <li>
                            <a class="dropdown-item" href="#dh-<?= $order['id_DH'] ?>">
                                <b>#<?= $order['id_DH'] ?></b> |
                                <?= number_format($order['tong_Tien'], 0, ',', '.') ?> đ |
                                <?= date("H:i", strtotime($order['ngay_Dat'])) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <h2>Quản lý đơn hàng</h2>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Ngày đặt</th>
                <th>Tổng tiền</th>
                <th>Trạng thái</th>
                <th>Địa chỉ giao</th>
                <th>Thao tác</th>
            </tr>
        </thead>

        <tbody>

        <?php foreach ($orders as $o): 
            $details_id = $o['id_DH'];

            // --- Lấy chi tiết sản phẩm ---
            $stmt = $conn->prepare("
                SELECT cthd.*, sp.ten_San_Pham
                FROM chi_tiet_hoa_don cthd
                LEFT JOIN san_pham sp ON cthd.id_SP = sp.id_SP
                WHERE cthd.id_DH=?
            ");
            $stmt->execute([$details_id]);
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- Lấy thông tin người nhận ---
            $stmt2 = $conn->prepare("
                SELECT nd.ho_ten, nd.email, nd.sdt, dh.dia_Chi_Giao, dh.tong_tien, dh.ma_Giam_Gia
                FROM don_hang dh
                LEFT JOIN nguoi_dung nd ON dh.id_ND=nd.id_ND
                WHERE dh.id_DH=?
            ");
            $stmt2->execute([$details_id]);
            $user_info = $stmt2->fetch(PDO::FETCH_ASSOC);
        ?>

            <tr id="dh-<?= $o['id_DH'] ?>">
                <td><b>#<?= $o['id_DH'] ?></b></td>
                <td><?= date('d/m/Y H:i', strtotime($o['ngay_Dat'])) ?></td>
                <td><?= number_format($o['tong_Tien'], 0, ',', '.') ?> đ</td>

                <td>
                    <form method="POST">
                        <select name="trang_Thai" class="form-select form-select-sm"
                                onchange="this.form.submit()">
                            <?php foreach ($statuses as $st): ?>
                                <option value="<?= $st ?>" <?= $st == $o['trang_Thai'] ? 'selected' : '' ?>>
                                    <?= $st ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="id_DH" value="<?= $o['id_DH'] ?>">
                    </form>
                </td>

                <td><?= htmlspecialchars($o['dia_Chi_Giao']) ?></td>

                <td>
                    <button class="btn btn-info btn-sm" data-bs-toggle="collapse" data-bs-target="#details-<?= $details_id ?>">
                        Xem chi tiết
                    </button>
                    <a href="?page=quanLyDonHang&delete=<?= $o['id_DH'] ?>"
                       onclick="return confirm('Bạn chắc muốn xóa?')"
                       class="btn btn-danger btn-sm">Xóa</a>
                </td>
            </tr>

            <!-- ======== CHI TIẾT HÓA ĐƠN ======== -->
            <tr class="collapse" id="details-<?= $details_id ?>">
                <td colspan="6">
                    <div class="card card-body">

                        <b>Người nhận:</b> <?= $user_info['ho_ten'] ?> <br>
                        <b>Email:</b> <?= $user_info['email'] ?> <br>
                        <b>SĐT:</b> <?= $user_info['sdt'] ?> <br>
                        <b>Địa chỉ:</b> <?= $user_info['dia_Chi_Giao'] ?> <br>
                        <b>Tổng tiền:</b> <?= number_format($user_info['tong_tien'],0,',','.') ?> đ <br>
                        <b>Voucher:</b> <?= $user_info['ma_Giam_Gia'] ?? "Không" ?> <br>

                        <hr>

                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th>Màu / Size</th>
                                    <th>SL</th>
                                    <th>Đơn giá</th>
                                    <th>Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($details as $d): ?>
                                <tr>
                                    <td><?= $d['ten_San_Pham'] ?></td>
                                    <td><?= $d['mau_sac'] . " / " . $d['kich_thuoc'] ?></td>
                                    <td><?= $d['so_Luong'] ?></td>
                                    <td><?= number_format($d['gia_Ban']) ?> đ</td>
                                    <td><?= number_format($d['gia_Ban'] * $d['so_Luong']) ?> đ</td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>

                    </div>
                </td>
            </tr>

        <?php endforeach; ?>

        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

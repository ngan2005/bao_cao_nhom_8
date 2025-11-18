<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/config.php';

$db = new Database();
$conn = $db->connect();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Kiểm tra login
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "Bạn cần đăng nhập trước";
    header("Location: index.php?page=login");
    exit;
}

$user_id = $_SESSION['user']['id_ND'];

// Xử lý cập nhật số lượng
if (isset($_POST['update_qty'])) {
    $id_ct = intval($_POST['id_GHCT']);
    $qty = max(1, intval($_POST['quantity']));
    $stmt = $conn->prepare("UPDATE gio_hang_chi_tiet SET so_Luong = :so_Luong WHERE id_GHCT = :id_GHCT");
    $stmt->execute([':so_Luong' => $qty, ':id_GHCT' => $id_ct]);
    header("Location: index.php?page=gioHang");
    exit;
}

// Xử lý xóa sản phẩm
if (isset($_GET['delete'])) {
    $id_ct = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM gio_hang_chi_tiet WHERE id_GHCT = ?");
    $stmt->execute([$id_ct]);
    header("Location: index.php?page=gioHang");
    exit;
}

// Lấy giỏ hàng của user
$stmt = $conn->prepare("SELECT * FROM gio_hang WHERE id_ND = :id_ND");
$stmt->bindParam(':id_ND', $user_id);
$stmt->execute();
$cart = $stmt->fetch(PDO::FETCH_ASSOC);

$cart_with_product = [];
$total = 0;

if ($cart) {
    $cart_id = $cart['id_GH'];
    $stmt2 = $conn->prepare("SELECT * FROM gio_hang_chi_tiet WHERE id_GH = :id_GH");
    $stmt2->bindParam(':id_GH', $cart_id);
    $stmt2->execute();
    $cart_items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cart_items as $item) {
        $stmt3 = $conn->prepare("SELECT gia_Ban, hinh_Anh, ten_san_pham FROM san_pham WHERE id_SP = :id_SP");
        $stmt3->bindParam(':id_SP', $item['id_SP']);
        $stmt3->execute();
        $product = $stmt3->fetch(PDO::FETCH_ASSOC);
        if ($product) {
            $item = array_merge($item, $product);
            $total += $product['gia_Ban'] * $item['so_Luong'];
            $cart_with_product[] = $item;
        }
    }
}
?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- MAIN CONTENT -->
<div class="container my-5">
    <h2 class="mb-4">Giỏ Hàng Của Bạn</h2>

    <?php if (empty($cart_with_product)): ?>
        <div class="empty-cart alert alert-info text-center py-5">
            <i class="fas fa-shopping-cart fa-3x mb-2"></i>
            <p class="fs-5">Giỏ hàng của bạn đang trống.</p>
            <a href="index.php" class="btn btn-primary mt-2">Tiếp tục mua sắm</a>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle text-center mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Hình</th>
                            <th>Tên sản phẩm</th>
                            <th>Màu sắc</th>
                            <th>Size</th>
                            <th>Đơn giá</th>
                            <th>Số lượng</th>
                            <th>Thành tiền</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_with_product as $item): ?>
                            <?php $line_total = $item['gia_Ban'] * $item['so_Luong']; ?>
                            <tr>
                                <td>
                                    <img src="<?= htmlspecialchars($item['hinh_Anh']) ?>" class="img-thumbnail" style="width:80px; height:80px; object-fit:cover;">
                                </td>
                                <td class="text-start"><?= htmlspecialchars($item['ten_san_pham']) ?></td>
                                <td><?= htmlspecialchars($item['mau_sac']) ?></td>
                                <td><?= htmlspecialchars($item['kich_Thuoc']) ?></td>
                                <td class="text-danger fw-bold"><?= number_format($item['gia_Ban'],0,',','.') ?> VNĐ</td>
                                <td>
                                    <form method="POST" class="d-flex justify-content-center align-items-center gap-1">
                                        <input type="number" name="quantity" value="<?= $item['so_Luong'] ?>" min="1" class="form-control form-control-sm" style="width:60px;">
                                        <input type="hidden" name="id_GHCT" value="<?= $item['id_GHCT'] ?>">
                                        <button type="submit" name="update_qty" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </form>
                                </td>
                                <td class="fw-bold"><?= number_format($line_total,0,',','.') ?> VNĐ</td>
                                <td>
                                    <a href="index.php?page=gioHang&delete=<?= $item['id_GHCT'] ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Xóa sản phẩm này khỏi giỏ?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-secondary fw-bold">
                            <td colspan="6" class="text-end">Tổng tiền:</td>
                            <td colspan="2" class="text-danger fs-5"><?= number_format($total,0,',','.') ?> VNĐ</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card-footer text-end">
                <form action="index.php?page=checkout" method="POST">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-credit-card me-1"></i> Thanh toán
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

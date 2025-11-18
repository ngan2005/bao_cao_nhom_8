<?php
require_once './includes/database.php';

// 🔹 Kết nối database
$db = new Database();
$conn = $db->connect();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 🔹 Lấy danh sách mã giảm giá còn hạn
$sql = "SELECT * FROM ma_giam_gia WHERE ngay_Ket_Thuc >= CURDATE() ORDER BY ngay_Ket_Thuc ASC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- 🔸 PHẦN HIỂN THỊ MÃ GIẢM GIÁ -->
<section class="voucher-section">
    <blockquote><h3>🎁 Ưu Đãi Dành Cho Bạn</h3></blockquote>

    <?php if (empty($vouchers)): ?>
        <p style="text-align:center; color:#666;">Hiện chưa có mã giảm giá nào.</p>
    <?php else: ?>
        <div class="voucher-list">
            <?php foreach ($vouchers as $v): ?>
                <div class="voucher">
                    <div class="voucher-code">
                        <span>Mã giảm giá:</span> 
                        <strong><?= htmlspecialchars($v['ma_Giam_Gia']) ?></strong>
                    </div>
                    <div class="voucher-info">
                        <?= htmlspecialchars($v['mo_Ta']) ?><br>
                        <small>Áp dụng đến <?= date('d/m/Y', strtotime($v['ngay_Ket_Thuc'])) ?></small>
                    </div>
                    <button class="copy-btn" onclick="copyVoucher('<?= htmlspecialchars($v['ma_Giam_Gia']) ?>')">
                        <i class="fas fa-copy"></i> Sao chép mã
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

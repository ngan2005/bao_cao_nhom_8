<?php
// Không cần session_start hay connect lại nếu file này được include vào admin.php
// Nhưng giữ lại require để đảm bảo biến $db tồn tại nếu chạy độc lập
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/config.php';

$db = new Database();
$pdo = $db->connect();

// Lấy danh sách bình luận
$stmt = $pdo->prepare("
    SELECT 
        bl.id_BL, 
        bl.noi_Dung, 
        bl.so_Sao, 
        bl.ngay_Binh_Luan, 
        sp.ten_San_Pham, 
        nd.ten_Dang_Nhap, 
        sp.id_SP,
        (SELECT AVG(so_Sao) FROM binh_luan bl2 WHERE bl2.id_SP = sp.id_SP) as avg_rating,
        (SELECT COUNT(*) FROM binh_luan bl3 WHERE bl3.id_SP = sp.id_SP) as rating_count
    FROM binh_luan bl
    JOIN san_pham sp ON bl.id_SP = sp.id_SP
    JOIN nguoi_dung nd ON bl.id_ND = nd.id_ND
    ORDER BY bl.ngay_Binh_Luan DESC
");
$stmt->execute();
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hàm hỗ trợ vẽ ngôi sao
function renderStars($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $html .= '<i class="fas fa-star"></i>'; // Sao đầy
        } else {
            $html .= '<i class="far fa-star"></i>'; // Sao rỗng
        }
    }
    return $html;
}
?>

<link rel="stylesheet" href="assets/Admin_css/quanLyBinhLuan.css">

<div class="comment-management-container">
    <div class="page-header">
        <span>Quản lý Bình luận Sản phẩm</span>
        <span style="font-size: 14px; font-weight: normal; color: #64748b;">
            Tổng: <strong><?= count($comments) ?></strong> bình luận
        </span>
    </div>

    <div class="table-responsive">
        <table class="comment-table">
            <thead>
                <tr>
                    <th width="5%">ID</th>
                    <th width="20%">Người dùng & Sản phẩm</th>
                    <th width="35%">Nội dung bình luận</th>
                    <th width="15%">Đánh giá</th>
                    <th width="15%">Thống kê SP</th>
                    <th width="10%">Ngày</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($comments)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-comments"></i>
                            <p>Chưa có bình luận nào.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <tr>
                            <td class="id-col">#<?= $comment['id_BL'] ?></td>
                            
                            <td>
                                <span class="user-name">
                                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($comment['ten_Dang_Nhap']) ?>
                                </span>
                                <a href="#" class="product-name">
                                    <?= htmlspecialchars($comment['ten_San_Pham']) ?>
                                </a>
                            </td>

                            <td>
                                <div class="comment-content">
                                    <?= nl2br(htmlspecialchars($comment['noi_Dung'])) ?>
                                </div>
                            </td>

                            <td>
                                <div class="star-rating">
                                    <?= renderStars($comment['so_Sao']) ?>
                                </div>
                                <small style="color: #64748b; font-size: 11px;">(<?= $comment['so_Sao'] ?>/5)</small>
                            </td>

                            <td>
                                <div class="avg-badge" title="Trung bình đánh giá của sản phẩm này">
                                    <i class="fas fa-chart-bar"></i>
                                    <?= number_format($comment['avg_rating'], 1) ?>/5 
                                    (<?= $comment['rating_count'] ?> lượt)
                                </div>
                            </td>

                            <td class="date-time">
                                <i class="far fa-clock"></i> <?= date('d/m/Y', strtotime($comment['ngay_Binh_Luan'])) ?><br>
                                <?= date('H:i', strtotime($comment['ngay_Binh_Luan'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
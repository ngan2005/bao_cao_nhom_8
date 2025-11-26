<?php
/* ===== KHỞI TẠO VÀ KẾT NỐI DATABASE ===== */
// Nạp lớp Database và file cấu hình chung
// (Nếu file này được include vào admin.php thì session/connect đã có, nhưng require lại để chắc chắn)
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/config.php';

// Tạo đối tượng Database và kết nối đến MySQL
$db = new Database();
$pdo = $db->connect();

/* ===== TRUY VẤN DANH SÁCH BÌNH LUẬN VỚI ĐỦ THÔNG TIN ===== */
// Chuẩn bị câu SQL để lấy bình luận cùng với thông tin sản phẩm, người dùng, và thống kê rating
$stmt = $pdo->prepare("
    SELECT 
        bl.id_BL,                    -- ID của bình luận
        bl.noi_Dung,                 -- Nội dung bình luận
        bl.so_Sao,                   -- Số sao (1-5) của bình luận này
        bl.ngay_Binh_Luan,           -- Ngày giờ bình luận
        sp.ten_San_Pham,             -- Tên sản phẩm được bình luận
        nd.ten_Dang_Nhap,            -- Tên đăng nhập của người bình luận
        sp.id_SP,                    -- ID sản phẩm (để xây dựng link nếu cần)
        -- Tính trung bình đánh giá của toàn bộ bình luận cho sản phẩm này
        (SELECT AVG(so_Sao) FROM binh_luan bl2 WHERE bl2.id_SP = sp.id_SP) as avg_rating,
        -- Đếm tổng số bình luận cho sản phẩm này
        (SELECT COUNT(*) FROM binh_luan bl3 WHERE bl3.id_SP = sp.id_SP) as rating_count
    FROM binh_luan bl
    -- Tham gia bảng sản phẩm để lấy tên sản phẩm
    JOIN san_pham sp ON bl.id_SP = sp.id_SP
    -- Tham gia bảng người dùng để lấy tên người bình luận
    JOIN nguoi_dung nd ON bl.id_ND = nd.id_ND
    -- Sắp xếp theo ngày mới nhất trước
    ORDER BY bl.ngay_Binh_Luan DESC
");
// Thực hiện truy vấn (không có tham số nên execute rỗng)
$stmt->execute();
// Lấy tất cả kết quả dưới dạng mảng kết hợp
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===== HÀM HIỂN THỊ NGÔI SAO ===== */
/**
 * Vẽ ngôi sao dựa trên điểm đánh giá
 * @param int $rating - số sao (từ 1 đến 5)
 * @return string - HTML với icon sao đầy/rỗng
 * 
 * Ví dụ: rating=3 sẽ hiển thị 3 sao đầy + 2 sao rỗng
 */
function renderStars($rating) {
    // Biến để lưu trữ HTML được tạo
    $html = '';
    // Lặp từ 1 đến 5 để vẽ 5 ngôi sao
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            // Nếu vị trí hiện tại ≤ điểm đánh giá, vẽ sao đầy (fas = solid/solid filled)
            $html .= '<i class="fas fa-star"></i>';
        } else {
            // Ngược lại vẽ sao rỗng (far = outline/hollow)
            $html .= '<i class="far fa-star"></i>';
        }
    }
    return $html;
}
?>

<!-- Nạp CSS riêng cho trang quản lý bình luận -->
<link rel="stylesheet" href="assets/Admin_css/quanLyBinhLuan.css">

<!-- CONTAINER CHÍNH CHO TRANG QUẢN LÝ BÌNH LUẬN -->
<div class="comment-management-container">
    <!-- HEADER - TIÊU ĐỀ VÀ THỐNG KÊ -->
    <div class="page-header">
        <!-- Tiêu đề trang -->
        <span>Quản lý Bình luận Sản phẩm</span>
        <!-- Hiển thị số lượng bình luận tổng cộng -->
        <span style="font-size: 14px; font-weight: normal; color: #64748b;">
            Tổng: <strong><?= count($comments) ?></strong> bình luận
        </span>
    </div>

    <!-- BẢNG HIỂN THỊ DANH SÁCH BÌNH LUẬN -->
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
                <!-- NẾU KHÔNG CÓ BÌNH LUẬN: HIỂN THỊ TRẠNG THÁI TRỐNG -->
                <?php if (empty($comments)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-comments"></i>
                            <p>Chưa có bình luận nào.</p>
                        </td>
                    </tr>
                <!-- NẾU CÓ BÌNH LUẬN: LẶP QUA TỪNG BÌNH LUẬN VÀ HIỂN THỊ -->
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <tr>
                            <!-- CỘT ID: Hiển thị ID bình luận -->
                            <td class="id-col">#<?= $comment['id_BL'] ?></td>
                            
                            <!-- CỘT NGƯỜI DÙNG & SẢN PHẨM: Hiển thị tên người bình luận + tên sản phẩm -->
                            <td>
                                <!-- Tên người dùng với icon người dùng -->
                                <span class="user-name">
                                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($comment['ten_Dang_Nhap']) ?>
                                </span>
                                <!-- Tên sản phẩm (có thể làm thành link đến chi tiết sản phẩm) -->
                                <a href="#" class="product-name">
                                    <?= htmlspecialchars($comment['ten_San_Pham']) ?>
                                </a>
                            </td>

                            <!-- CỘT NỘI DUNG: Hiển thị nội dung bình luận với xuống dòng được bảo tồn -->
                            <td>
                                <div class="comment-content">
                                    <?= nl2br(htmlspecialchars($comment['noi_Dung'])) ?>
                                </div>
                            </td>

                            <!-- CỘT ĐÁNH GIÁ: Hiển thị ngôi sao + số sao dưới dạng điểm -->
                            <td>
                                <div class="star-rating">
                                    <!-- Gọi hàm renderStars để vẽ ngôi sao dựa trên so_Sao -->
                                    <?= renderStars($comment['so_Sao']) ?>
                                </div>
                                <!-- Hiển thị điểm dưới dạng X/5 -->
                                <small style="color: #64748b; font-size: 11px;">(<?= $comment['so_Sao'] ?>/5)</small>
                            </td>

                            <!-- CỘT THỐNG KÊ: Hiển thị trung bình rating của sản phẩm + số lượng bình luận -->
                            <td>
                                <!-- Badge chứa thông tin thống kê với title tooltip -->
                                <div class="avg-badge" title="Trung bình đánh giá của sản phẩm này">
                                    <i class="fas fa-chart-bar"></i>
                                    <!-- avg_rating được tính từ subquery trong SQL; rating_count là số bình luận -->
                                    <?= number_format($comment['avg_rating'], 1) ?>/5 
                                    (<?= $comment['rating_count'] ?> lượt)
                                </div>
                            </td>

                            <!-- CỘT NGÀY: Hiển thị ngày và giờ bình luận -->
                            <td class="date-time">
                                <!-- Chuyển đổi ngày từ format DB sang định dạng dd/mm/yyyy -->
                                <i class="far fa-clock"></i> <?= date('d/m/Y', strtotime($comment['ngay_Binh_Luan'])) ?><br>
                                <!-- Hiển thị giờ:phút -->
                                <?= date('H:i', strtotime($comment['ngay_Binh_Luan'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
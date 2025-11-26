<?php
// Hiển thị lỗi (chỉ dùng trong development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Nạp lớp Database (wrapper PDO)
require_once __DIR__ .'/../../includes/database.php';

// Tạo kết nối database
$db = new Database();
$conn = $db->connect();

// Biến lưu thông báo hiển thị trên UI
$msg = '';
// Biến lưu dòng danh mục đang sửa (khi ?edit=ID)
$editRow = null;

/* ======================================
   XÓA DANH MỤC (DELETE)
   - Kiểm tra URL param: ?delete=ID
   - Ép kiểu intval() để bảo mật
   - Dùng prepared statement để tránh SQL injection
   - Bắt exception và hiển thị thông báo lỗi
   ====================================== */
if (isset($_GET['delete'])) {
    // Ép kiểu thành số nguyên, an toàn hơn
    $id = intval($_GET['delete']);
    try {
        // Prepared statement: ? là placeholder, giá trị truyền qua execute()
        $stmt = $conn->prepare("DELETE FROM danh_muc WHERE id_DM = ?");
        // Thực hiện xóa bản ghi có id_DM = $id
        $stmt->execute([$id]);
        // Gán thông báo thành công
        $msg = "<div class='msg success'><i class='fas fa-trash'></i> Đã xóa danh mục ID <strong>$id</strong>!</div>";
    } catch (Exception $e) {
        // Nếu có lỗi, hiển thị thông báo lỗi (htmlspecialchars tránh XSS)
        $msg = "<div class='msg error'><i class='fas fa-times-circle'></i> Lỗi: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

/* ======================================
   LẤY DỮ LIỆU DANH MỤC ĐỂ SỬA (EDIT)
   - URL param: ?edit=ID
   - Truy vấn dòng tương ứng từ database
   - Lưu vào $editRow để hiển thị trên form
   ====================================== */
if (isset($_GET['edit'])) {
    // Ép kiểu ID từ URL
    $id = intval($_GET['edit']);
    // Truy vấn dòng danh mục có id = $id
    $stmt = $conn->prepare("SELECT * FROM danh_muc WHERE id_DM = ?");
    $stmt->execute([$id]);
    // fetch() lấy một dòng dưới dạng mảng key => value
    $editRow = $stmt->fetch(PDO::FETCH_ASSOC);
    // Sau đó hiển thị $editRow trên form phía HTML
}

/* ======================================
   XỬ LÝ FORM THÊM/SỬA DANH MỤC (POST)
   - Nếu form gửi lên, REQUEST_METHOD = 'POST'
   - Kiểm tra name="sua_danh_muc" để quyết định UPDATE hay INSERT
   - Validation: tên không được rỗng
   - Dùng prepared statement để chống SQL injection
   ====================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy giá trị từ form, trim() xóa khoảng trắng đầu/cuối
    // ?? '' là default value nếu key không tồn tại
    $ten = trim($_POST['ten_Danh_Muc'] ?? '');
    // Lấy ID nếu đang sửa (sẽ là 0 nếu không tồn tại)
    $id_DM = intval($_POST['id_DM'] ?? 0);

    // Validation đơn giản: kiểm tra tên không được để trống
    if ($ten === '') {
        $msg = "<div class='msg error'><i class='fas fa-exclamation-triangle'></i> Tên danh mục không được để trống!</div>";
    } else {
        try {
            // Kiểm tra xem có input hidden "sua_danh_muc" hay không
            if (isset($_POST['sua_danh_muc'])) {
                // INPUT HIDDEN CÓ => ĐÂY LÀ FORM SỬA
                $stmt = $conn->prepare("UPDATE danh_muc SET ten_Danh_Muc = ? WHERE id_DM = ?");
                $stmt->execute([$ten, $id_DM]);
                $msg = "<div class='msg success'><i class='fas fa-save'></i> Cập nhật danh mục thành công!</div>";
            } else {
                // KHÔNG CÓ INPUT HIDDEN => FORM THÊM MỚI
                $stmt = $conn->prepare("INSERT INTO danh_muc (ten_Danh_Muc) VALUES (?)");
                $stmt->execute([$ten]);
                $msg = "<div class='msg success'><i class='fas fa-plus-circle'></i> Đã thêm danh mục <strong>" . htmlspecialchars($ten) . "</strong>!</div>";
            }
        } catch (Exception $e) {
            // Bắt lỗi SQL (VD: duplicate key, constraint violation, ...)
            $msg = "<div class='msg error'><i class='fas fa-bug'></i> Lỗi SQL: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

/* ======================================
   TRUY VẤN DANH SÁCH DANH MỤC (READ)
   - query() dùng khi không có tham số
   - fetchAll() lấy tất cả dòng thành mảng
   - ORDER BY id_DM ASC: sắp xếp theo ID tăng dần
   ====================================== */
// Truy vấn toàn bộ danh mục từ table danh_muc
$stmt = $conn->query("SELECT * FROM danh_muc ORDER BY id_DM ASC");
// fetchAll() trả về mảng 2 chiều: [0] => ['id_DM' => 1, 'ten_Danh_Muc' => 'Áo'], ...
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Danh mục | 160STORE Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/Admin_css/style.css">
    <link rel="stylesheet" href="assets/Admin_css/quanLyDanhMuc.css">
</head>
<body>

<div class="container">

    <h2>Quản lý Danh mục Sản phẩm</h2>
    <?= $msg ?>

    <!-- FORM THÊM / SỬA DANH MỤC -->
    <!-- input hidden 'sua_danh_muc' chỉ xuất hiện khi $editRow có dữ liệu -->
    <!-- Nếu không có input này => form sẽ THÊM MỚI, nếu có => form sẽ CẬP NHẬT -->
    <div class="form-card">
        <h3 class="form-title">
            <?= $editRow ? '<i class="fas fa-edit"></i> Sửa danh mục' : '<i class="fas fa-plus-circle"></i> Thêm danh mục mới' ?>
        </h3>
        <form method="POST" class="form-row">
            <!-- Nếu đang sửa, gửi id_DM trong input hidden để xác định bản ghi cần cập nhật -->
            <?php if ($editRow): ?>
                <input type="hidden" name="id_DM" value="<?= htmlspecialchars($editRow['id_DM']) ?>">
            <?php endif; ?>

            <!-- Input tên danh mục: nếu sửa thì điền giá trị cũ, nếu thêm thì để trống -->
            <input type="text" name="ten_Danh_Muc" class="form-input" 
                   placeholder="Nhập tên danh mục (VD: Áo thun, Quần jeans...)"
                   value="<?= htmlspecialchars($editRow['ten_Danh_Muc'] ?? '') ?>" required style="padding:10px; width:30%; border-radius:8px;outline:none; border: 1px solid #007bff; margin-bottom:12px">

            <!-- Nút hành động: SỬA hoặc THÊM MỚI tùy theo trạng thái $editRow -->
            <?php if ($editRow): ?>
                <button type="submit" name="sua_danh_muc" class="btn btn-primary">
                    <i class="fas fa-save"></i> Cập nhật
                </button>
                <!-- Nút Hủy: quay lại trang thêm mới (bỏ tham số ?edit) -->
                <button type="button" class="btn btn-cancel" onclick="cancelEdit()">
                    <i class="fas fa-times"></i> Hủy
               </button>
            <?php else: ?>
                <button type="submit" name="them_danh_muc" class="btn btn-primary mt-6">
                    <i class="fas fa-plus"></i> Thêm mới
                </button>
            <?php endif; ?>
        </form>
    </div>

    <!-- BẢNG HIỂN THỊ DANH SÁCH DANH MỤC -->
    <!-- Lặp qua $rows, mỗi dòng là một danh mục -->
    <!-- Nếu $rows rỗng => hiển thị thông báo 'Chưa có danh mục nào' -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th width="80">ID</th>
                    <th>Tên danh mục</th>
                    <th width="140">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <!-- Hiển thị khi không có dữ liệu -->
                    <tr>
                        <td colspan="3" style="text-align:center; color:#888; padding:30px;">
                            <i class="fas fa-inbox"></i> Chưa có danh mục nào.
                        </td>
                    </tr>
                <?php else: ?>
                    <!-- Hiển thị từng danh mục: ID, Tên, 2 nút (Sửa, Xóa) -->
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><strong>#<?= $r['id_DM'] ?></strong></td>
                            <td><?= htmlspecialchars($r['ten_Danh_Muc']) ?></td>
                            <td class="actions">
                                <!-- Nút Sửa: gọi JS editDm() với ID -->
                                <button class="btn-edit" onclick="editDm(<?= $r['id_DM'] ?>)">
                                    <i class="fas fa-edit"></i> Sửa
                                </button>
                                <!-- Nút Xóa: gọi JS deleteDm() với ID -->
                                <button class="btn-delete" onclick="deleteDm(<?= $r['id_DM'] ?>)">
                                    <i class="fas fa-trash"></i> Xóa
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
<script>
    /* ======================================
       HÀM HỦY BỎ CHỈNH SỬA (cancelEdit)
       - Khi người dùng nhấn nút Hủy
       - Quay lại trang chính (bỏ tham số ?edit=ID)
       - Làm trống form sửa, hiển thị form thêm mới
       ====================================== */
    function cancelEdit() {
        // window.location.href: chuyển hướng trình duyệt tới URL mới
        // Loại bỏ tham số ?edit, quay về trang thêm mới
        window.location.href = 'admin.php?page=quanLyDanhMuc';
    }

    /* ======================================
       HÀM CHỈNH SỬA DANH MỤC (editDm)
       - Khi người dùng nhấn nút 'Sửa' trong bảng
       - Tải lại trang với tham số ?edit=ID
       - Backend PHP sẽ phát hiện tham số này và load dữ liệu vào form
       ====================================== */
    /* ======================================
       HÀM CHỈNH SỬA DANH MỤC (editDm)
       - Khi người dùng nhấn nút 'Sửa' trong bảng
       - Thêm tham số ?edit=ID vào URL
       - Backend PHP phát hiện và load dữ liệu vào form
       ====================================== */
    function editDm(id) {
        // Chuyển sang chế độ sửa: thêm tham số ?edit=ID vào URL
        window.location.href = 'admin.php?page=quanLyDanhMuc&edit=' + id;
        return false;
    }

    /* ======================================
       HÀM XÓA DANH MỤC (deleteDm)
       - Khi người dùng nhấn nút 'Xóa' trong bảng
       - Hiển thị hộp thoại xác nhận (confirm)
       - Nếu xác nhận => thêm tham số ?delete=ID vào URL
       - Backend thực hiện DELETE từ database
       ====================================== */
    function deleteDm(id) {
        // Hiển thị hộp thoại xác nhận
        if (!confirm(`Bạn có chắc muốn xóa danh mục ID #${id} không?`)) return false;
        // Nếu xác nhận, chuyển hướng với tham số ?delete=ID
        window.location.href = 'admin.php?page=quanLyDanhMuc&delete=' + id;
        return false;
    }
    
    /* ======================================
       TỰ ĐỘNG ẨN THÔNG BÁO SAU 3 GIÂY
       - Khi có thông báo (success hoặc error)
       - Sau 3s tự động mờ dần và biến mất
       - Tạo trải nghiệm người dùng mượt mà hơn
       ====================================== */
    setTimeout(function() {
        // Tìm element có class 'msg' (chứa thông báo)
        const msg = document.querySelector('.msg');
        if (msg) {
            // Thêm hiệu ứng fade out (mờ dần)
            msg.style.transition = 'opacity 0.5s';
            msg.style.opacity = '0';
            // Sau 0.5s nữa, xóa element khỏi DOM
            setTimeout(() => msg.remove(), 500);
        }
    }, 3000); // 3000ms = 3 giây
</script>

</body>
</html>
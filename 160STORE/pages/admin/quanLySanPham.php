<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra quyền admin
if (!isset($_SESSION['user']) || $_SESSION['user']['vai_Tro'] !== 'admin') {
    header("Location: ../dangNhap_DangKy.php");
    exit;
}

$db = new Database();
$conn = $db->connect();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$msg = "";
$editing = false;
$product = [];
$variants = [];

// Tạo thư mục uploads
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Lấy danh sách hỗ trợ
try {
    $categories = $conn->query("SELECT * FROM danh_muc ORDER BY ten_Danh_Muc")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

try {
    $discounts = $conn->query("SELECT * FROM ma_giam_gia ORDER BY ma_Giam_Gia")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $discounts = [];
}

// Xác định tên bảng
$tableName = 'san_pham';
try {
    $conn->query("SELECT 1 FROM san_pham LIMIT 1");
} catch (Exception $e) {
    try {
        $conn->query("SELECT 1 FROM sanpham LIMIT 1");
        $tableName = 'sanpham';
    } catch (Exception $e2) {
        die("Lỗi: Không tìm thấy bảng sản phẩm trong database.");
    }
}

// XÓA SẢN PHẨM
if (isset($_GET['delete'])) {
    $id = trim($_GET['delete']);
    try {
        // Xóa biến thể trước
        $stmt = $conn->prepare("DELETE FROM bien_the_san_pham WHERE id_SP = ?");
        $stmt->execute([$id]);
        
        // Xóa sản phẩm
        $stmt = $conn->prepare("DELETE FROM $tableName WHERE id_SP = ?");
        $stmt->execute([$id]);
        $msg = "<div class='msg success'><i class='fas fa-trash'></i> Đã xóa sản phẩm <strong>$id</strong>!</div>";
    } catch (Exception $e) {
        $msg = "<div class='msg error'><i class='fas fa-times-circle'></i> Lỗi: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// LẤY THÔNG TIN SẢN PHẨM ĐỂ SỬA
if (isset($_GET['edit'])) {
    $id = trim($_GET['edit']);
    try {
        $stmt = $conn->prepare("SELECT * FROM $tableName WHERE id_SP = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $editing = true;
            // Lấy biến thể
            $stmt = $conn->prepare("SELECT * FROM bien_the_san_pham WHERE id_SP = ?");
            $stmt->execute([$id]);
            $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $msg = "<div class='msg error'><i class='fas fa-times-circle'></i> Không tìm thấy sản phẩm!</div>";
        }
    } catch (Exception $e) {
        $msg = "<div class='msg error'><i class='fas fa-bug'></i> Lỗi: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// THÊM/SỬA SẢN PHẨM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['them']) || isset($_POST['sua'])) {
    $id_SP = trim($_POST['id_SP'] ?? '');
    $ten_San_Pham = trim($_POST['ten_San_Pham'] ?? '');
    $gia_Ban = floatval($_POST['gia_Ban'] ?? 0);
    $gia_Goc = floatval($_POST['gia_Goc'] ?? 0);
    $mo_Ta = trim($_POST['mo_Ta'] ?? '');
    $id_DM = intval($_POST['id_DM'] ?? 0);
    $thuong_Hieu = trim($_POST['thuong_Hieu'] ?? '');
    $so_Luong_Ton = intval($_POST['so_Luong_Ton'] ?? 0);
    $trang_Thai = 'Còn hàng';
    $ma_Giam_Gia = trim($_POST['ma_Giam_Gia'] ?? '');
    $hinh_Anh = trim($_POST['link_hinh'] ?? '');

    $isUpdating = isset($_POST['sua']);
    $errors = [];
    
    // Validation
    if (empty($id_SP)) $errors[] = "Mã sản phẩm không được để trống.";
    if (empty($ten_San_Pham)) $errors[] = "Tên sản phẩm không được để trống.";
    if ($gia_Ban <= 0) $errors[] = "Giá bán phải lớn hơn 0.";
    if ($id_DM <= 0) $errors[] = "Vui lòng chọn danh mục hợp lệ.";
    
    // Kiểm tra trùng mã khi thêm mới
    if (!$isUpdating) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM $tableName WHERE id_SP = ?");
        $stmt->execute([$id_SP]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Mã sản phẩm <strong>$id_SP</strong> đã tồn tại.";
        }
    }
    
    // Upload hình ảnh
    if (isset($_FILES['file_hinh']) && $_FILES['file_hinh']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $_FILES['file_hinh']['type'];
        $fileSize = $_FILES['file_hinh']['size'];
        
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP).";
        } elseif ($fileSize > 5 * 1024 * 1024) { // 5MB
            $errors[] = "File ảnh không được vượt quá 5MB.";
        } else {
            $fileExt = pathinfo($_FILES['file_hinh']['name'], PATHINFO_EXTENSION);
            $fileName = time() . '_' . uniqid() . '.' . $fileExt;
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['file_hinh']['tmp_name'], $targetPath)) {
                $hinh_Anh = "uploads/" . $fileName;
            } else {
                $errors[] = "Lỗi khi upload file hình ảnh. Kiểm tra quyền thư mục.";
            }
        }
    }
    
    if (!empty($errors)) {
        $msg = "<div class='msg error'><ul>" . implode("</li><li>", $errors) . "</ul></div>";
    } else {
        try {
            if ($isUpdating) {
                // CẬP NHẬT - Giữ nguyên hình ảnh cũ nếu không upload mới
                if (empty($hinh_Anh) && !empty($product['hinh_Anh'])) {
                    $hinh_Anh = $product['hinh_Anh'];
                }
                
                $ngay_Cap_Nhat = date('Y-m-d H:i:s');
                $sql = "UPDATE $tableName SET 
                    ten_San_Pham=?, gia_Ban=?, gia_Goc=?, mo_Ta=?, id_DM=?, 
                    thuong_Hieu=?, so_Luong_Ton=?, trang_Thai=?, ma_Giam_Gia=?, hinh_Anh=?, ngay_Cap_Nhat=?
                    WHERE id_SP=?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$ten_San_Pham, $gia_Ban, $gia_Goc, $mo_Ta, $id_DM, 
                               $thuong_Hieu, $so_Luong_Ton, $trang_Thai, $ma_Giam_Gia, $hinh_Anh, $ngay_Cap_Nhat, $id_SP]);
                
                // Xóa biến thể cũ
                $stmt = $conn->prepare("DELETE FROM bien_the_san_pham WHERE id_SP = ?");
                $stmt->execute([$id_SP]);
                
                $msg = "<div class='msg success'><i class='fas fa-check-circle'></i> Cập nhật sản phẩm thành công!</div>";
            } else {
                // THÊM MỚI
                $ngay_Tao = date('Y-m-d H:i:s');
                $ngay_Cap_Nhat = date('Y-m-d H:i:s');
                $sql = "INSERT INTO $tableName (id_SP, ten_San_Pham, gia_Ban, gia_Goc, mo_Ta, id_DM, 
                        thuong_Hieu, so_Luong_Ton, trang_Thai, ma_Giam_Gia, hinh_Anh, ngay_Tao, ngay_Cap_Nhat)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id_SP, $ten_San_Pham, $gia_Ban, $gia_Goc, $mo_Ta, $id_DM,
                    $thuong_Hieu, $so_Luong_Ton, $trang_Thai, $ma_Giam_Gia, $hinh_Anh, $ngay_Tao, $ngay_Cap_Nhat]);

                $msg = "<div class='msg success'><i class='fas fa-check-circle'></i> Thêm sản phẩm thành công!</div>";
            }
            
            // Thêm biến thể (nếu có)
            if (!empty($_POST['variants'])) {
                $stmt = $conn->prepare("SELECT MAX(id_Bien_The) as max_id FROM bien_the_san_pham");
                $stmt->execute();
                $maxId = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
                $nextId = $maxId + 1;
                
                $stmtVariant = $conn->prepare("INSERT INTO bien_the_san_pham (id_Bien_The, id_SP, mau_Sac, kich_Thuoc) VALUES (?, ?, ?, ?)");
                
                foreach ($_POST['variants'] as $variant) {
                    $mau = trim($variant['mau_sac'] ?? '');
                    $size = trim($variant['kich_thuoc'] ?? '');
                    if (!empty($mau) || !empty($size)) {
                        $stmtVariant->execute([$nextId++, $id_SP, $mau, $size]);
                    }
                }
            }
            
            $editing = false;
            $product = [];
            $variants = [];
            
        } catch (Exception $e) {
            $msg = "<div class='msg error'><i class='fas fa-bug'></i> Lỗi: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// TÌM KIẾM
$search = trim($_GET['search'] ?? '');
$sql = "SELECT sp.*, dm.ten_Danh_Muc 
        FROM $tableName sp 
        LEFT JOIN danh_muc dm ON sp.id_DM = dm.id_DM
        WHERE sp.ten_San_Pham LIKE :s OR sp.id_SP LIKE :s
        ORDER BY sp.id_SP DESC";
$stmt = $conn->prepare($sql);
$stmt->execute(['s' => "%$search%"]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Sản phẩm | 160STORE Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/Admin_css/quanLySanPham.css">
    <style>
        .variant-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .variant-item {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .btn-add-variant {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-remove-variant {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="container">

    <h2><i class="fas fa-box"></i> Quản lý Sản phẩm</h2>
    <?= $msg ?>

    <!-- TÌM KIẾM -->
    <form method="GET" class="search-bar">
        <input type="text" name="search" class="search-input" placeholder="Tìm kiếm mã hoặc tên sản phẩm..." 
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn-search">
            <i class="fas fa-search"></i> Tìm kiếm
        </button>
        <a href="admin.php?page=quanLySanPham" class="btn-refresh">
            <i class="fas fa-sync"></i> Làm mới
        </a>
    </form>

    <!-- FORM THÊM/SỬA -->
    <div class="form-card">
        <h3 class="form-title">
            <?= $editing ? '<i class="fas fa-edit"></i> Sửa sản phẩm' : '<i class="fas fa-plus"></i> Thêm sản phẩm mới' ?>
        </h3>
        <form method="POST" enctype="multipart/form-data" id="productForm">
            <div class="form-grid">
                
                <div class="form-group">
                    <label><i class="fas fa-barcode"></i> Mã sản phẩm *</label>
                    <input type="text" name="id_SP" value="<?= htmlspecialchars($product['id_SP'] ?? '') ?>" 
                           <?= $editing ? 'readonly' : 'required' ?> placeholder="VD: SP001">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Tên sản phẩm *</label>
                    <input type="text" name="ten_San_Pham" value="<?= htmlspecialchars($product['ten_San_Pham'] ?? '') ?>" 
                           required placeholder="Áo thun nam cổ tròn">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-dollar-sign"></i> Giá bán (VNĐ) *</label>
                    <input type="number" name="gia_Ban" value="<?= htmlspecialchars($product['gia_Ban'] ?? '') ?>" 
                           required placeholder="199000" min="0">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-money-bill"></i> Giá gốc (VNĐ)</label>
                    <input type="number" name="gia_Goc" value="<?= htmlspecialchars($product['gia_Goc'] ?? '') ?>" 
                           placeholder="299000" min="0">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-list"></i> Danh mục *</label>
                    <select name="id_DM" required>
                        <option value="">-- Chọn danh mục --</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?= $c['id_DM'] ?>" <?= ($product['id_DM'] ?? '') == $c['id_DM'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['ten_Danh_Muc']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-trademark"></i> Thương hiệu</label>
                    <input type="text" name="thuong_Hieu" value="<?= htmlspecialchars($product['thuong_Hieu'] ?? '') ?>" 
                           placeholder="160STORE">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-warehouse"></i> Số lượng tồn</label>
                    <input type="number" name="so_Luong_Ton" value="<?= htmlspecialchars($product['so_Luong_Ton'] ?? 0) ?>" 
                           placeholder="100" min="0">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-percent"></i> Mã giảm giá</label>
                    <select name="ma_Giam_Gia">
                        <option value="">-- Không áp dụng --</option>
                        <?php foreach ($discounts as $d): ?>
                            <option value="<?= htmlspecialchars($d['ma_Giam_Gia']) ?>" 
                                <?= ($product['ma_Giam_Gia'] ?? '') == $d['ma_Giam_Gia'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['ma_Giam_Gia']) ?> - <?= htmlspecialchars($d['mo_Ta']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group full">
                    <label><i class="fas fa-align-left"></i> Mô tả sản phẩm</label>
                    <textarea name="mo_Ta" rows="3" placeholder="Chất liệu cotton, thoáng mát..."><?= htmlspecialchars($product['mo_Ta'] ?? '') ?></textarea>
                </div>

                <div class="form-group full">
                    <label><i class="fas fa-image"></i> Hình ảnh sản phẩm</label>
                    <input type="file" name="file_hinh" accept="image/*">
                    <input type="text" name="link_hinh" placeholder="Hoặc nhập link ảnh..." 
                            value="<?= htmlspecialchars($product['hinh_Anh'] ?? '') ?>" style="margin-top:10px;">
                    <?php if ($editing && !empty($product['hinh_Anh'])): ?>
                        <small style="color:#888; margin-top:5px;">
                            Ảnh hiện tại: <a href="<?= htmlspecialchars($product['hinh_Anh']) ?>" target="_blank">Xem ảnh</a>
                        </small>
                    <?php endif; ?>
                </div>

                <!-- BIẾN THỂ -->
                <div class="form-group full">
                    <div class="variant-section">
                        <h4><i class="fas fa-palette"></i> Biến thể sản phẩm (Màu sắc & Size)</h4>
                        <div id="variantContainer">
                            <?php if (!empty($variants)): ?>
                                <?php foreach($variants as $index => $v): ?>
                                    <div class="variant-item">
                                        <div class="form-group">
                                            <label>Màu sắc</label>
                                            <input type="text" name="variants[<?= $index ?>][mau_sac]" 
                                                   value="<?= htmlspecialchars($v['mau_Sac'] ?? '') ?>" 
                                                   placeholder="VD: Đen, Trắng">
                                        </div>
                                        <div class="form-group">
                                            <label>Kích thước</label>
                                            <input type="text" name="variants[<?= $index ?>][kich_thuoc]" 
                                                   value="<?= htmlspecialchars($v['kich_Thuoc'] ?? '') ?>" 
                                                   placeholder="VD: S, M, L, XL">
                                        </div>
                                        <button type="button" class="btn-remove-variant" onclick="removeVariant(this)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="variant-item">
                                    <div class="form-group">
                                        <label>Màu sắc</label>
                                        <input type="text" name="variants[0][mau_sac]" placeholder="VD: Đen, Trắng">
                                    </div>
                                    <div class="form-group">
                                        <label>Kích thước</label>
                                        <input type="text" name="variants[0][kich_thuoc]" placeholder="VD: S, M, L, XL">
                                    </div>
                                    <button type="button" class="btn-remove-variant" onclick="removeVariant(this)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn-add-variant" onclick="addVariant()">
                            <i class="fas fa-plus"></i> Thêm biến thể
                        </button>
                    </div>
                </div>

                <div class="form-actions">
                    <?php if ($editing): ?>
                        <button type="submit" name="sua" class="btn btn-primary">
                            <i class="fas fa-save"></i> Cập nhật
                        </button>
                        <a href="admin.php?page=quanLySanPham" class="btn btn-cancel">
                            <i class="fas fa-times"></i> Hủy
                        </a>
                    <?php else: ?>
                        <button type="submit" name="them" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Thêm sản phẩm
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- BẢNG DANH SÁCH -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Mã SP</th>
                    <th>Tên sản phẩm</th>
                    <th>Ảnh</th>
                    <th>Giá bán</th>
                    <th>Tồn kho</th>
                    <th>Danh mục</th>
                    <th>Biến thể</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; color:#888; padding:30px;">
                            <i class="fas fa-inbox" style="font-size:32px;"></i><br>
                            Không tìm thấy sản phẩm nào.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($products as $p): ?>
                        <?php
                        // Lấy biến thể cho sản phẩm này
                        $stmt = $conn->prepare("SELECT * FROM bien_the_san_pham WHERE id_SP = ?");
                        $stmt->execute([$p['id_SP']]);
                        $pVariants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['id_SP']) ?></strong></td>
                            <td><?= htmlspecialchars($p['ten_San_Pham']) ?></td>
                            <td>
                                <?php if (!empty($p['hinh_Anh'])): ?>
                                    <img src="<?= htmlspecialchars($p['hinh_Anh']) ?>" class="product-thumb" alt="Product">
                                <?php else: ?>
                                    <div style="width:60px;height:60px;background:#e0e0e0;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                        <i class="fas fa-image" style="color:#999;"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= number_format($p['gia_Ban'], 0, ',', '.') ?>đ</strong></td>
                            <td>
                                <span style="padding:5px 10px; background:#e3f2fd; color:#1976d2; border-radius:5px; font-weight:600;">
                                    <?= $p['so_Luong_Ton'] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($p['ten_Danh_Muc'] ?? '-') ?></td>
                            <td>
                                <?php if (!empty($pVariants)): ?>
                                    <small style="color:#666;">
                                        <?php foreach($pVariants as $pv): ?>
                                            <div><?= htmlspecialchars(($pv['mau_Sac'] ?? '') . ' - ' . ($pv['kich_Thuoc'] ?? '')) ?></div>
                                        <?php endforeach; ?>
                                    </small>
                                <?php else: ?>
                                    <small style="color:#999;">Không có</small>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="?edit=<?= urlencode($p['id_SP']) ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="btn-edit">
                                    <i class="fas fa-edit"></i> Sửa
                                </a>
                                <a href="?delete=<?= urlencode($p['id_SP']) ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
                                   class="btn-delete" 
                                   onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm <?= htmlspecialchars($p['ten_San_Pham']) ?>?')">
                                    <i class="fas fa-trash"></i> Xóa
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
let variantIndex = <?= !empty($variants) ? count($variants) : 1 ?>;

function addVariant() {
    const container = document.getElementById('variantContainer');
    const newVariant = document.createElement('div');
    newVariant.className = 'variant-item';
    newVariant.innerHTML = `
        <div class="form-group">
            <label>Màu sắc</label>
            <input type="text" name="variants[${variantIndex}][mau_sac]" placeholder="VD: Đen, Trắng">
        </div>
        <div class="form-group">
            <label>Kích thước</label>
            <input type="text" name="variants[${variantIndex}][kich_thuoc]" placeholder="VD: S, M, L, XL">
        </div>
        <button type="button" class="btn-remove-variant" onclick="removeVariant(this)">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(newVariant);
    variantIndex++;
}

function removeVariant(btn) {
    const container = document.getElementById('variantContainer');
    if (container.children.length > 1) {
        btn.closest('.variant-item').remove();
    } else {
        alert('Phải có ít nhất 1 biến thể!');
    }
}

// Tự động ẩn thông báo sau 5 giây
setTimeout(function() {
    const msg = document.querySelector('.msg');
    if (msg) {
        msg.style.transition = 'opacity 0.5s';
        msg.style.opacity = '0';
        setTimeout(() => msg.remove(), 500);
    }
}, 5000);
</script>

</body>
</html>
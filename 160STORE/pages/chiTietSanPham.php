<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/database.php');

$db = new Database();
$pdo = $db->connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Kiểm tra login
$isLoggedIn = isset($_SESSION['user']);
$user_id = $isLoggedIn ? $_SESSION['user']['id_ND'] : null;

// Lấy id sản phẩm
$id_SP = $_GET['id'] ?? '';
if (!$id_SP) die("<p class='text-danger'>Sản phẩm không tồn tại.</p>");

// ===== LẤY THÔNG TIN SẢN PHẨM =====
$product_stmt = $pdo->prepare("
    SELECT sp.*, dm.ten_Danh_Muc
    FROM san_pham sp
    LEFT JOIN danh_muc dm ON sp.id_DM = dm.id_DM
    WHERE sp.id_SP = ?
");
$product_stmt->execute([$id_SP]);
$product = $product_stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) die("<p class='text-danger'>Sản phẩm không tồn tại.</p>");

// Lấy đánh giá trung bình và số đánh giá cho sản phẩm
$rating_stmt = $pdo->prepare("SELECT AVG(so_Sao) AS avg_rating, COUNT(*) AS rating_count FROM binh_luan WHERE id_SP = ?");
$rating_stmt->execute([$id_SP]);
$rating_info = $rating_stmt->fetch(PDO::FETCH_ASSOC);
$avg_rating = $rating_info && $rating_info['avg_rating'] !== null ? round(floatval($rating_info['avg_rating']),1) : 0;
$rating_count = $rating_info ? intval($rating_info['rating_count']) : 0;

// Kiểm tra có phải phụ kiện không
$isAccessory = ($product['id_DM'] == 3);

// ===== LẤY BIẾN THỂ =====
$variant_stmt = $pdo->prepare("SELECT * FROM bien_the_san_pham WHERE id_SP = ?");
$variant_stmt->execute([$id_SP]);
$variants = $variant_stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== XỬ LÝ GIỎ HÀNG (AJAX) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_to_cart']) || isset($_POST['buy_now']))) {
    // Xóa bất kỳ output nào trước đó
    ob_clean();
    header('Content-Type: application/json');
    
    // Kiểm tra đăng nhập
    if (!$isLoggedIn) {
        echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để thêm vào giỏ hàng!']);
        exit;
    }

    $selected_size  = $isAccessory ? null : ($_POST['size'] ?? '');
    $selected_color = $isAccessory ? null : ($_POST['color'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);
    $buy_now = isset($_POST['buy_now']);

    // Validate input cho sản phẩm không phải phụ kiện
    if (!$isAccessory && (empty($selected_size) || empty($selected_color))) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn đầy đủ màu sắc và kích thước!']);
        exit;
    }

    try {
        // Kiểm tra giỏ hàng
        $stmt = $pdo->prepare("SELECT * FROM gio_hang WHERE id_ND = ?");
        $stmt->execute([$user_id]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cart) {
            $maxId = $pdo->query("SELECT COALESCE(MAX(id_GH),0)+1 AS newId FROM gio_hang")->fetch()['newId'];
            $pdo->prepare("INSERT INTO gio_hang (id_GH, id_ND, ngay_Tao) VALUES (?, ?, NOW())")
                ->execute([$maxId, $user_id]);
            $cart_id = $maxId;
        } else {
            $cart_id = $cart['id_GH'];
        }

        // Kiểm tra tồn kho trước khi thêm
        $stmt_stock = $pdo->prepare("SELECT so_Luong_Ton FROM san_pham WHERE id_SP = ?");
        $stmt_stock->execute([$id_SP]);
        $stockRow = $stmt_stock->fetch(PDO::FETCH_ASSOC);
        $available = $stockRow ? intval($stockRow['so_Luong_Ton']) : 0;
        if ($quantity > $available) {
            echo json_encode(['success' => false, 'message' => 'Số lượng trong kho không đủ. Hiện có: ' . $available]);
            exit;
        }

        $pdo->prepare("
            INSERT INTO gio_hang_chi_tiet (id_GH, id_SP, so_Luong, ten_san_pham, mau_sac, kich_Thuoc)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$cart_id, $id_SP, $quantity, $product['ten_San_Pham'], $selected_color, $selected_size]);

        $message = $buy_now ? 'Đang chuyển hướng đến thanh toán...' : 'Đã thêm vào giỏ hàng!';
        echo json_encode(['success' => true, 'message' => $message, 'buy_now' => $buy_now]);
    } catch (Exception $e) {
        $err = [
            'success' => false,
            'message' => 'Lỗi: ' . $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
        // Log to file for local debugging
        $logDir = __DIR__ . '/storage';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        @file_put_contents($logDir . '/error_log.txt', date('[Y-m-d H:i:s] ') . json_encode($err) . PHP_EOL, FILE_APPEND);
        echo json_encode($err);
    }
    exit;
}

// ===== GỢI Ý SẢN PHẨM =====
$suggest_stmt = $pdo->query("SELECT id_SP, ten_San_Pham, gia_Ban, hinh_Anh FROM san_pham ORDER BY RAND() LIMIT 4");
$suggest_products = $suggest_stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== BÌNH LUẬN =====
$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn && isset($_POST['guiBL'])) {
    $noi_Dung = trim($_POST['noi_Dung'] ?? '');
    $so_Sao = intval($_POST['so_Sao'] ?? 5);
    $id_BL_cha = !empty($_POST['id_BL_cha']) ? intval($_POST['id_BL_cha']) : NULL;

    if ($noi_Dung) {
        $pdo->prepare("
            INSERT INTO binh_luan(id_SP,id_ND,id_BL_cha,noi_Dung,so_Sao,ngay_Binh_Luan)
            VALUES(?,?,?,?,?,NOW())
        ")->execute([$id_SP,$user_id,$id_BL_cha,$noi_Dung,$so_Sao]);

        $success_msg = "Đã gửi bình luận!";
    } else $error_msg = "Nội dung không được trống!";
}

$bl_stmt = $pdo->prepare("
    SELECT bl.*, nd.ho_ten
    FROM binh_luan bl
    LEFT JOIN nguoi_dung nd ON bl.id_ND = nd.id_ND
    WHERE id_SP=?
    ORDER BY ngay_Binh_Luan ASC
");
$bl_stmt->execute([$id_SP]);
$comments = $bl_stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== GHÉP NHÁNH BÌNH LUẬN =====
function buildTree($arr){
    $tree=[];$refs=[];
    foreach($arr as $c){ $c['children']=[]; $refs[$c['id_BL']]=$c; }
    foreach($refs as $id=>&$node){
        if($node['id_BL_cha']==NULL) $tree[$id]=&$node;
        elseif(isset($refs[$node['id_BL_cha']])) $refs[$node['id_BL_cha']]['children'][$id]=&$node;
    }
    return $tree;
}

$commentTree = buildTree($comments);

function renderCommentNodes($arr,$login,$lv=0){
    foreach($arr as $c){
        echo "<div class='list-group-item mb-2 shadow-sm' style='margin-left:".($lv*20)."px'>";
        echo "<b>".htmlspecialchars($c['ho_ten'])."</b> <small class='text-muted'>(".date("d/m/Y",strtotime($c['ngay_Binh_Luan'])).")</small><br>";
        // Hiển thị sao cho bình luận
        $rating = intval(
            isset($c['so_Sao']) ? $c['so_Sao'] : 0
        );
        for($s=1;$s<=5;$s++){
            $cls = ($s <= $rating) ? 'star filled' : 'star';
            echo "<span class='".$cls."'>★</span>";
        }
        echo "<br>";
        echo nl2br(htmlspecialchars($c['noi_Dung']))."<br>";
        if($login){
            echo "<form method='post' class='mt-1'>
                    <input type='hidden' name='id_BL_cha' value='{$c['id_BL']}'>
                    <textarea name='noi_Dung' class='form-control' rows='1'></textarea>
                    <button name='guiBL' class='btn btn-sm btn-outline-primary mt-1'>Trả lời</button>
                </form>";
        }
        if($c['children']) renderCommentNodes($c['children'],$login,$lv+1);
        echo "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($product['ten_San_Pham']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .star{font-size:18px;color:#ccc;margin-right:4px}
        .star.filled{color:#ffc107}
        .star-input{cursor:pointer;font-size:22px;color:#ccc;margin-right:4px}
        .star-input.filled{color:#ffc107}
        .product-rating{display:inline-flex;align-items:center}
        .product-rating small{margin-left:8px;color:#666}
    </style>
</head>
<body>
<div class="container my-5">
<div class="row g-4">
    <div class="col-md-5">
        <img src="<?= stripos($product['hinh_Anh'],'http')===0?$product['hinh_Anh']:'/ShopQuanAo/160STORE/pages/admin/'.$product['hinh_Anh'] ?>"
             class="img-fluid rounded">
    </div>
    <div class="col-md-7">
        <h2><?= htmlspecialchars($product['ten_San_Pham']) ?></h2>
        <p><b>Danh mục:</b> <?= htmlspecialchars($product['ten_Danh_Muc']) ?></p>
        <h4 class="text-danger"><?= number_format($product['gia_Ban']) ?> VNĐ</h4>
        <p><?= htmlspecialchars($product['mo_Ta']) ?></p>

        <?php
        // Hiển thị đánh giá trung bình
        echo '<div class="mb-2"><div class="product-rating">';
        for ($i=1;$i<=5;$i++){
            $cls = ($i <= round($avg_rating)) ? 'star filled' : 'star';
            echo "<span class=\"{$cls}\">★</span>";
        }
        echo "<small>({$avg_rating} / 5 - {$rating_count} đánh giá)</small>";
        echo '</div></div>';
        ?>

        <!-- Thông báo AJAX -->
        <div id="alertBox" class="alert" style="display:none; margin-top:10px;"></div>

        <!-- FORM THÊM VÀO GIỎ HÀNG -->
        <?php if($isLoggedIn): ?>
        <form id="addCartForm">
            <div class="d-flex flex-column flex-md-row gap-2 mb-3">
                <?php if(!$isAccessory): ?>
                    <select name="color" class="form-select" required>
                        <option value="">--Chọn màu--</option>
                        <?php foreach ($variants as $v): if($v['mau_Sac']): ?>
                            <option value="<?= $v['mau_Sac'] ?>"><?= $v['mau_Sac'] ?></option>
                        <?php endif; endforeach; ?>
                    </select>

                    <select name="size" class="form-select" required>
                        <option value="">--Chọn size--</option>
                        <?php foreach ($variants as $v): if($v['kich_Thuoc']): ?>
                            <option value="<?= $v['kich_Thuoc'] ?>"><?= $v['kich_Thuoc'] ?></option>
                        <?php endif; endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="hidden" name="size" value="">
                    <input type="hidden" name="color" value="">
                <?php endif; ?>

                <input type="number" name="quantity" value="1" min="1" class="form-control" style="max-width:100px;">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" id="addCartBtn" name="action" value="add_to_cart">Thêm vào giỏ</button>
                <button type="button" class="btn btn-success" id="buyNowBtn" value="buy_now">Mua luôn</button>
            </div>
        </form>
        <?php else: ?>
            <div class="alert alert-warning">
                Vui lòng <a href="login.php">đăng nhập</a> để thêm sản phẩm vào giỏ hàng.
            </div>
        <?php endif; ?>
    </div>
</div>

<hr><h4>Gợi ý sản phẩm</h4>
<div class="row">
<?php foreach($suggest_products as $s): ?>
    <div class="col-md-3 col-6 mb-3">
        <a href="index.php?page=chiTietSanPham&id=<?= $s['id_SP'] ?>" class="text-decoration-none">
            <div class="card">
                <img src="<?= stripos($s['hinh_Anh'],'http')===0 ? $s['hinh_Anh'] : '/ShopQuanAo/160STORE/pages/admin/'.$s['hinh_Anh'] ?>" class="card-img-top" style="height:180px;object-fit:cover;">
                <div class="card-body text-center">
                    <small><?= htmlspecialchars($s['ten_San_Pham']) ?></small><br>
                    <b><?= number_format($s['gia_Ban']) ?>đ</b>
                </div>
            </div>
        </a>
    </div>
<?php endforeach; ?>
</div>

<hr><h4>Bình luận</h4>
<?php
if($isLoggedIn){
    ?>
    <form method="post" id="commentForm">
        <div class="mb-2">
            <label>Đánh giá:</label>
            <div id="starInput" class="mb-2">
                <?php for($i=1;$i<=5;$i++): ?>
                    <span class="star-input" data-value="<?php echo $i; ?>">★</span>
                <?php endfor; ?>
                <input type="hidden" name="so_Sao" id="so_Sao" value="5">
            </div>
        </div>
        <textarea name="noi_Dung" class="form-control mb-2" placeholder="Viết bình luận..." required></textarea>
        <button name="guiBL" class="btn btn-success">Gửi</button>
    </form>
    <br>
    <?php
} else {
    echo "<p class='text-warning'>Bạn cần đăng nhập để bình luận.</p>";
}
echo "<div class='list-group mt-3'>";
renderCommentNodes($commentTree,$isLoggedIn);
echo "</div>";
?>

</div>

<script>
const form = document.getElementById('addCartForm');
const alertBox = document.getElementById('alertBox');
const addCartBtn = document.getElementById('addCartBtn');
const buyNowBtn = document.getElementById('buyNowBtn');

if(form) {
    // Xử lý cả hai nút submit
    const buttons = [addCartBtn, buyNowBtn];
    
    buttons.forEach(btn => {
        btn.addEventListener('click', function(e){
            e.preventDefault();

            // Disable tất cả button để tránh spam
            addCartBtn.disabled = true;
            buyNowBtn.disabled = true;
            addCartBtn.textContent = 'Đang xử lý...';

            const formData = new FormData(form);
            
            // Xác định action
            const action = this.value || 'add_to_cart';
            if(action === 'add_to_cart') {
                formData.append('add_to_cart', 1);
            } else {
                formData.append('buy_now', 1);
            }

            fetch('', {method:'POST', body: formData})
                .then(res => {
                    // Debug: Log response text
                    return res.text().then(text => {
                        console.log('Response:', text);
                        try {
                            return JSON.parse(text);
                        } catch(e) {
                            console.error('JSON Parse Error:', e);
                            console.error('Raw response:', text);
                            throw new Error('Server trả về không phải JSON');
                        }
                    });
                })
                .then(data => {
                    // Hiển thị thông báo
                    alertBox.className = data.success ? 'alert alert-success' : 'alert alert-danger';
                    alertBox.textContent = data.message;
                    alertBox.style.display = 'block';
                    
                    // Ẩn sau 3 giây
                    setTimeout(() => {
                        alertBox.style.display = 'none';
                    }, 3000);

                    // Reset form nếu thành công
                    if(data.success) {
                        form.reset();
                        
                        // Redirect theo action
                        if(data.buy_now) {
                            // Redirect đến trang thanh toán
                            setTimeout(() => {
                                window.location.href = 'index.php?page=checkout';
                            }, 1000);
                        } else {
                            // Reload lại trang chi tiết sản phẩm
                            setTimeout(() => {
                                window.location.href = 'index.php?page=chiTietSanPham&id=<?= $id_SP ?>';
                            }, 1000);
                        }
                    } else {
                        // Enable lại button nếu thất bại
                        addCartBtn.disabled = false;
                        buyNowBtn.disabled = false;
                        addCartBtn.textContent = 'Thêm vào giỏ';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = 'Có lỗi xảy ra: ' + error.message;
                    alertBox.style.display = 'block';
                    
                    addCartBtn.disabled = false;
                    buyNowBtn.disabled = false;
                    addCartBtn.textContent = 'Thêm vào giỏ';
                });
        });
    });
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const starInputs = document.querySelectorAll('.star-input');
    const soSaoInput = document.getElementById('so_Sao');
    if(!starInputs || !soSaoInput) return;

    function setStars(value){
        starInputs.forEach(si => {
            const v = parseInt(si.getAttribute('data-value'));
            if(v <= value) si.classList.add('filled'); else si.classList.remove('filled');
        });
        soSaoInput.value = value;
    }

    starInputs.forEach(si => {
        si.addEventListener('click', function(){
            const v = parseInt(this.getAttribute('data-value'));
            setStars(v);
        });
        si.addEventListener('mouseover', function(){
            const v = parseInt(this.getAttribute('data-value'));
            setStars(v);
        });
        si.addEventListener('mouseout', function(){
            setStars(parseInt(soSaoInput.value || 5));
        });
    });

    // Initialize default
    setStars(parseInt(soSaoInput.value || 5));
});
</script>

</body>
</html>
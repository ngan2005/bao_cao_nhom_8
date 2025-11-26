<?php
$currentPage = $_GET['page'] ?? 'TrangChu';

// $breadcrumb_map = [
//     'TrangChu' => ['Hàng Mới'],
//     'danhSachCombo' => ['Sản Phẩm', 'Combo'],
//     'danhSachAoNam' => ['Áo Nam'],
//     'danhSachQuanNam' => ['Quần Nam'],
//     'danhSachPhuKien' => ['Sản Phẩm', 'Phụ Kiện'],
// ];
// Lấy breadcrumb hiện tại
$currentBreadcrumb = $breadcrumb_map[$currentPage] ?? [];
?>

<nav class="main-nav">
    <ul class="menu">
        <li><a href="index.php?page=TrangChu" class="<?= $currentPage == 'TrangChu' ? 'active' : ''; ?>">HÀNG MỚI</a></li>
        <li>
            <a href="#">SẢN PHẨM</a>
            <ul class="submenu">
                <li><a href="index.php?page=danhSachCombo">Combo</a></li>
                <li><a href="index.php?page=danhSachAoNam">Áo</a></li>
                <li><a href="index.php?page=danhSachQuanNam">Quần</a></li>
                <li><a href="index.php?page=danhSachPhuKien">Phụ Kiện</a></li>
            </ul>
        </li>
        <li><a href="index.php?page=danhSachAoNam" >ÁO NAM</a></li>
        <li><a href="index.php?page=danhSachQuanNam" >QUẦN NAM</a></li>


    </ul>

    <?php if (!empty($currentBreadcrumb)): ?>
        <div class="breadcrumb">
            <a href="index.php?page=TrangChu">Trang chủ</a>
            <?php foreach ($currentBreadcrumb as $crumb): ?>
                <span class="breadcrumb-separator">  </span>
                <span><?= htmlspecialchars($crumb) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</nav>

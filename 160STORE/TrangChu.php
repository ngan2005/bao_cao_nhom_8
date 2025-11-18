<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Kết nối cơ sở dữ liệu, ví dụ: db_connection.php (nếu bạn cần kết nối cơ sở dữ liệu ở trang này)
require_once './includes/database.php';
require_once './includes/config.php';
// Bao gồm file header, sidebar, danh sách sản phẩm, footer
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang Chủ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/stylee.css">  <!-- Liên kết đến file CSS -->
    <link rel="stylesheet" href="assets/css/style_slider.css">  <!-- Liên kết đến file CSS slider -->
    <link rel="stylesheet" href="assets/css/maGiamGia.css"> 
</head>
<body>

    <main>
        <!-- Thanh trượt hình ảnh -->
          <div class="slider">
       <div class="slides">
          <div class="slide"><img src="https://file.hstatic.net/1000253775/file/banner_pc_3688a7ee993a48a3aa2ceda425abfa7b.jpg" alt="" width="1150"></div>
          <div class="slide"><img src="https://cdn.hstatic.net/files/1000253775/file/store_160_dk.jpg" alt="" width="1150"></div>
      </div>
          <button class="prev" onclick="moveSlide(-1)">❮</button>
          <button class="next" onclick="moveSlide(1)">❯</button>
      </div>
      <!---- mã giảm giá -->
      <?php include('./pages/danhSachMaGiamGia.php'); ?>
      <script src="assets/js/maGiamGia.js"></script>
      <!--- Hình ảnh ở giữa trang chủ -->
      <img src="https://file.hstatic.net/1000253775/file/banner_h_ng_m_i_6__1_.jpg" alt="hình mới" class="center-img" width="1150">
      <!-- ====== Link tới CSS và JS ====== -->
      <script src="assets/js/slider.js"></script>
        <!-- Bao gồm danh sách sản phẩm -->
        <?php include('./pages/danhSachSanPham.php'); ?>
    </main>
</body>
</html>
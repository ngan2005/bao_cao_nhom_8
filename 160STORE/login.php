<?php
// Hiển thị tất cả lỗi (chỉ nên bật trong môi trường phát triển)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bắt đầu output buffering để có thể sửa header sau khi có output
ob_start();

// Khởi động session để lưu/truy xuất thông tin người dùng
session_start();

// Nạp các file cấu hình và thư viện cần thiết
require_once './includes/database.php';     // lớp Database kết nối PDO
require_once './includes/config.php';       // cấu hình chung (nếu có)
require './includes/PHPMailer.php';         // thư viện PHPMailer (local copy)
require './includes/SMTP.php';              // phần SMTP của PHPMailer
require './includes/Exception.php';         // Exception của PHPMailer

// Sử dụng namespace của PHPMailer để dễ gọi class
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Tạo kết nối cơ sở dữ liệu bằng lớp Database
$db   = new Database();
$conn = $db->connect();
// Bật chế độ ném exception khi có lỗi PDO
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Biến dùng để chứa thông báo lỗi/thành công hiển thị ở giao diện
$msg = '';

/* ==== HÀM GỬI EMAIL QUA SMTP (PHPMailer) ==== */
/**
 * Gửi email chào mừng sau khi người dùng đăng ký
 * @param string $toEmail - địa chỉ nhận
 * @param string $toName  - tên người nhận (dùng trong nội dung)
 * @return bool - true khi gửi thành công, false khi lỗi
 */
function sendWelcomeEmail($toEmail, $toName) {
    // Tạo đối tượng PHPMailer (true bật exceptions nội bộ của PHPMailer)
    $mail = new PHPMailer(true);
    try {
        // Cấu hình gửi qua SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';          // máy chủ SMTP
        $mail->SMTPAuth   = true;                      // sử dụng xác thực
        $mail->Username   = 'luu.kimngan205@gmail.com';// username SMTP (ở đây là Gmail)
        $mail->Password   = 'kidh svem expv gojm';     // mật khẩu (App Password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // mã hóa STARTTLS
        $mail->Port       = 587;                       // cổng SMTP

        // Chế độ debug (0 = tắt). Chỉ bật khi test.
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = 'html';

        // Thiết lập charset và encoding
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // From và To
        $mail->setFrom('luu.kimngan205@gmail.com', '160STORE');
        $mail->addAddress($toEmail, $toName);

        // Nội dung email (HTML)
        $mail->isHTML(true);
        $mail->Subject = 'Chúc mừng bạn đã đăng ký thành công!';
        $mail->Body    = "<h2>Xin chào $toName!</h2><p>Bạn đã đăng ký tài khoản thành công tại <strong>160STORE</strong>.</p>";

        // Thực hiện gửi
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Ghi log lỗi để debug (không hiển thị trực tiếp cho user)
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/* ==== XỬ LÝ ĐĂNG NHẬP ==== */
// Kiểm tra nếu form Đăng nhập được submit (button name="dangNhap")
if (isset($_POST['dangNhap'])) {
    // Lấy dữ liệu từ form, trim để loại khoảng trắng thừa
    $user = trim($_POST['ten_Dang_Nhap']);
    $pass = trim($_POST['mat_Khau']);

    // Truy vấn người dùng theo tên đăng nhập
    $stmt = $conn->prepare("SELECT * FROM nguoi_dung WHERE ten_Dang_Nhap = ?");
    $stmt->execute([$user]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC); // Lấy 1 hàng dưới dạng mảng kết hợp

    // So sánh mật khẩu (LƯU Ý: hiện đang so sánh plaintext — nên băm mật khẩu trong thực tế)
    if ($u && $u['mat_Khau'] === $pass) {
        // Đăng nhập thành công: lưu thông tin user vào session
        $_SESSION['user'] = $u;
        // Chuẩn hóa role để so sánh
        $role = trim(strtolower($u['vai_Tro']));
        if ($role === 'admin') {
            // Nếu là admin thì chuyển đến trang admin
            header("Location: admin.php");
        } else {
            // Ngược lại chuyển về trang chủ ứng dụng (router dùng page=TrangChu)
            header("Location: index.php?page=TrangChu");
        }
        exit; // dừng script sau redirect
    } else {
        // Nếu thông tin không đúng, hiển thị thông báo lỗi
        $msg = "<div class='msg error'>Sai tài khoản hoặc mật khẩu!</div>";
    }
}

/* ==== XỬ LÝ ĐĂNG KÝ ==== */
// Kiểm tra nếu form Đăng ký được submit (button name="dangKy")
if (isset($_POST['dangKy'])) {
    // Lấy dữ liệu từ form đăng ký
    $ten   = trim($_POST['ten_Dang_Nhap']);
    $pass  = trim($_POST['mat_Khau']);
    $hoTen = trim($_POST['ho_Ten']);
    $email = trim($_POST['email']);
    $sdt = trim($_POST['sdt']);
    $dia_chi = trim($_POST['dia_chi']);

    // Thiết lập vai trò mặc định cho user mới và timestamp
    $vaiTro = 'khach_hang';
    $ngayTao = date('Y-m-d H:i:s');

    // Kiểm tra trùng tên đăng nhập trong DB
    $check = $conn->prepare("SELECT id_ND FROM nguoi_dung WHERE ten_Dang_Nhap = ?");
    $check->execute([$ten]);
    if ($check->fetch()) {
        // Nếu tồn tại, thông báo lỗi
        $msg = "<div class='msg error'>Tên đăng nhập đã tồn tại!</div>";
    } else {
        // Nếu chưa tồn tại, chèn user mới vào bảng `nguoi_dung`
        $stmt = $conn->prepare("INSERT INTO nguoi_dung 
    (ten_Dang_Nhap, mat_Khau, ho_Ten, email, sdt, dia_chi, vai_Tro, ngay_Tao)
    VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$ten, $pass, $hoTen, $email, $sdt, $dia_chi, $vaiTro, $ngayTao]);

        // Gọi hàm gửi email chào mừng (nếu cấu hình SMTP đúng)
        $emailSent = sendWelcomeEmail($email, $hoTen);
        $msg = $emailSent 
            ? "<div class='msg success'>Đăng ký thành công! 🎉 Email đã được gửi đến <strong>{$email}</strong></div>"
            : "<div class='msg success'>Đăng ký thành công! <br><small>(Email sẽ được gửi sau)</small></div>";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đăng nhập / Đăng ký</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!-- Font Awesome dùng cho icon (nếu có) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<!-- CSS riêng cho form login/register -->
<link rel="stylesheet" href="assets/logincss.css">
</head>
<body>
<div class="container">
  <!-- Tiêu đề form; thay đổi khi chuyển tab -->
  <h2 id="form-title">Đăng nhập</h2>
  <!-- Hiển thị thông báo (biến $msg được gán khi có lỗi/khuyến cáo) -->
  <?= $msg ?>
  <div class="tab">
    <button type="button" onclick="showForm('login')" id="btnLogin" class="active">Đăng nhập</button>
    <button type="button" onclick="showForm('register')" id="btnRegister">Đăng ký</button>
  </div>

  <!-- FORM ĐĂNG NHẬP -->
  <form method="POST" id="form-login">
    <!-- Tên đăng nhập -->
    <input type="text" name="ten_Dang_Nhap" placeholder="Tên đăng nhập" autocomplete="username" required>
    <div class="input-wrap">
      <!-- Mật khẩu -->
      <input type="password" name="mat_Khau" id="pass-login" placeholder="Mật khẩu" autocomplete="current-password" required>
    <!-- Nút hiện/ẩn mật khẩu (JS toggle) -->
    <span class="toggle-pass" onclick="togglePass('pass-login')"><i class="fa fa-eye" aria-hidden="true"></i></span>
    </div>
    <button type="submit" name="dangNhap">Đăng nhập</button>
  </form>

  <!-- FORM ĐĂNG KÝ -->
  <form method="POST" id="form-register" class="hidden">
    <!-- Ở đây form đăng ký dùng email làm ten_Dang_Nhap theo UI hiện tại -->
    <input type="email" name="ten_Dang_Nhap" placeholder="Email" autocomplete="Email" required>
    <input type="text" name="ho_Ten" placeholder="Họ tên" autocomplete="name" required>
    <input type="text" name="email" placeholder="Tên đăng nhập" >
    <div class="input-wrap">
    <input type="password" name="mat_Khau" id="pass-reg" placeholder="Mật khẩu" autocomplete="new-password" required>
    <span class="toggle-pass" onclick="togglePass('pass-reg')"><i class="fa fa-eye" aria-hidden="true"></i></span>
    </div>
    <input type="text" name="sdt" placeholder="Số điện thoại" required>
    <input type="text" name="dia_chi" placeholder="Địa chỉ" required>
    <button type="submit" name="dangKy">Đăng ký</button>
  </form>
</div>

<script>
/**
 * Chuyển hiển thị giữa form Đăng nhập và Đăng ký
 * name: 'login' hoặc 'register'
 */
function showForm(name) {
    // Ẩn tất cả form
    document.querySelectorAll('form').forEach(f => f.classList.add('hidden'));
    // Bỏ active ở tất cả tab
    document.querySelectorAll('.tab button').forEach(b => b.classList.remove('active'));
    // Hiển thị form tương ứng
    document.getElementById('form-' + name).classList.remove('hidden');
    // Set active cho nút tab tương ứng
    document.getElementById('btn' + name.charAt(0).toUpperCase() + name.slice(1)).classList.add('active');
    // Thay tiêu đề
    document.getElementById('form-title').innerText = name === 'login' ? 'Đăng nhập' : 'Đăng ký';
}

/**
 * Hiển thị / ẩn mật khẩu cho ô có id truyền vào
 * Lưu ý: hiện đơn giản chuyển type giữa 'password' và 'text'
 */
function togglePass(id) {
    const el = document.getElementById(id);
    el.type = (el.type === 'password') ? 'text' : 'password';
}
</script>
</body>
</html>
<?php
// Kết thúc output buffering và gửi output ra client
ob_end_flush();
?>

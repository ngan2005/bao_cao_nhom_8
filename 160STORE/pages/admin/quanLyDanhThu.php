<?php
// quanLyDanhThu.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/config.php';

$db = new Database();
$conn = $db->connect();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Lấy tháng và năm hiện tại (hoặc từ GET)
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$pageName = $_GET['page'] ?? 'quanLyDanhThu'; // Lấy page để form submit lại

try {
    // Lấy doanh thu theo ngày trong tháng
    $stmt = $conn->prepare("
        SELECT DAY(ngay_Dat) as ngay, SUM(tong_Tien) as doanhThu
        FROM don_hang
        WHERE MONTH(ngay_Dat) = :month AND YEAR(ngay_Dat) = :year
            AND trang_Thai = 'Đã giao'  -- Chỉ tính đơn đã giao
        GROUP BY DAY(ngay_Dat)
        ORDER BY DAY(ngay_Dat)
    ");
    $stmt->execute([':month' => $month, ':year' => $year]);
    $dataChart = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Chuẩn bị dữ liệu cho chart (lấp đầy các ngày không có doanh thu)
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $labels = [];
    $data = [];
    $tongDoanhThu = 0;
    
    // Tạo một mảng doanh thu với key là ngày
    $revenueByDay = [];
    foreach ($dataChart as $row) {
        $revenueByDay[$row['ngay']] = (float)$row['doanhThu'];
        $tongDoanhThu += (float)$row['doanhThu'];
    }

    // Lặp qua tất cả các ngày trong tháng
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $labels[] = "Ngày " . $day; // Label: "Ngày 1", "Ngày 2"...
        $data[] = $revenueByDay[$day] ?? 0; // Thêm 0 nếu ngày đó không có doanh thu
    }

    $soNgayCoDoanhThu = count($dataChart);
    $trungBinh = $soNgayCoDoanhThu > 0 ? $tongDoanhThu / $soNgayCoDoanhThu : 0;
    $maxDoanhThu = $tongDoanhThu > 0 ? max($data) : 0;

} catch (PDOException $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}
?>

<!-- Link CSS riêng cho trang này -->
<link rel="stylesheet" href="assets/Admin_css/quanLyDanhThu.css">
<!-- Đảm bảo Chart.js đã được load ở admin.php hoặc load ở đây nếu chưa -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="revenue-container">

    <!-- Thanh Lọc Filter -->
    <div class="filter-bar">
        <form method="GET">
            <!-- RẤT QUAN TRỌNG: giữ lại page khi submit -->
            <input type="hidden" name="page" value="<?= htmlspecialchars($pageName) ?>">
            
            <div class="filter-group">
                <label for="month">Chọn Tháng:</label>
                <input type="number" id="month" name="month" min="1" max="12" class="form-control" value="<?= htmlspecialchars($month) ?>">
            </div>
            <div class="filter-group">
                <label for="year">Chọn Năm:</label>
                <input type="number" id="year" name="year" min="2000" max="2100" class="form-control" value="<?= htmlspecialchars($year) ?>">
            </div>
            <div class="filter-group">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-filter"></i> Xem báo cáo
                </button>
            </div>
        </form>
    </div>

    <!-- Lưới Thống kê Nhanh -->
    <div class="stats-grid">
        <!-- Card 1: Tổng doanh thu -->
        <div class="stat-card total-revenue">
            <div class="icon-wrapper">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Tổng doanh thu (Tháng <?= htmlspecialchars($month) ?>)</span>
                <span class="stat-value"><?= number_format($tongDoanhThu, 0, ',', '.') ?> đ</span>
            </div>
        </div>

        <!-- Card 2: Trung bình/ngày (chỉ tính ngày có đơn) -->
        <div class="stat-card average-revenue">
            <div class="icon-wrapper">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Trung bình / ngày có đơn</span>
                <span class="stat-value"><?= number_format($trungBinh, 0, ',', '.') ?> đ</span>
            </div>
        </div>

        <!-- Card 3: Cao nhất -->
        <div class="stat-card max-revenue">
            <div class="icon-wrapper">
                <i class="fas fa-arrow-up"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Ngày doanh thu cao nhất</span>
                <span class="stat-value"><?= number_format($maxDoanhThu, 0, ',', '.') ?> đ</span>
            </div>
        </div>
    </div>

    <!-- Biểu đồ Doanh thu -->
    <div class="chart-container">
        <h3 class="chart-title">Biểu đồ doanh thu theo ngày (Tháng <?= htmlspecialchars($month) ?>/<?= htmlspecialchars($year) ?>)</h3>
        <canvas id="doanhThuChart"></canvas>
    </div>

</div>

<script>
    // Chuẩn bị dữ liệu từ PHP
    const chartLabels = <?= json_encode($labels) ?>;
    const chartData = <?= json_encode($data) ?>;
    
    const primaryColor = '#4361ee'; // Lấy từ :root CSS

    const ctx = document.getElementById('doanhThuChart').getContext('2d');
    
    // Tạo gradient cho background của line chart
    const gradient = ctx.createLinearGradient(0, 0, 0, 350);
    gradient.addColorStop(0, 'rgba(67, 97, 238, 0.3)');
    gradient.addColorStop(1, 'rgba(67, 97, 238, 0.05)');


    const doanhThuChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Doanh Thu (đ)',
                data: chartData,
                borderColor: primaryColor,
                backgroundColor: gradient, // Dùng gradient
                fill: true,
                tension: 0.4, // Làm mượt đường cong
                pointRadius: 4,
                pointBackgroundColor: primaryColor,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 6,
                pointHoverBackgroundColor: primaryColor,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // Cho phép set height tùy ý
            plugins: {
                legend: { 
                    display: false // Ẩn legend vì đã có label
                },
                tooltip: { 
                    mode: 'index', 
                    intersect: false,
                    backgroundColor: '#1e293b', // Tooltip màu tối
                    titleFont: { weight: 'bold' },
                    bodyFont: { size: 14 },
                    padding: 12,
                    cornerRadius: 6,
                    displayColors: false, // Ẩn ô màu trong tooltip
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                // Định dạng tiền tệ VNĐ
                                label += new Intl.NumberFormat('vi-VN').format(context.parsed.y) + ' đ';
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: { 
                    title: { display: false, text: 'Ngày trong tháng' },
                    grid: { display: false } // Ẩn lưới trục X
                },
                y: { 
                    title: { display: false, text: 'Doanh Thu (đ)' },
                    grid: { 
                        color: '#f1f5f9' // Làm mờ lưới trục Y
                    },
                    ticks: {
                        // Định dạng tiền tệ trên trục Y (ví dụ: 1Tr, 500K)
                        callback: function(value, index, ticks) {
                            return new Intl.NumberFormat('vi-VN', { 
                                notation: 'compact',
                                maximumFractionDigits: 1
                            }).format(value);
                        }
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });
</script>
// ========== SAO CHÉP MÃ GIẢM GIÁ ==========
function copyVoucher(code) {
  // Ghi mã vào clipboard
  navigator.clipboard.writeText(code)
    .then(() => {
      alert('Đã sao chép mã giảm giá: ' + code);
    })
    .catch((err) => {
      console.error('Không thể sao chép mã: ', err);
      alert('Trình duyệt của bạn không hỗ trợ sao chép tự động.');
    });
}

// ========== SLIDER SCRIPT ==========
let currentSlide = 0;

function moveSlide(step) {
  const slides = document.querySelectorAll(".slide");
  if (slides.length === 0) return; // tránh lỗi nếu chưa có ảnh
  currentSlide = (currentSlide + step + slides.length) % slides.length;
  document.querySelector(".slides").style.transform = `translateX(-${currentSlide * 100}%)`;
}

// Tự động chuyển slide sau mỗi 3 giây
setInterval(() => {
  moveSlide(1);
}, 3000);

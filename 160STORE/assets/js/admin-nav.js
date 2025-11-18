// === Điều khiển menu sidebar & breadcrumb ===
const navLinks = document.querySelectorAll('.nav-link');
const pageTitleElement = document.getElementById('pageTitle');
const breadcrumbTitleElement = document.getElementById('currentBreadcrumbTitle');

navLinks.forEach(link => {
    link.addEventListener('click', function () {
        navLinks.forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        const title = this.getAttribute('data-title');
        const icon = this.querySelector('i').outerHTML;
        pageTitleElement.innerHTML = icon + ' ' + title;
        breadcrumbTitleElement.textContent = title;
    });
});
// ==========================================
<aside class="sidebar">
    <div class="logo">
        <img src="https://file.hstatic.net/1000253775/file/logo_no_bf-05_3e6797f31bda4002a22464d6f2787316.png" alt="160STORE">
        <h2><a class="title_shop" href="index.php?page=TrangChu" style="text-decoration: none;color:var(--primary)">160STORE</a></h2>
    </div>
    <div class="admin-info">
        <div class="admin-avatar"><i class="fas fa-user-shield"></i></div>
        <h3><?= htmlspecialchars($admin['ho_Ten']) ?></h3>
        <p><?= htmlspecialchars($admin['email']) ?></p>
    </div>
    <nav>
        <ul class="nav-menu">
            <?php foreach($valid_pages as $key => $info): ?>
                <li class="nav-item">
                    <a href="?page=<?= $key ?>" class="nav-link <?= $page === $key ? 'active' : '' ?>" data-title="<?= htmlspecialchars($info['title']) ?>">
                        <?= $info['icon'] ?> <span><?= htmlspecialchars($info['title']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>
<?php
declare(strict_types=1);
$pageTitle = 'Available Services';
require_once __DIR__ . '/includes/header.php';

$search = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');

$services = [];
$categories = [];

try {
    $db = Database::getConnection();
    if ($db) {
        $catStmt = $db->query('SELECT DISTINCT category FROM services WHERE is_active = 1 AND category IS NOT NULL');
        $categories = $catStmt ? $catStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        $query = 'SELECT * FROM services WHERE is_active = 1';
        $params = [];

        if ($search !== '') {
            $query .= ' AND (name LIKE :q OR description LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        if ($category !== '') {
            $query .= ' AND category = :cat';
            $params['cat'] = $category;
        }

        $query .= ' ORDER BY sort_order ASC, name ASC';
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $services = $stmt ? $stmt->fetchAll() : [];
    }
} catch (Throwable $e) {
    error_log('[SERVICES ERROR] ' . $e->getMessage());
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Header -->
    <div class="max-w-3xl mb-10">
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Available Services</h1>
        <p class="mt-2 text-sm text-gray-600">
            Browse services available for SMS verification.
        </p>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="card-premium p-4 mb-8 flex flex-col md:flex-row gap-4 items-center justify-between">
        <form method="GET" class="flex-1 w-full flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search service by name..." 
                    class="w-full pl-10 pr-4 py-2 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            <?php if (!empty($categories)): ?>
                <select name="category" class="px-4 py-2 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9] bg-white">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e(ucfirst($cat)) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <button type="submit" class="btn-primary text-xs py-2 px-5">Filter</button>
            <?php if ($search || $category): ?>
                <a href="/services.php" class="btn-secondary text-xs py-2 px-3">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Services Grid -->
    <?php if (empty($services)): ?>
        <div class="card-premium p-12 text-center max-w-md mx-auto">
            <h3 class="text-base font-bold text-gray-900 mb-1">No services are currently available</h3>
            <p class="text-xs text-gray-500">Please check back later or contact support if you need a specific service.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($services as $svc): ?>
                <div class="card-premium p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center text-[#6D28D9]">
                                <?php if (!empty($svc['icon_svg'])): ?>
                                    <?= $svc['icon_svg'] ?>
                                <?php else: ?>
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 text-base leading-tight"><?= e($svc['name']) ?></h3>
                                <span class="text-xs text-gray-400 capitalize"><?= e($svc['category'] ?? 'General') ?></span>
                            </div>
                        </div>

                        <?php if (!empty($svc['description'])): ?>
                            <p class="text-xs text-gray-600 line-clamp-2 mb-4 leading-relaxed">
                                <?= e($svc['description']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
                        <span class="badge-success text-xs">Available</span>
                        <a href="/user/activate.php?service=<?= e($svc['code']) ?>" class="btn-primary text-xs py-1.5 px-3">
                            Get Number &rarr;
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);
$adminTitle = 'Financial & Operational Reports';
require_once dirname(__DIR__) . '/includes/admin_header.php';
require_admin('pricing.manage');

$db = Database::getConnection();
$range = $_GET['range'] ?? '30d';

$dateCondition = match ($range) {
    'today' => 'DATE(o.created_at) = CURDATE()',
    'yesterday' => 'DATE(o.created_at) = (CURDATE() - INTERVAL 1 DAY)',
    '7d'    => 'o.created_at >= (NOW() - INTERVAL 7 DAY)',
    default => 'o.created_at >= (NOW() - INTERVAL 30 DAY)'
};

$totals = [
    'revenue'      => 0.0,
    'cost'         => 0.0,
    'margin'       => 0.0,
    'orders_count' => 0,
    'success_rate' => 0.0
];

$countryStats = [];
$serviceStats = [];

try {
    // 1. Overall Aggregates
    $aggStmt = $db->query("
        SELECT
            COUNT(*) AS total_orders,
            SUM(CASE WHEN o.status = 'completed' THEN o.price ELSE 0 END) AS total_revenue,
            SUM(CASE WHEN o.status = 'completed' THEN o.provider_cost ELSE 0 END) AS total_cost,
            SUM(CASE WHEN o.status = 'completed' THEN o.margin ELSE 0 END) AS total_margin,
            SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) AS completed_count
        FROM orders o
        WHERE $dateCondition
    ");
    $agg = $aggStmt->fetch();

    $totals['orders_count'] = (int)($agg['total_orders'] ?? 0);
    $totals['revenue'] = (float)($agg['total_revenue'] ?? 0.0);
    $totals['cost'] = (float)($agg['total_cost'] ?? 0.0);
    $totals['margin'] = (float)($agg['total_margin'] ?? 0.0);

    $completedCount = (int)($agg['completed_count'] ?? 0);
    $totals['success_rate'] = $totals['orders_count'] > 0 ? round(($completedCount / $totals['orders_count']) * 100, 1) : 0.0;

    // 2. Country Breakdown
    $cStmt = $db->query("
        SELECT c.name, c.flag_emoji, COUNT(o.id) AS order_count, SUM(o.price) AS country_revenue
        FROM orders o
        JOIN countries c ON c.id = o.country_id
        WHERE $dateCondition AND o.status = 'completed'
        GROUP BY c.id
        ORDER BY country_revenue DESC
        LIMIT 8
    ");
    $countryStats = $cStmt->fetchAll();

    // 3. Service Breakdown
    $sStmt = $db->query("
        SELECT s.name, COUNT(o.id) AS order_count, SUM(o.price) AS service_revenue
        FROM orders o
        JOIN services s ON s.id = o.service_id
        WHERE $dateCondition AND o.status = 'completed'
        GROUP BY s.id
        ORDER BY service_revenue DESC
        LIMIT 8
    ");
    $serviceStats = $sStmt->fetchAll();

} catch (Throwable $e) {
    error_log('[REPORTS ERROR] ' . $e->getMessage());
}
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-extrabold text-gray-900">Analytics & Performance Reports</h2>
            <p class="text-xs text-gray-500 mt-0.5">Calculated strictly from authoritative database order records.</p>
        </div>

        <!-- Range Filter Buttons -->
        <div class="flex items-center gap-1.5 text-xs">
            <a href="?range=today" class="px-3 py-1.5 rounded-lg <?= $range === 'today' ? 'bg-[#6D28D9] text-white font-bold' : 'bg-white text-gray-600 border border-gray-200' ?>">Today</a>
            <a href="?range=yesterday" class="px-3 py-1.5 rounded-lg <?= $range === 'yesterday' ? 'bg-[#6D28D9] text-white font-bold' : 'bg-white text-gray-600 border border-gray-200' ?>">Yesterday</a>
            <a href="?range=7d" class="px-3 py-1.5 rounded-lg <?= $range === '7d' ? 'bg-[#6D28D9] text-white font-bold' : 'bg-white text-gray-600 border border-gray-200' ?>">Last 7 Days</a>
            <a href="?range=30d" class="px-3 py-1.5 rounded-lg <?= $range === '30d' ? 'bg-[#6D28D9] text-white font-bold' : 'bg-white text-gray-600 border border-gray-200' ?>">Last 30 Days</a>
        </div>
    </div>

    <!-- Aggregates Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card-premium p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase">Gross Revenue</div>
            <div class="text-2xl font-extrabold text-gray-900 font-mono mt-1"><?= format_currency($totals['revenue']) ?></div>
            <div class="text-[11px] text-gray-400 mt-1">Period total</div>
        </div>

        <div class="card-premium p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase">Gross Margin</div>
            <div class="text-2xl font-extrabold text-[#6D28D9] font-mono mt-1"><?= format_currency($totals['margin']) ?></div>
            <div class="text-[11px] text-gray-400 mt-1">Carrier Cost: <?= format_currency($totals['cost']) ?></div>
        </div>

        <div class="card-premium p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase">Total Activations</div>
            <div class="text-2xl font-extrabold text-gray-900 font-mono mt-1"><?= number_format($totals['orders_count']) ?></div>
            <div class="text-[11px] text-gray-400 mt-1">Requested lines</div>
        </div>

        <div class="card-premium p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase">Fulfillment Rate</div>
            <div class="text-2xl font-extrabold text-green-600 font-mono mt-1"><?= $totals['success_rate'] ?>%</div>
            <div class="text-[11px] text-gray-400 mt-1">Delivered SMS</div>
        </div>
    </div>

    <!-- Breakdown Tables -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- By Country -->
        <div class="card-premium p-6">
            <h3 class="font-bold text-gray-900 text-sm mb-4">Top Regions by Revenue</h3>
            <?php if (empty($countryStats)): ?>
                <div class="py-8 text-center text-xs text-gray-400">No completed orders in this date range.</div>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($countryStats as $cs): ?>
                        <div class="py-2.5 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <span><?= e($cs['flag_emoji']) ?></span>
                                <span class="font-bold text-gray-900"><?= e($cs['name']) ?></span>
                                <span class="text-gray-400 text-[10px] font-mono">(<?= (int)$cs['order_count'] ?> orders)</span>
                            </div>
                            <div class="font-mono font-bold text-gray-900">
                                <?= format_currency($cs['country_revenue']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- By Service -->
        <div class="card-premium p-6">
            <h3 class="font-bold text-gray-900 text-sm mb-4">Top Services by Volume</h3>
            <?php if (empty($serviceStats)): ?>
                <div class="py-8 text-center text-xs text-gray-400">No completed orders in this date range.</div>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($serviceStats as $ss): ?>
                        <div class="py-2.5 flex items-center justify-between text-xs">
                            <div>
                                <span class="font-bold text-gray-900"><?= e($ss['name']) ?></span>
                                <span class="text-gray-400 text-[10px] font-mono ml-1">(<?= (int)$ss['order_count'] ?> orders)</span>
                            </div>
                            <div class="font-mono font-bold text-gray-900">
                                <?= format_currency($ss['service_revenue']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>

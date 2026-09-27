<?php
declare(strict_types=1);
$adminTitle = 'System Operations Dashboard';
require_once dirname(__DIR__) . '/includes/admin_header.php';

$db = Database::getConnection();

// Real Database Metrics (Rule #4: strictly from MySQL, zero fake data)
$stats = [
    'total_users'        => 0,
    'new_users_today'    => 0,
    'total_revenue'      => 0.0,
    'today_revenue'      => 0.0,
    'total_provider_cost'=> 0.0,
    'total_margin'       => 0.0,
    'active_activations' => 0,
    'completed_orders'   => 0,
    'refunded_orders'    => 0,
    'total_deposits'     => 0.0,
];

$providers = [];
$recentOrders = [];

try {
    // 1. Users Counts
    $uStmt = $db->query('
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today
        FROM users
        WHERE role = "user"
    ');
    $uRow = $uStmt->fetch();
    $stats['total_users'] = (int)($uRow['total'] ?? 0);
    $stats['new_users_today'] = (int)($uRow['today'] ?? 0);

    // 2. Financial Metrics from Orders
    $finStmt = $db->query('
        SELECT
            SUM(CASE WHEN status = "completed" THEN price ELSE 0 END) AS rev_total,
            SUM(CASE WHEN status = "completed" AND DATE(created_at) = CURDATE() THEN price ELSE 0 END) AS rev_today,
            SUM(CASE WHEN status = "completed" THEN provider_cost ELSE 0 END) AS cost_total,
            SUM(CASE WHEN status = "completed" THEN margin ELSE 0 END) AS margin_total,
            SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) AS act_count,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS comp_count,
            SUM(CASE WHEN status = "refunded" THEN 1 ELSE 0 END) AS ref_count
        FROM orders
    ');
    $finRow = $finStmt->fetch();
    $stats['total_revenue'] = (float)($finRow['rev_total'] ?? 0.0);
    $stats['today_revenue'] = (float)($finRow['rev_today'] ?? 0.0);
    $stats['total_provider_cost'] = (float)($finRow['cost_total'] ?? 0.0);
    $stats['total_margin'] = (float)($finRow['margin_total'] ?? 0.0);
    $stats['active_activations'] = (int)($finRow['act_count'] ?? 0);
    $stats['completed_orders'] = (int)($finRow['comp_count'] ?? 0);
    $stats['refunded_orders'] = (int)($finRow['ref_count'] ?? 0);

    // 3. Wallet Deposits
    $depStmt = $db->query('SELECT SUM(amount) FROM wallet_transactions WHERE type IN ("credit", "payment") AND status = "completed"');
    $stats['total_deposits'] = (float)($depStmt->fetchColumn() ?: 0.0);

    // 4. Provider Status & Health
    $pStmt = $db->query('SELECT * FROM providers ORDER BY priority ASC, name ASC');
    $providers = $pStmt->fetchAll();

    // 5. Recent System Orders
    $roStmt = $db->query('
        SELECT o.*, u.email AS user_email, s.name AS service_name, c.name AS country_name, c.flag_emoji
        FROM orders o
        JOIN users u ON u.id = o.user_id
        JOIN services s ON s.id = o.service_id
        JOIN countries c ON c.id = o.country_id
        ORDER BY o.created_at DESC
        LIMIT 6
    ');
    $recentOrders = $roStmt->fetchAll();

} catch (Throwable $e) {
    error_log('[ADMIN DASHBOARD ERROR] ' . $e->getMessage());
}
?>

<!-- Metrics Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="card-premium p-5">
        <div class="text-xs font-semibold text-gray-500 uppercase">Gross Platform Revenue</div>
        <div class="text-2xl font-extrabold text-gray-900 font-mono mt-1">
            <?= format_currency($stats['total_revenue']) ?>
        </div>
        <div class="text-[11px] text-green-600 font-semibold mt-1">
            Today: <?= format_currency($stats['today_revenue']) ?>
        </div>
    </div>

    <div class="card-premium p-5">
        <div class="text-xs font-semibold text-gray-500 uppercase">Gross Margin (Profit)</div>
        <div class="text-2xl font-extrabold text-[#6D28D9] font-mono mt-1">
            <?= format_currency($stats['total_margin']) ?>
        </div>
        <div class="text-[11px] text-gray-400 mt-1">
            Provider Cost: <?= format_currency($stats['total_provider_cost']) ?>
        </div>
    </div>

    <div class="card-premium p-5">
        <div class="text-xs font-semibold text-gray-500 uppercase">Total User Accounts</div>
        <div class="text-2xl font-extrabold text-gray-900 font-mono mt-1">
            <?= number_format($stats['total_users']) ?>
        </div>
        <div class="text-[11px] text-purple-700 font-semibold mt-1">
            +<?= $stats['new_users_today'] ?> new registrations today
        </div>
    </div>

    <div class="card-premium p-5">
        <div class="text-xs font-semibold text-gray-500 uppercase">Active Activations</div>
        <div class="text-2xl font-extrabold text-blue-600 font-mono mt-1">
            <?= $stats['active_activations'] ?>
        </div>
        <div class="text-[11px] text-gray-400 mt-1">
            <?= $stats['completed_orders'] ?> completed • <?= $stats['refunded_orders'] ?> refunded
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-8">
    <!-- Active Carrier Upstream Providers -->
    <div class="lg:col-span-5">
        <div class="card-premium p-6">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
                <h3 class="font-bold text-gray-900 text-sm">Upstream Carrier Providers</h3>
                <a href="/admin/providers.php" class="text-xs text-[#6D28D9] hover:underline font-semibold">Manage &rarr;</a>
            </div>

            <?php if (empty($providers)): ?>
                <div class="py-8 text-center text-xs text-gray-400">
                    No providers configured. <a href="/admin/providers.php" class="text-[#6D28D9] underline">Add your first provider</a>.
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($providers as $p): ?>
                        <div class="p-3.5 rounded-xl border border-gray-100 flex items-center justify-between text-xs">
                            <div>
                                <div class="font-bold text-gray-900"><?= e($p['name']) ?></div>
                                <div class="text-[10px] text-gray-400 font-mono"><?= e($p['adapter_class']) ?> • Priority: <?= (int)$p['priority'] ?></div>
                            </div>
                            <div class="text-right">
                                <span class="capitalize text-[10px] font-bold <?= $p['health_status'] === 'healthy' ? 'text-green-600' : 'text-amber-600' ?>">
                                    ● <?= e($p['health_status']) ?>
                                </span>
                                <div class="font-mono font-bold text-gray-700 mt-0.5">
                                    Bal: <?= format_currency($p['balance']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Live System Activations Stream -->
    <div class="lg:col-span-7">
        <div class="card-premium p-6">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
                <h3 class="font-bold text-gray-900 text-sm">Recent Platform Orders</h3>
                <a href="/admin/orders.php" class="text-xs text-[#6D28D9] hover:underline font-semibold">All Orders &rarr;</a>
            </div>

            <?php if (empty($recentOrders)): ?>
                <div class="py-8 text-center text-xs text-gray-400">
                    No customer orders placed yet.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="text-gray-400 uppercase text-[10px] border-b border-gray-100">
                                <th class="pb-2">Order</th>
                                <th class="pb-2">User</th>
                                <th class="pb-2">Service</th>
                                <th class="pb-2 text-right">Price</th>
                                <th class="pb-2 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($recentOrders as $ro): ?>
                                <tr>
                                    <td class="py-2.5 font-mono font-bold text-gray-900">#<?= e($ro['order_number']) ?></td>
                                    <td class="py-2.5 text-gray-600 truncate max-w-[120px]"><?= e($ro['user_email']) ?></td>
                                    <td class="py-2.5">
                                        <span class="mr-1"><?= e($ro['flag_emoji']) ?></span>
                                        <span class="font-semibold text-gray-900"><?= e($ro['service_name']) ?></span>
                                    </td>
                                    <td class="py-2.5 text-right font-mono font-bold text-gray-900"><?= format_currency($ro['price']) ?></td>
                                    <td class="py-2.5 text-right">
                                        <span class="capitalize text-[10px] font-semibold <?= match($ro['status']) {
                                            'completed' => 'text-green-600',
                                            'active'    => 'text-[#6D28D9]',
                                            'refunded'  => 'text-amber-600',
                                            default     => 'text-gray-500'
                                        } ?>">
                                            <?= e($ro['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>

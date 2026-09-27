<?php
declare(strict_types=1);
$pageTitle = 'User Dashboard';
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$user = current_user();
$userId = (int)$user['id'];
$db = Database::getConnection();

// Fetch real metrics from MySQL (Rule #4: No fake numbers)
$metrics = [
    'active'    => 0,
    'completed' => 0,
    'failed'    => 0,
    'refunded'  => 0
];

$activeActivations = [];
$recentOrders = [];
$recentTransactions = [];
$notifications = [];

try {
    // 1. Order Status Counts
    $countStmt = $db->prepare('
        SELECT status, COUNT(*) AS total
        FROM orders
        WHERE user_id = :uid
        GROUP BY status
    ');
    $countStmt->execute(['uid' => $userId]);
    while ($row = $countStmt->fetch()) {
        $st = $row['status'];
        if (isset($metrics[$st])) {
            $metrics[$st] = (int)$row['total'];
        }
    }

    // 2. Active Activations
    $actStmt = $db->prepare('
        SELECT a.*, o.order_number, o.price, s.name AS service_name, c.name AS country_name, c.flag_emoji
        FROM activations a
        JOIN orders o ON o.id = a.order_id
        JOIN services s ON s.id = o.service_id
        JOIN countries c ON c.id = o.country_id
        WHERE a.user_id = :uid AND a.status IN ("waiting_sms", "waiting", "number_assigned")
        ORDER BY a.created_at DESC
    ');
    $actStmt->execute(['uid' => $userId]);
    $activeActivations = $actStmt->fetchAll();
    $metrics['active'] = count($activeActivations);

    // 3. Recent Orders
    $orderStmt = $db->prepare('
        SELECT o.*, s.name AS service_name, c.name AS country_name, c.flag_emoji, a.phone_number
        FROM orders o
        JOIN services s ON s.id = o.service_id
        JOIN countries c ON c.id = o.country_id
        LEFT JOIN activations a ON a.order_id = o.id
        WHERE o.user_id = :uid
        ORDER BY o.created_at DESC
        LIMIT 5
    ');
    $orderStmt->execute(['uid' => $userId]);
    $recentOrders = $orderStmt->fetchAll();

    // 4. Recent Wallet Transactions
    $txStmt = $db->prepare('
        SELECT * FROM wallet_transactions
        WHERE user_id = :uid
        ORDER BY created_at DESC
        LIMIT 5
    ');
    $txStmt->execute(['uid' => $userId]);
    $recentTransactions = $txStmt->fetchAll();

    // 5. Notifications
    $notifStmt = $db->prepare('
        SELECT * FROM notifications
        WHERE user_id = :uid
        ORDER BY created_at DESC
        LIMIT 4
    ');
    $notifStmt->execute(['uid' => $userId]);
    $notifications = $notifStmt->fetchAll();

} catch (Throwable $e) {
    error_log('[DASHBOARD FETCH ERROR] ' . $e->getMessage());
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Welcome Topbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">
                Welcome back, <?= e($user['name']) ?>
            </h1>
            <p class="text-xs text-gray-500 mt-1">
                Account ID: <span class="font-mono text-gray-700"><?= e($user['uuid']) ?></span>
                <?php if (!empty($user['group_name'])): ?>
                    • <span class="badge-purple font-semibold text-[11px]"><?= e($user['group_name']) ?></span>
                <?php endif; ?>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="/user/activate.php" class="btn-primary text-xs py-2.5 px-4 shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                New Activation
            </a>
            <a href="/user/wallet.php" class="btn-secondary text-xs py-2.5 px-4">
                Add Funds
            </a>
        </div>
    </div>

    <!-- Metric Cards Grid -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-10">
        <!-- Balance Card -->
        <div class="card-premium p-5 col-span-2 sm:col-span-1 bg-gradient-to-br from-white to-purple-50/40 border-purple-100">
            <div class="text-xs font-semibold text-[#6D28D9] uppercase tracking-wider mb-1">Wallet Balance</div>
            <div class="text-2xl font-extrabold text-gray-900 font-mono">
                <?= format_currency($user['wallet_balance'] ?? 0.0) ?>
            </div>
            <div class="mt-2 text-[11px] text-gray-500">
                <a href="/user/wallet.php" class="text-[#6D28D9] font-medium hover:underline">Manage Wallet &rarr;</a>
            </div>
        </div>

        <div class="card-premium p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Active</div>
            <div class="text-2xl font-extrabold text-[#6D28D9] font-mono">
                <?= $metrics['active'] ?>
            </div>
            <div class="mt-2 text-[11px] text-gray-400">Waiting for code</div>
        </div>

        <div class="card-premium p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Completed</div>
            <div class="text-2xl font-extrabold text-[#16A34A] font-mono">
                <?= $metrics['completed'] ?>
            </div>
            <div class="mt-2 text-[11px] text-gray-400">SMS delivered</div>
        </div>

        <div class="card-premium p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Refunded</div>
            <div class="text-2xl font-extrabold text-[#F59E0B] font-mono">
                <?= $metrics['refunded'] ?>
            </div>
            <div class="mt-2 text-[11px] text-gray-400">Timeout / Cancelled</div>
        </div>

        <div class="card-premium p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Failed</div>
            <div class="text-2xl font-extrabold text-gray-400 font-mono">
                <?= $metrics['failed'] ?>
            </div>
            <div class="mt-2 text-[11px] text-gray-400">Provider errors</div>
        </div>
    </div>

    <!-- Active Activations Section (Real-Time Attention) -->
    <?php if (!empty($activeActivations)): ?>
        <div class="mb-10">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse"></span>
                    <h2 class="text-base font-bold text-gray-900">Active Live Activations</h2>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($activeActivations as $act): ?>
                    <div class="card-premium p-5 border-purple-200 bg-white">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center gap-2">
                                <span class="text-lg"><?= e($act['flag_emoji']) ?></span>
                                <div>
                                    <div class="font-bold text-gray-900 text-sm"><?= e($act['service_name']) ?></div>
                                    <div class="text-xs text-gray-500"><?= e($act['country_name']) ?></div>
                                </div>
                            </div>
                            <span class="badge-purple font-mono text-xs">Waiting SMS</span>
                        </div>

                        <div class="bg-gray-50 rounded-xl p-3 mb-4 flex items-center justify-between">
                            <span class="font-mono font-bold text-gray-900 text-sm tracking-wider"><?= e($act['full_phone_number']) ?></span>
                            <button data-copy="<?= e($act['full_phone_number']) ?>" class="btn-secondary text-[11px] py-1 px-2">
                                Copy
                            </button>
                        </div>

                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-400">Order: <?= e($act['order_number']) ?></span>
                            <a href="/user/active.php?id=<?= (int)$act['id'] ?>" class="btn-primary text-xs py-1.5 px-3">
                                Live Inbound &rarr;
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Two-Column Grid: Recent Orders & Wallet Transactions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
        <!-- Recent Orders -->
        <div class="card-premium p-6">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
                <h3 class="font-bold text-gray-900 text-sm">Recent Orders</h3>
                <a href="/user/orders.php" class="text-xs text-[#6D28D9] font-medium hover:underline">View All Orders &rarr;</a>
            </div>

            <?php if (empty($recentOrders)): ?>
                <div class="py-8 text-center text-xs text-gray-400">
                    No orders placed yet. <a href="/user/activate.php" class="text-[#6D28D9] font-medium hover:underline">Start your first activation</a>.
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($recentOrders as $order): ?>
                        <div class="py-3 flex items-center justify-between text-xs">
                            <div>
                                <div class="font-bold text-gray-900">
                                    <?= e($order['flag_emoji']) ?> <?= e($order['service_name']) ?>
                                    <span class="text-gray-400 font-normal ml-1">#<?= e($order['order_number']) ?></span>
                                </div>
                                <div class="text-gray-400 mt-0.5"><?= date('M j, Y H:i', strtotime($order['created_at'])) ?> UTC</div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono font-bold text-gray-900"><?= format_currency($order['price']) ?></div>
                                <span class="capitalize text-[10px] font-semibold <?= match($order['status']) {
                                    'completed' => 'text-green-600',
                                    'active'    => 'text-[#6D28D9]',
                                    'refunded'  => 'text-amber-600',
                                    default     => 'text-gray-500'
                                } ?>">
                                    <?= e($order['status']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Transactions -->
        <div class="card-premium p-6">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
                <h3 class="font-bold text-gray-900 text-sm">Wallet Activity</h3>
                <a href="/user/wallet.php" class="text-xs text-[#6D28D9] font-medium hover:underline">View Ledger &rarr;</a>
            </div>

            <?php if (empty($recentTransactions)): ?>
                <div class="py-8 text-center text-xs text-gray-400">
                    No transactions recorded in wallet ledger.
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($recentTransactions as $tx): ?>
                        <div class="py-3 flex items-center justify-between text-xs">
                            <div>
                                <div class="font-medium text-gray-800"><?= e($tx['description']) ?></div>
                                <div class="text-gray-400 font-mono text-[10px]"><?= e($tx['transaction_ref']) ?> • <?= date('M j, H:i', strtotime($tx['created_at'])) ?></div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono font-bold <?= $tx['type'] === 'debit' ? 'text-gray-800' : 'text-green-600' ?>">
                                    <?= $tx['type'] === 'debit' ? '-' : '+' ?><?= format_currency($tx['amount']) ?>
                                </div>
                                <div class="text-[10px] text-gray-400 font-mono">Bal: <?= format_currency($tx['balance_after']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Navigation Footnotes -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
        <a href="/user/orders.php" class="card-premium p-4 flex items-center gap-3 hover:bg-gray-50 transition-colors">
            <div class="w-8 h-8 rounded-lg bg-purple-50 text-[#6D28D9] flex items-center justify-center font-bold">📦</div>
            <div>
                <div class="font-bold text-gray-900">Order History</div>
                <div class="text-gray-400">View past SMS codes</div>
            </div>
        </a>
        <a href="/user/support.php" class="card-premium p-4 flex items-center gap-3 hover:bg-gray-50 transition-colors">
            <div class="w-8 h-8 rounded-lg bg-purple-50 text-[#6D28D9] flex items-center justify-center font-bold">💬</div>
            <div>
                <div class="font-bold text-gray-900">Support Desk</div>
                <div class="text-gray-400">Tickets & assistance</div>
            </div>
        </a>
        <a href="/user/api.php" class="card-premium p-4 flex items-center gap-3 hover:bg-gray-50 transition-colors">
            <div class="w-8 h-8 rounded-lg bg-purple-50 text-[#6D28D9] flex items-center justify-center font-bold">🔑</div>
            <div>
                <div class="font-bold text-gray-900">Developer API</div>
                <div class="text-gray-400">Keys & documentation</div>
            </div>
        </a>
        <a href="/user/profile.php" class="card-premium p-4 flex items-center gap-3 hover:bg-gray-50 transition-colors">
            <div class="w-8 h-8 rounded-lg bg-purple-50 text-[#6D28D9] flex items-center justify-center font-bold">⚙️</div>
            <div>
                <div class="font-bold text-gray-900">Account Security</div>
                <div class="text-gray-400">Passwords & logs</div>
            </div>
        </a>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

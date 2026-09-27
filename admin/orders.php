<?php
declare(strict_types=1);
$adminTitle = 'Platform Orders & Activations';
require_once dirname(__DIR__) . '/includes/admin_header.php';
require_admin('orders.manage');

$db = Database::getConnection();
$admin = current_user();
$adminId = (int)$admin['id'];

$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');
$message = '';
$error = '';

// Handle Admin Refund Trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'issue_refund') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Session validation error.';
    } else {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Administrative manual refund override');

        $refRes = RefundEngine::process($orderId, $reason, 'admin', $adminId);
        if ($refRes['success']) {
            $message = 'Refund processed and credited back to customer wallet balance.';
        } else {
            $error = $refRes['message'];
        }
    }
}

// Fetch orders
$orders = [];
try {
    $sql = '
        SELECT o.*, u.email AS user_email, u.name AS user_name,
               s.name AS service_name, c.name AS country_name, c.flag_emoji,
               p.name AS provider_name,
               a.id AS activation_id, a.phone_number, a.full_phone_number,
               (SELECT verification_code FROM sms_messages WHERE activation_id = a.id ORDER BY id DESC LIMIT 1) AS last_code
        FROM orders o
        JOIN users u ON u.id = o.user_id
        JOIN services s ON s.id = o.service_id
        JOIN countries c ON c.id = o.country_id
        JOIN providers p ON p.id = o.provider_id
        LEFT JOIN activations a ON a.order_id = o.id
        WHERE 1=1
    ';
    $params = [];

    if ($statusFilter !== '') {
        $sql .= ' AND o.status = :st';
        $params['st'] = $statusFilter;
    }

    if ($search !== '') {
        $sql .= ' AND (o.order_number LIKE :q OR u.email LIKE :q OR a.phone_number LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY o.created_at DESC LIMIT 50';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[ADMIN ORDERS FETCH ERROR] ' . $e->getMessage());
}
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-extrabold text-gray-900">Platform Orders & Activations</h2>
            <p class="text-xs text-gray-500 mt-0.5">Inspect real-time carrier allocations, delivery statuses, costs, and refund requests.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="card-premium p-4 bg-green-50 border-green-200 text-green-800 text-xs font-semibold">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="card-premium p-4 bg-red-50 border-red-200 text-red-700 text-xs font-semibold">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="card-premium p-4 flex flex-col sm:flex-row gap-3">
        <form method="GET" class="flex-1 flex flex-col sm:flex-row gap-3">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by order ID, email, or number..."
                class="flex-1 px-3.5 py-2 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">

            <select name="status" class="px-3.5 py-2 text-xs border border-gray-200 rounded-xl bg-white focus:outline-hidden focus:border-[#6D28D9]">
                <option value="">All Statuses</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
            </select>

            <button type="submit" class="btn-primary text-xs py-2 px-4">Filter</button>
            <?php if ($search || $statusFilter): ?>
                <a href="/admin/orders.php" class="btn-secondary text-xs py-2 px-3">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="card-premium overflow-hidden">
        <?php if (empty($orders)): ?>
            <div class="p-12 text-center text-xs text-gray-400">
                No orders match your filter criteria.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 font-semibold uppercase tracking-wider">
                            <th class="py-3 px-6">Order ID</th>
                            <th class="py-3 px-6">Customer</th>
                            <th class="py-3 px-6">Service / Region</th>
                            <th class="py-3 px-6">Carrier Line</th>
                            <th class="py-3 px-6 font-mono">SMS Code</th>
                            <th class="py-3 px-6 text-right">Price</th>
                            <th class="py-3 px-6 text-right">Margin</th>
                            <th class="py-3 px-6 text-center">Status</th>
                            <th class="py-3 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($orders as $o): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3 px-6 font-mono font-bold text-gray-900">#<?= e($o['order_number']) ?></td>
                                <td class="py-3 px-6">
                                    <div class="font-bold text-gray-900"><?= e($o['user_name']) ?></div>
                                    <div class="text-gray-400 text-[10px]"><?= e($o['user_email']) ?></div>
                                </td>
                                <td class="py-3 px-6">
                                    <div class="font-bold text-gray-900"><?= e($o['service_name']) ?></div>
                                    <div class="text-gray-400 text-[10px]"><?= e($o['flag_emoji']) ?> <?= e($o['country_name']) ?> • <?= e($o['provider_name']) ?></div>
                                </td>
                                <td class="py-3 px-6 font-mono text-gray-700"><?= e($o['full_phone_number'] ?: '—') ?></td>
                                <td class="py-3 px-6 font-mono font-bold text-[#6D28D9]"><?= e($o['last_code'] ?: '—') ?></td>
                                <td class="py-3 px-6 text-right font-mono font-bold text-gray-900"><?= format_currency($o['price']) ?></td>
                                <td class="py-3 px-6 text-right font-mono text-green-600 font-semibold">+<?= format_currency($o['margin']) ?></td>
                                <td class="py-3 px-6 text-center">
                                    <span class="capitalize text-[10px] font-semibold <?= match($o['status']) {
                                        'completed' => 'badge-success',
                                        'active'    => 'badge-purple',
                                        'refunded'  => 'badge-warning',
                                        default     => 'badge-danger'
                                    } ?>">
                                        <?= e($o['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-right">
                                    <?php if ($o['status'] === 'active' || ($o['status'] === 'failed' && $o['price'] > 0)): ?>
                                        <form method="POST" onsubmit="return confirm('Confirm issuing administrative refund for Order #<?= e($o['order_number']) ?>?');" class="inline-block">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="issue_refund">
                                            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                            <input type="hidden" name="reason" value="Administrative refund intervention">
                                            <button type="submit" class="btn-secondary text-[10px] py-1 px-2 text-red-600 hover:bg-red-50">
                                                Refund
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-[11px]">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>

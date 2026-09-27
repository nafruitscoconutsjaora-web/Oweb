<?php
declare(strict_types=1);
$adminTitle = 'User Management & Balances';
require_once dirname(__DIR__) . '/includes/admin_header.php';
require_admin('users.view');

$db = Database::getConnection();
$admin = current_user();
$adminId = (int)$admin['id'];

$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$message = '';
$error = '';

// Handle User Status Toggle (Suspend / Unsuspend)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    require_admin('users.manage');
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Session validation failed.';
    } else {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $newStatus = $_POST['new_status'] === 'suspended' ? 'suspended' : 'active';

        try {
            $upd = $db->prepare('UPDATE users SET status = :st, updated_at = NOW() WHERE id = :id');
            $upd->execute(['st' => $newStatus, 'id' => $targetUserId]);

            AuditLogger::log('user.status_change', 'user', (string)$targetUserId, null, ['status' => $newStatus], 'admin');
            $message = 'User status successfully updated to ' . $newStatus . '.';
        } catch (Throwable $e) {
            error_log('[USER STATUS ERROR] ' . $e->getMessage());
            $error = 'Failed to update user status.';
        }
    }
}

// Handle Manual Wallet Balance Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_wallet') {
    require_admin('wallet.adjust');
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Session validation failed.';
    } else {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $adjType = $_POST['adj_type'] === 'debit' ? 'debit' : 'credit';
        $amount = (float)($_POST['amount'] ?? 0.0);
        $reason = trim($_POST['reason'] ?? '');

        if ($amount <= 0 || !$reason) {
            $error = 'A valid positive amount and non-empty administrative justification reason are mandatory.';
        } else {
            if ($adjType === 'credit') {
                $res = Wallet::credit(
                    $targetUserId,
                    $amount,
                    'Admin Balance Adjustment: ' . $reason,
                    'adjustment',
                    'admin_adjust',
                    (string)$adminId,
                    $adminId
                );
            } else {
                $res = Wallet::debit(
                    $targetUserId,
                    $amount,
                    'Admin Balance Deduction: ' . $reason,
                    'admin_adjust',
                    (string)$adminId
                );
            }

            if ($res['success']) {
                AuditLogger::log(
                    'wallet.manual_adjustment',
                    'user',
                    (string)$targetUserId,
                    ['type' => $adjType, 'amount' => $amount],
                    ['reason' => $reason, 'new_balance' => $res['balance_after']],
                    'admin'
                );

                Notifications::sendUser(
                    $targetUserId,
                    'wallet',
                    'Administrative Balance Update',
                    sprintf('Your wallet was %sed by %s. Reason: %s', $adjType, format_currency($amount), $reason),
                    '/user/wallet.php'
                );

                $message = sprintf('Wallet adjustment completed. Balance updated by %s %s.', $adjType === 'credit' ? '+' : '-', format_currency($amount));
            } else {
                $error = $res['error'] ?? 'Adjustment failed.';
            }
        }
    }
}

// Fetch users
$users = [];
try {
    $sql = '
        SELECT u.*, w.balance AS wallet_balance, ug.name AS group_name,
               (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS total_orders
        FROM users u
        LEFT JOIN wallets w ON w.user_id = u.id
        LEFT JOIN user_groups ug ON ug.id = u.user_group_id
        WHERE u.role = "user"
    ';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (u.name LIKE :q OR u.email LIKE :q OR u.uuid LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }

    if ($statusFilter !== '') {
        $sql .= ' AND u.status = :st';
        $params['st'] = $statusFilter;
    }

    $sql .= ' ORDER BY u.created_at DESC LIMIT 50';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[USERS FETCH ERROR] ' . $e->getMessage());
}
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-extrabold text-gray-900">User Account Administration</h2>
            <p class="text-xs text-gray-500 mt-0.5">Inspect user portfolios, monitor activity, and execute audited wallet balance adjustments.</p>
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
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by name, email, or UUID..."
                class="flex-1 px-3.5 py-2 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">

            <select name="status" class="px-3.5 py-2 text-xs border border-gray-200 rounded-xl bg-white focus:outline-hidden focus:border-[#6D28D9]">
                <option value="">All Statuses</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            </select>

            <button type="submit" class="btn-primary text-xs py-2 px-4">Search</button>
            <?php if ($search || $statusFilter): ?>
                <a href="/admin/users.php" class="btn-secondary text-xs py-2 px-3">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Users Table -->
    <div class="card-premium overflow-hidden">
        <?php if (empty($users)): ?>
            <div class="p-12 text-center text-xs text-gray-400">
                No users match the search filter.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 font-semibold uppercase tracking-wider">
                            <th class="py-3 px-6">User / Identifier</th>
                            <th class="py-3 px-6">Tier Group</th>
                            <th class="py-3 px-6 text-right">Wallet Balance</th>
                            <th class="py-3 px-6 text-center">Orders</th>
                            <th class="py-3 px-6">Status</th>
                            <th class="py-3 px-6">Registered (UTC)</th>
                            <th class="py-3 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($users as $u): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3.5 px-6">
                                    <div class="font-bold text-gray-900"><?= e($u['name']) ?></div>
                                    <div class="text-gray-500 text-[11px]"><?= e($u['email']) ?></div>
                                    <div class="font-mono text-gray-400 text-[10px]"><?= e($u['uuid']) ?></div>
                                </td>
                                <td class="py-3.5 px-6">
                                    <span class="badge-purple text-[10px]"><?= e($u['group_name'] ?? 'Retail') ?></span>
                                </td>
                                <td class="py-3.5 px-6 text-right font-mono font-bold text-gray-900">
                                    <?= format_currency($u['wallet_balance'] ?? 0.0) ?>
                                </td>
                                <td class="py-3.5 px-6 text-center font-mono text-gray-600">
                                    <?= (int)$u['total_orders'] ?>
                                </td>
                                <td class="py-3.5 px-6">
                                    <span class="capitalize text-[10px] font-semibold <?= $u['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= e($u['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-6 text-gray-400 font-mono text-[11px]">
                                    <?= date('Y-m-d H:i', strtotime($u['created_at'])) ?>
                                </td>
                                <td class="py-3.5 px-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Adjustment Trigger -->
                                        <button onclick="openAdjustModal(<?= (int)$u['id'] ?>, '<?= e(addslashes($u['email'])) ?>', '<?= format_currency($u['wallet_balance'] ?? 0.0) ?>')"
                                                class="btn-secondary text-[11px] py-1 px-2 text-[#6D28D9]">
                                            Adjust Balance
                                        </button>

                                        <!-- Suspend / Unsuspend Form -->
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to change user status?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="target_user_id" value="<?= (int)$u['id'] ?>">
                                            <input type="hidden" name="new_status" value="<?= $u['status'] === 'active' ? 'suspended' : 'active' ?>">
                                            <button type="submit" class="text-[11px] font-semibold py-1 px-2 rounded-lg <?= $u['status'] === 'active' ? 'text-red-600 hover:bg-red-50' : 'text-green-600 hover:bg-green-50' ?>">
                                                <?= $u['status'] === 'active' ? 'Suspend' : 'Unsuspend' ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Manual Wallet Adjustment -->
<div id="adjust-modal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="card-premium p-6 max-w-md w-full bg-white relative">
        <button onclick="document.getElementById('adjust-modal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        
        <h3 class="text-base font-bold text-gray-900 mb-1">Manual Wallet Adjustment</h3>
        <p class="text-xs text-gray-500 mb-4">
            Target User: <strong id="modal-user-email" class="text-gray-800"></strong><br>
            Current Balance: <span id="modal-user-bal" class="font-mono text-gray-800"></span>
        </p>

        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="adjust_wallet">
            <input type="hidden" id="modal-user-id" name="target_user_id" value="">

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Operation</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center gap-2 p-2 border border-gray-200 rounded-xl text-xs cursor-pointer">
                        <input type="radio" name="adj_type" value="credit" checked class="text-[#6D28D9]">
                        <span class="font-bold text-green-700">Credit (+) Add Funds</span>
                    </label>
                    <label class="flex items-center gap-2 p-2 border border-gray-200 rounded-xl text-xs cursor-pointer">
                        <input type="radio" name="adj_type" value="debit" class="text-[#6D28D9]">
                        <span class="font-bold text-red-700">Debit (-) Deduct</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Adjustment Amount (USD) *</label>
                <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                    class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9] font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Audit Justification Reason *</label>
                <textarea name="reason" rows="3" required placeholder="Required for compliance and financial audit log..."
                    class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]"></textarea>
            </div>

            <button type="submit" class="btn-primary w-full py-2.5 text-xs font-semibold">
                Execute Audited Adjustment
            </button>
        </form>
    </div>
</div>

<script>
function openAdjustModal(uid, email, balance) {
    document.getElementById('modal-user-id').value = uid;
    document.getElementById('modal-user-email').textContent = email;
    document.getElementById('modal-user-bal').textContent = balance;
    document.getElementById('adjust-modal').classList.remove('hidden');
}
</script>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>

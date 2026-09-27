<?php
declare(strict_types=1);
$adminTitle = 'Platform Audit Log';
require_once dirname(__DIR__) . '/includes/admin_header.php';
require_admin('audit.view');

$db = Database::getConnection();

$actionFilter = trim($_GET['action_filter'] ?? '');
$logs = [];

try {
    $sql = 'SELECT * FROM audit_logs WHERE 1=1';
    $params = [];

    if ($actionFilter !== '') {
        $sql .= ' AND action LIKE :act';
        $params['act'] = '%' . $actionFilter . '%';
    }

    $sql .= ' ORDER BY created_at DESC LIMIT 60';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[AUDIT FETCH ERROR] ' . $e->getMessage());
}
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-extrabold text-gray-900">Security & Administrative Audit Trail</h2>
            <p class="text-xs text-gray-500 mt-0.5">Immutable record of sensitive operations, wallet adjustments, and governance actions.</p>
        </div>
    </div>

    <!-- Audit Table -->
    <div class="card-premium overflow-hidden">
        <?php if (empty($logs)): ?>
            <div class="p-12 text-center text-xs text-gray-400">
                No administrative actions logged yet.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 font-semibold uppercase tracking-wider">
                            <th class="py-3 px-6">Timestamp (UTC)</th>
                            <th class="py-3 px-6">Actor</th>
                            <th class="py-3 px-6">Action</th>
                            <th class="py-3 px-6">Target</th>
                            <th class="py-3 px-6">Details / Payload</th>
                            <th class="py-3 px-6 text-right">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($logs as $l): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3.5 px-6 font-mono text-gray-400 text-[11px]">
                                    <?= date('Y-m-d H:i:s', strtotime($l['created_at'])) ?>
                                </td>
                                <td class="py-3.5 px-6">
                                    <div class="font-bold text-gray-900"><?= e($l['actor_email'] ?: 'System') ?></div>
                                    <span class="badge-purple text-[10px] uppercase"><?= e($l['actor_type']) ?></span>
                                </td>
                                <td class="py-3.5 px-6 font-mono font-bold text-[#6D28D9]">
                                    <?= e($l['action']) ?>
                                </td>
                                <td class="py-3.5 px-6 text-gray-700">
                                    <?= e($l['target_type']) ?>: <span class="font-mono"><?= e($l['target_id'] ?: '—') ?></span>
                                </td>
                                <td class="py-3.5 px-6 max-w-sm truncate text-gray-600 font-mono text-[10px]">
                                    <?= e($l['new_values'] ?: ($l['old_values'] ?: '—')) ?>
                                </td>
                                <td class="py-3.5 px-6 text-right font-mono text-gray-500">
                                    <?= e($l['ip_address']) ?>
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

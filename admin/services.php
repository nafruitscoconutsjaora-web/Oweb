<?php
declare(strict_types=1);
$adminTitle = 'Service Management';
require_once dirname(__DIR__) . '/includes/admin_header.php';
require_admin('services.manage');

$db = Database::getConnection();
$message = '';
$error = '';

// Handle Add / Toggle Service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Session validation error.';
    } else {
        $action = $_POST['action'];

        if ($action === 'create_service') {
            $name = trim($_POST['name'] ?? '');
            $code = strtolower(trim($_POST['code'] ?? ''));
            $cat = trim($_POST['category'] ?? 'general');
            $desc = trim($_POST['description'] ?? '');
            $icon = trim($_POST['icon_svg'] ?? '');
            $sort = (int)($_POST['sort_order'] ?? 0);

            if (!$name || !$code) {
                $error = 'Service name and unique code are mandatory.';
            } else {
                try {
                    $ins = $db->prepare('
                        INSERT INTO services (name, code, category, description, icon_svg, is_active, sort_order, created_at, updated_at)
                        VALUES (:name, :code, :cat, :desc, :icon, 1, :sort, NOW(), NOW())
                    ');
                    $ins->execute([
                        'name' => $name,
                        'code' => $code,
                        'cat'  => $cat,
                        'desc' => $desc,
                        'icon' => $icon,
                        'sort' => $sort
                    ]);

                    AuditLogger::log('service.create', 'service', $code, null, ['name' => $name], 'admin');
                    $message = 'Service ' . $name . ' added to catalog.';
                } catch (Throwable $e) {
                    error_log('[SERVICE ADD ERROR] ' . $e->getMessage());
                    $error = 'Failed to register service. Code might already exist.';
                }
            }
        } elseif ($action === 'toggle_service') {
            $serviceId = (int)($_POST['service_id'] ?? 0);
            $newStatus = (int)($_POST['status'] ?? 0);

            try {
                $upd = $db->prepare('UPDATE services SET is_active = :st, updated_at = NOW() WHERE id = :id');
                $upd->execute(['st' => $newStatus, 'id' => $serviceId]);
                $message = 'Service visibility updated.';
            } catch (Throwable $e) {
                error_log('[SERVICE TOGGLE ERROR] ' . $e->getMessage());
                $error = 'Failed to update service status.';
            }
        }
    }
}

// Fetch all services
$services = [];
try {
    $services = $db->query('SELECT * FROM services ORDER BY sort_order ASC, name ASC')->fetchAll();
} catch (Throwable $e) {
    error_log('[SERVICES FETCH ERROR] ' . $e->getMessage());
}
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-extrabold text-gray-900">Supported Services Directory</h2>
            <p class="text-xs text-gray-500 mt-0.5">Configure software verification targets, categories, and marketplace displays.</p>
        </div>

        <button onclick="document.getElementById('new-service-modal').classList.remove('hidden')" class="btn-primary text-xs py-2 px-4">
            + Add New Service
        </button>
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

    <div class="card-premium overflow-hidden">
        <?php if (empty($services)): ?>
            <div class="p-12 text-center text-xs text-gray-400">
                No services configured yet. Click "Add New Service" to start building your catalog.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 font-semibold uppercase tracking-wider">
                            <th class="py-3 px-6">Service</th>
                            <th class="py-3 px-6">Identifier Code</th>
                            <th class="py-3 px-6">Category</th>
                            <th class="py-3 px-6 text-center">Sort Weight</th>
                            <th class="py-3 px-6 text-center">Status</th>
                            <th class="py-3 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($services as $s): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3 px-6 font-bold text-gray-900 flex items-center gap-3">
                                    <div class="w-7 h-7 rounded-lg bg-purple-50 text-[#6D28D9] flex items-center justify-center shrink-0">
                                        <?php if (!empty($s['icon_svg'])): ?>
                                            <?= $s['icon_svg'] ?>
                                        <?php else: ?>
                                            <span class="text-xs">⚡</span>
                                        <?php endif; ?>
                                    </div>
                                    <span><?= e($s['name']) ?></span>
                                </td>
                                <td class="py-3 px-6 font-mono text-gray-700 font-bold">
                                    <?= e($s['code']) ?>
                                </td>
                                <td class="py-3 px-6 capitalize text-gray-600">
                                    <?= e($s['category'] ?? 'General') ?>
                                </td>
                                <td class="py-3 px-6 text-center font-mono text-gray-400">
                                    <?= (int)$s['sort_order'] ?>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    <span class="<?= $s['is_active'] ? 'badge-success' : 'badge-danger' ?> text-[10px]">
                                        <?= $s['is_active'] ? 'Active' : 'Disabled' ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-right">
                                    <form method="POST" class="inline-block">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_service">
                                        <input type="hidden" name="service_id" value="<?= (int)$s['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $s['is_active'] ? '0' : '1' ?>">
                                        <button type="submit" class="btn-secondary text-[11px] py-1 px-2.5">
                                            <?= $s['is_active'] ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: New Service -->
<div id="new-service-modal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="card-premium p-6 max-w-md w-full bg-white relative">
        <button onclick="document.getElementById('new-service-modal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        <h3 class="text-base font-bold text-gray-900 mb-1">Add Service Target</h3>
        <p class="text-xs text-gray-500 mb-4">Register a new verification software profile.</p>

        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_service">

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Service Name *</label>
                <input type="text" name="name" required placeholder="e.g. Google / Gmail" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">System Code *</label>
                    <input type="text" name="code" required placeholder="go" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Category</label>
                    <input type="text" name="category" value="messaging" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Short Description</label>
                <textarea name="description" rows="2" placeholder="Brief description of service for customers" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]"></textarea>
            </div>

            <button type="submit" class="btn-primary w-full py-2.5 text-xs font-semibold mt-2">
                Save Service Target
            </button>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>

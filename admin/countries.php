<?php
declare(strict_types=1);
$adminTitle = 'Country Management';
require_once dirname(__DIR__) . '/includes/admin_header.php';
require_admin('services.manage');

$db = Database::getConnection();
$message = '';
$error = '';

// Handle Add / Edit Country
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired.';
    } else {
        $action = $_POST['action'];

        if ($action === 'create_country') {
            $name = trim($_POST['name'] ?? '');
            $iso = strtoupper(trim($_POST['iso_code'] ?? ''));
            $dial = trim($_POST['dial_code'] ?? '');
            $flag = trim($_POST['flag_emoji'] ?? '🌐');
            $sort = (int)($_POST['sort_order'] ?? 0);

            if (!$name || !$iso || !$dial) {
                $error = 'Name, ISO code, and dial code are required.';
            } else {
                try {
                    $ins = $db->prepare('
                        INSERT INTO countries (name, iso_code, dial_code, flag_emoji, is_active, sort_order, created_at, updated_at)
                        VALUES (:name, :iso, :dial, :flag, 1, :sort, NOW(), NOW())
                    ');
                    $ins->execute([
                        'name' => $name,
                        'iso'  => $iso,
                        'dial' => $dial,
                        'flag' => $flag,
                        'sort' => $sort
                    ]);

                    AuditLogger::log('country.create', 'country', $iso, null, ['name' => $name, 'dial' => $dial], 'admin');
                    $message = 'Country ' . $name . ' added successfully.';
                } catch (Throwable $e) {
                    error_log('[COUNTRY ADD ERROR] ' . $e->getMessage());
                    $error = 'Failed to add country. ISO code may already exist.';
                }
            }
        } elseif ($action === 'toggle_country') {
            $countryId = (int)($_POST['country_id'] ?? 0);
            $newStatus = (int)($_POST['status'] ?? 0);

            try {
                $upd = $db->prepare('UPDATE countries SET is_active = :st, updated_at = NOW() WHERE id = :id');
                $upd->execute(['st' => $newStatus, 'id' => $countryId]);
                $message = 'Country visibility updated.';
            } catch (Throwable $e) {
                error_log('[COUNTRY TOGGLE ERROR] ' . $e->getMessage());
                $error = 'Failed to update country status.';
            }
        }
    }
}

// Fetch all countries
$countries = [];
try {
    $countries = $db->query('SELECT * FROM countries ORDER BY sort_order ASC, name ASC')->fetchAll();
} catch (Throwable $e) {
    error_log('[COUNTRIES FETCH ERROR] ' . $e->getMessage());
}
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-extrabold text-gray-900">Supported Countries Directory</h2>
            <p class="text-xs text-gray-500 mt-0.5">Manage international telecommunication regions, dial codes, and routing availability.</p>
        </div>

        <button onclick="document.getElementById('new-country-modal').classList.remove('hidden')" class="btn-primary text-xs py-2 px-4">
            + Add New Country
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
        <?php if (empty($countries)): ?>
            <div class="p-12 text-center text-xs text-gray-400">
                No countries registered yet. Click "Add New Country" to configure your first destination.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 font-semibold uppercase tracking-wider">
                            <th class="py-3 px-6">Region</th>
                            <th class="py-3 px-6">ISO 3166-1</th>
                            <th class="py-3 px-6">Dial Code</th>
                            <th class="py-3 px-6 text-center">Sort Order</th>
                            <th class="py-3 px-6 text-center">Status</th>
                            <th class="py-3 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($countries as $c): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3 px-6 font-bold text-gray-900 flex items-center gap-2">
                                    <span class="text-lg"><?= e($c['flag_emoji']) ?></span>
                                    <span><?= e($c['name']) ?></span>
                                </td>
                                <td class="py-3 px-6 font-mono font-bold text-gray-700">
                                    <?= e($c['iso_code']) ?>
                                </td>
                                <td class="py-3 px-6 font-mono text-gray-600">
                                    <?= e($c['dial_code']) ?>
                                </td>
                                <td class="py-3 px-6 text-center font-mono text-gray-400">
                                    <?= (int)$c['sort_order'] ?>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    <span class="<?= $c['is_active'] ? 'badge-success' : 'badge-danger' ?> text-[10px]">
                                        <?= $c['is_active'] ? 'Active' : 'Disabled' ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-right">
                                    <form method="POST" class="inline-block">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_country">
                                        <input type="hidden" name="country_id" value="<?= (int)$c['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $c['is_active'] ? '0' : '1' ?>">
                                        <button type="submit" class="btn-secondary text-[11px] py-1 px-2.5">
                                            <?= $c['is_active'] ? 'Disable' : 'Enable' ?>
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

<!-- Modal: New Country -->
<div id="new-country-modal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="card-premium p-6 max-w-md w-full bg-white relative">
        <button onclick="document.getElementById('new-country-modal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        <h3 class="text-base font-bold text-gray-900 mb-1">Add Country</h3>
        <p class="text-xs text-gray-500 mb-4">Define a new telecommunication territory.</p>

        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_country">

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Country Name *</label>
                <input type="text" name="name" required placeholder="e.g. United States" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">ISO Alpha-2 *</label>
                    <input type="text" name="iso_code" maxlength="2" required placeholder="US" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl uppercase font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Dial Code *</label>
                    <input type="text" name="dial_code" required placeholder="+1" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Flag Emoji</label>
                    <input type="text" name="flag_emoji" value="🇺🇸" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Sort Weight</label>
                    <input type="number" name="sort_order" value="10" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
            </div>

            <button type="submit" class="btn-primary w-full py-2.5 text-xs font-semibold mt-2">
                Save Country
            </button>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>

<?php
declare(strict_types=1);
$adminTitle = 'Provider & Carrier Adapters';
require_once dirname(__DIR__) . '/includes/admin_header.php';
require_admin('providers.manage');

$db = Database::getConnection();
$message = '';
$error = '';

// Handle Add / Edit / Sync Provider
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Session validation error.';
    } else {
        $action = $_POST['action'];

        if ($action === 'create_provider') {
            $name = trim($_POST['name'] ?? '');
            $slug = strtolower(trim($_POST['slug'] ?? ''));
            $endpoint = trim($_POST['api_endpoint'] ?? '');
            $apiKey = trim($_POST['api_key'] ?? '');
            $apiSecret = trim($_POST['api_secret'] ?? '');
            $adapterClass = trim($_POST['adapter_class'] ?? 'GenericRestAdapter');
            $priority = (int)($_POST['priority'] ?? 10);
            $timeout = (int)($_POST['timeout_sec'] ?? 15);

            if (!$name || !$slug || !$endpoint) {
                $error = 'Name, unique slug, and API endpoint are required.';
            } else {
                try {
                    // Encrypt API credentials
                    $encKey = $apiKey ? ('enc:' . encrypt_secret($apiKey)) : null;
                    $encSecret = $apiSecret ? ('enc:' . encrypt_secret($apiSecret)) : null;

                    $ins = $db->prepare('
                        INSERT INTO providers
                        (name, slug, api_endpoint, api_key_encrypted, api_secret_encrypted, adapter_class, priority, timeout_sec, retry_limit, is_active, created_at, updated_at)
                        VALUES
                        (:name, :slug, :ep, :k, :s, :ac, :pri, :to, 2, 1, NOW(), NOW())
                    ');
                    $ins->execute([
                        'name' => $name,
                        'slug' => $slug,
                        'ep'   => $endpoint,
                        'k'    => $encKey,
                        's'    => $encSecret,
                        'ac'   => $adapterClass,
                        'pri'  => $priority,
                        'to'   => $timeout
                    ]);

                    AuditLogger::log('provider.create', 'provider', $slug, null, ['name' => $name, 'adapter' => $adapterClass], 'admin');
                    $message = 'Provider ' . $name . ' added successfully.';
                } catch (Throwable $e) {
                    error_log('[PROVIDER CREATE ERROR] ' . $e->getMessage());
                    $error = 'Failed to register provider. Slug may already exist.';
                }
            }
        } elseif ($action === 'check_balance') {
            $providerId = (int)($_POST['provider_id'] ?? 0);
            $adapter = ProviderFactory::createById($providerId);

            if (!$adapter) {
                $error = 'Could not initialize adapter for provider ID ' . $providerId;
            } else {
                $balRes = $adapter->getBalance();
                if ($balRes['success']) {
                    $bal = (float)$balRes['balance'];
                    $upd = $db->prepare('UPDATE providers SET balance = :bal, last_health_check = NOW(), health_status = "healthy", updated_at = NOW() WHERE id = :id');
                    $upd->execute(['bal' => number_format($bal, 4, '.', ''), 'id' => $providerId]);
                    $message = 'Balance synced from upstream carrier API: ' . format_currency($bal);
                } else {
                    $error = 'Balance query failed: ' . ($balRes['error'] ?? 'Unknown carrier error');
                }
            }
        } elseif ($action === 'toggle_provider') {
            $providerId = (int)($_POST['provider_id'] ?? 0);
            $status = (int)($_POST['status'] ?? 0);

            try {
                $upd = $db->prepare('UPDATE providers SET is_active = :st, updated_at = NOW() WHERE id = :id');
                $upd->execute(['st' => $status, 'id' => $providerId]);
                $message = 'Provider status updated.';
            } catch (Throwable $e) {
                error_log('[PROVIDER TOGGLE ERROR] ' . $e->getMessage());
                $error = 'Failed to update provider status.';
            }
        }
    }
}

// Fetch all providers
$providers = [];
try {
    $providers = $db->query('SELECT * FROM providers ORDER BY priority ASC, name ASC')->fetchAll();
} catch (Throwable $e) {
    error_log('[PROVIDERS FETCH ERROR] ' . $e->getMessage());
}
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-extrabold text-gray-900">Carrier Provider Adapters & Routing</h2>
            <p class="text-xs text-gray-500 mt-0.5">Manage upstream API endpoints, encrypted credentials, and automated fallback priority.</p>
        </div>

        <button onclick="document.getElementById('new-provider-modal').classList.remove('hidden')" class="btn-primary text-xs py-2 px-4">
            + Connect New Provider
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
        <?php if (empty($providers)): ?>
            <div class="p-12 text-center text-xs text-gray-400">
                No upstream providers configured yet. Connect a provider adapter to start receiving numbers.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 font-semibold uppercase tracking-wider">
                            <th class="py-3 px-6">Provider Name</th>
                            <th class="py-3 px-6">Adapter Class</th>
                            <th class="py-3 px-6">Endpoint</th>
                            <th class="py-3 px-6 text-center">Priority</th>
                            <th class="py-3 px-6 text-right">Carrier Balance</th>
                            <th class="py-3 px-6 text-center">Health</th>
                            <th class="py-3 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($providers as $p): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3 px-6">
                                    <div class="font-bold text-gray-900"><?= e($p['name']) ?></div>
                                    <div class="text-[10px] text-gray-400 font-mono"><?= e($p['slug']) ?></div>
                                </td>
                                <td class="py-3 px-6 font-mono font-semibold text-gray-700">
                                    <?= e($p['adapter_class']) ?>
                                </td>
                                <td class="py-3 px-6 font-mono text-gray-500 text-[11px] max-w-xs truncate">
                                    <?= e($p['api_endpoint']) ?>
                                </td>
                                <td class="py-3 px-6 text-center font-mono font-bold text-gray-900">
                                    <?= (int)$p['priority'] ?>
                                </td>
                                <td class="py-3 px-6 text-right font-mono font-bold text-gray-900">
                                    <?= format_currency($p['balance']) ?>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    <span class="capitalize text-[10px] font-bold <?= $p['health_status'] === 'healthy' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= e($p['health_status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Check Balance Button -->
                                        <form method="POST" class="inline-block">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="check_balance">
                                            <input type="hidden" name="provider_id" value="<?= (int)$p['id'] ?>">
                                            <button type="submit" class="btn-secondary text-[11px] py-1 px-2 text-[#6D28D9]">
                                                Sync Balance
                                            </button>
                                        </form>

                                        <!-- Toggle Status -->
                                        <form method="POST" class="inline-block">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_provider">
                                            <input type="hidden" name="provider_id" value="<?= (int)$p['id'] ?>">
                                            <input type="hidden" name="status" value="<?= $p['is_active'] ? '0' : '1' ?>">
                                            <button type="submit" class="text-[11px] font-semibold py-1 px-2 rounded-lg <?= $p['is_active'] ? 'text-red-600 hover:bg-red-50' : 'text-green-600 hover:bg-green-50' ?>">
                                                <?= $p['is_active'] ? 'Disable' : 'Enable' ?>
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

<!-- Modal: New Provider -->
<div id="new-provider-modal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="card-premium p-6 max-w-lg w-full bg-white relative">
        <button onclick="document.getElementById('new-provider-modal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        <h3 class="text-base font-bold text-gray-900 mb-1">Connect Carrier Provider</h3>
        <p class="text-xs text-gray-500 mb-4">API keys are encrypted using AES-256-GCM before storage.</p>

        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_provider">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Provider Name *</label>
                    <input type="text" name="name" required placeholder="e.g. SMS-Activate Hub" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Unique Slug *</label>
                    <input type="text" name="slug" required placeholder="sms_activate" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Adapter Protocol *</label>
                <select name="adapter_class" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl bg-white focus:outline-hidden focus:border-[#6D28D9]">
                    <option value="SmsActivateAdapter">SmsActivateAdapter (Standard SMS-Activate Protocol)</option>
                    <option value="FiveSimAdapter">FiveSimAdapter (5SIM Protocol)</option>
                    <option value="GenericRestAdapter">GenericRestAdapter (Standard JSON REST Protocol)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">API Base Endpoint *</label>
                <input type="url" name="api_endpoint" required placeholder="https://api.sms-activate.org/stubs/handler_api.php" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">API Key / Token</label>
                    <input type="password" name="api_key" placeholder="••••••••••••••••" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">API Secret (If required)</label>
                    <input type="password" name="api_secret" placeholder="••••••••••••••••" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Priority Weight</label>
                    <input type="number" name="priority" value="10" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                    <span class="text-[10px] text-gray-400">Lower integer = higher routing priority</span>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Timeout (Sec)</label>
                    <input type="number" name="timeout_sec" value="15" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
            </div>

            <button type="submit" class="btn-primary w-full py-2.5 text-xs font-semibold mt-2">
                Save & Encrypt Provider Credentials
            </button>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>

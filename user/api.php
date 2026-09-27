<?php
declare(strict_types=1);
$pageTitle = 'Developer & B2B API';
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$user = current_user();
$userId = (int)$user['id'];
$db = Database::getConnection();

$newRawKey = '';
$successMsg = '';
$errorMsg = '';

// Get or create api_customers record
$custStmt = $db->prepare('SELECT * FROM api_customers WHERE user_id = :uid LIMIT 1');
$custStmt->execute(['uid' => $userId]);
$apiCustomer = $custStmt->fetch();

if (!$apiCustomer) {
    $insCust = $db->prepare('
        INSERT INTO api_customers (user_id, company_name, rate_limit_per_min, daily_request_limit, is_active, created_at, updated_at)
        VALUES (:uid, :cname, 60, 10000, 1, NOW(), NOW())
    ');
    $insCust->execute([
        'uid'   => $userId,
        'cname' => $user['name'] . ' API Workspace'
    ]);
    $custStmt->execute(['uid' => $userId]);
    $apiCustomer = $custStmt->fetch();
}

$customerId = (int)$apiCustomer['id'];

// Handle API Key Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_key') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Session validation error.';
    } else {
        $label = trim($_POST['label'] ?? 'Standard API Key');
        $rawSecret = 'vfx_live_' . bin2hex(random_bytes(24));
        $prefix = substr($rawSecret, 0, 12);
        $keyHash = hash('sha256', $rawSecret);

        try {
            $insKey = $db->prepare('
                INSERT INTO api_keys (customer_id, key_prefix, key_hash, label, is_active, created_at)
                VALUES (:cid, :prefix, :hash, :label, 1, NOW())
            ');
            $insKey->execute([
                'cid'    => $customerId,
                'prefix' => $prefix,
                'hash'   => $keyHash,
                'label'  => $label
            ]);

            $newRawKey = $rawSecret;
            $successMsg = 'New API Key generated successfully. Please copy it immediately, as it will never be displayed again.';
        } catch (Throwable $e) {
            error_log('[API KEY GENERATION ERROR] ' . $e->getMessage());
            $errorMsg = 'Unable to generate API key.';
        }
    }
}

// Fetch active keys
$keys = [];
$usageLogs = [];
try {
    $kStmt = $db->prepare('SELECT * FROM api_keys WHERE customer_id = :cid ORDER BY created_at DESC');
    $kStmt->execute(['cid' => $customerId]);
    $keys = $kStmt->fetchAll();

    $logStmt = $db->prepare('SELECT * FROM api_usage_logs WHERE customer_id = :cid ORDER BY created_at DESC LIMIT 10');
    $logStmt->execute(['cid' => $customerId]);
    $usageLogs = $logStmt->fetchAll();
} catch (Throwable $e) {
    error_log('[API FETCH ERROR] ' . $e->getMessage());
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Developer & B2B API</h1>
            <p class="text-xs text-gray-500 mt-1">Integrate virtual number allocation directly into your automated test pipelines.</p>
        </div>

        <button onclick="document.getElementById('new-key-modal').classList.remove('hidden')" class="btn-primary text-xs py-2.5 px-4 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Generate New API Key
        </button>
    </div>

    <?php if ($newRawKey): ?>
        <div class="card-premium p-6 mb-8 bg-purple-50/70 border-purple-300">
            <div class="flex items-center gap-2 mb-2 text-[#6D28D9] font-bold text-sm">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
                Copy Your Secret API Key Now
            </div>
            <p class="text-xs text-purple-800 mb-3">For your security, this key cannot be recovered after leaving this screen.</p>
            <div class="flex items-center gap-3">
                <input type="text" readonly value="<?= e($newRawKey) ?>" class="w-full bg-white px-3.5 py-2 font-mono text-xs font-bold border border-purple-300 rounded-xl text-gray-900">
                <button data-copy="<?= e($newRawKey) ?>" class="btn-primary text-xs py-2 px-4 shrink-0">
                    Copy Key
                </button>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($successMsg && !$newRawKey): ?>
        <div class="card-premium p-4 mb-6 bg-green-50 border-green-200 text-green-800 text-xs font-semibold">
            <?= e($successMsg) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="card-premium p-4 mb-6 bg-red-50 border-red-200 text-red-700 text-xs font-semibold">
            <?= e($errorMsg) ?>
        </div>
    <?php endif; ?>

    <!-- Quota & Limits Bar -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="card-premium p-5">
            <div class="text-xs text-gray-400 font-semibold uppercase">Rate Limit</div>
            <div class="text-xl font-extrabold text-gray-900 font-mono mt-1"><?= (int)$apiCustomer['rate_limit_per_min'] ?> req / min</div>
        </div>
        <div class="card-premium p-5">
            <div class="text-xs text-gray-400 font-semibold uppercase">Daily Quota</div>
            <div class="text-xl font-extrabold text-gray-900 font-mono mt-1"><?= number_format((int)$apiCustomer['daily_request_limit']) ?> req / day</div>
        </div>
        <div class="card-premium p-5">
            <div class="text-xs text-gray-400 font-semibold uppercase">Active Keys</div>
            <div class="text-xl font-extrabold text-[#6D28D9] font-mono mt-1"><?= count($keys) ?></div>
        </div>
    </div>

    <!-- Active API Keys Table -->
    <div class="card-premium overflow-hidden mb-10">
        <div class="p-5 border-b border-gray-100">
            <h3 class="font-bold text-gray-900 text-sm">Active API Keys</h3>
        </div>

        <?php if (empty($keys)): ?>
            <div class="p-8 text-center text-xs text-gray-400">
                No active API keys found. Generate a key to begin.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 font-semibold uppercase">
                            <th class="py-3 px-6">Label</th>
                            <th class="py-3 px-6">Key Prefix</th>
                            <th class="py-3 px-6">Last Used</th>
                            <th class="py-3 px-6">Created</th>
                            <th class="py-3 px-6 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($keys as $k): ?>
                            <tr>
                                <td class="py-3 px-6 font-bold text-gray-900"><?= e($k['label']) ?></td>
                                <td class="py-3 px-6 font-mono text-gray-600"><?= e($k['key_prefix']) ?>••••••••</td>
                                <td class="py-3 px-6 text-gray-400"><?= $k['last_used_at'] ? date('M j, Y H:i', strtotime($k['last_used_at'])) : 'Never' ?></td>
                                <td class="py-3 px-6 text-gray-400"><?= date('M j, Y', strtotime($k['created_at'])) ?></td>
                                <td class="py-3 px-6 text-right">
                                    <span class="badge-success text-[10px]"><?= $k['is_active'] ? 'Active' : 'Disabled' ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Interactive Conceptual Documentation -->
    <div class="card-premium p-6">
        <h3 class="font-bold text-gray-900 text-base mb-4">REST API Reference</h3>
        <p class="text-xs text-gray-500 mb-6">
            Authenticate all requests by supplying your API key in the <code class="bg-gray-100 px-1 py-0.5 rounded font-mono text-gray-800">Authorization: Bearer YOUR_API_KEY</code> HTTP header.
        </p>

        <div class="space-y-4">
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                <div class="flex items-center gap-2 mb-1">
                    <span class="bg-blue-600 text-white font-bold font-mono text-[10px] px-2 py-0.5 rounded">GET</span>
                    <span class="font-mono text-xs font-bold text-gray-900">/api/v1/balance</span>
                </div>
                <p class="text-xs text-gray-500">Query your current available wallet balance.</p>
            </div>

            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                <div class="flex items-center gap-2 mb-1">
                    <span class="bg-blue-600 text-white font-bold font-mono text-[10px] px-2 py-0.5 rounded">GET</span>
                    <span class="font-mono text-xs font-bold text-gray-900">/api/v1/countries</span>
                </div>
                <p class="text-xs text-gray-500">List all active supported countries with ISO codes.</p>
            </div>

            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                <div class="flex items-center gap-2 mb-1">
                    <span class="bg-green-600 text-white font-bold font-mono text-[10px] px-2 py-0.5 rounded">POST</span>
                    <span class="font-mono text-xs font-bold text-gray-900">/api/v1/activations</span>
                </div>
                <p class="text-xs text-gray-500">Reserve a new virtual line for a specific service and country.</p>
            </div>

            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                <div class="flex items-center gap-2 mb-1">
                    <span class="bg-blue-600 text-white font-bold font-mono text-[10px] px-2 py-0.5 rounded">GET</span>
                    <span class="font-mono text-xs font-bold text-gray-900">/api/v1/activations/{id}</span>
                </div>
                <p class="text-xs text-gray-500">Inspect activation status and retrieve incoming verification SMS codes.</p>
            </div>
        </div>
    </div>
</div>

<!-- Key Modal -->
<div id="new-key-modal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="card-premium p-6 max-w-md w-full bg-white relative">
        <button onclick="document.getElementById('new-key-modal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        <h3 class="text-base font-bold text-gray-900 mb-1">Generate API Key</h3>
        <p class="text-xs text-gray-500 mb-4">Provide a label to track usage in your systems.</p>

        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="generate_key">
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Key Label</label>
                <input type="text" name="label" required placeholder="e.g. CI/CD Staging Cluster" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
            </div>
            <button type="submit" class="btn-primary w-full py-2.5 text-xs font-semibold">
                Generate Key
            </button>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

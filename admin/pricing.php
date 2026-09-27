<?php
declare(strict_types=1);
$adminTitle = 'Pricing Engine & User Tiers';
require_once dirname(__DIR__) . '/includes/admin_header.php';
require_admin('pricing.manage');

$db = Database::getConnection();
$message = '';
$error = '';

// Handle Pricing Rule Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired.';
    } else {
        $action = $_POST['action'];

        if ($action === 'create_rule') {
            $name = trim($_POST['name'] ?? '');
            $ruleType = $_POST['rule_type'] ?? 'global';
            $marginType = $_POST['margin_type'] ?? 'percentage';
            $marginValue = (float)($_POST['margin_value'] ?? 25.0);
            $countryId = !empty($_POST['country_id']) ? (int)$_POST['country_id'] : null;
            $serviceId = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
            $priority = (int)($_POST['priority'] ?? 10);

            if (!$name) {
                $error = 'Rule name is required.';
            } else {
                try {
                    $ins = $db->prepare('
                        INSERT INTO pricing_rules
                        (name, rule_type, margin_type, margin_value, country_id, service_id, priority, is_active, created_at, updated_at)
                        VALUES
                        (:name, :rt, :mt, :mv, :cid, :sid, :pri, 1, NOW(), NOW())
                    ');
                    $ins->execute([
                        'name' => $name,
                        'rt'   => $ruleType,
                        'mt'   => $marginType,
                        'mv'   => number_format($marginValue, 4, '.', ''),
                        'cid'  => $countryId,
                        'sid'  => $serviceId,
                        'pri'  => $priority
                    ]);

                    AuditLogger::log('pricing.create_rule', 'pricing_rule', $name, null, ['margin_type' => $marginType, 'margin_val' => $marginValue], 'admin');
                    $message = 'Pricing rule registered successfully.';
                } catch (Throwable $e) {
                    error_log('[PRICING RULE ERROR] ' . $e->getMessage());
                    $error = 'Failed to create pricing rule.';
                }
            }
        } elseif ($action === 'update_tier') {
            $groupId = (int)($_POST['group_id'] ?? 0);
            $discount = (float)($_POST['discount_percentage'] ?? 0.0);

            try {
                $upd = $db->prepare('UPDATE user_groups SET discount_percentage = :disc, updated_at = NOW() WHERE id = :id');
                $upd->execute(['disc' => number_format($discount, 2, '.', ''), 'id' => $groupId]);
                $message = 'User tier discount updated.';
            } catch (Throwable $e) {
                error_log('[TIER UPDATE ERROR] ' . $e->getMessage());
                $error = 'Failed to update user tier.';
            }
        }
    }
}

// Fetch rules, user groups, countries, services
$rules = [];
$groups = [];
$countries = [];
$services = [];

try {
    $rStmt = $db->query('
        SELECT pr.*, c.name AS country_name, s.name AS service_name
        FROM pricing_rules pr
        LEFT JOIN countries c ON c.id = pr.country_id
        LEFT JOIN services s ON s.id = pr.service_id
        ORDER BY pr.priority DESC, pr.id DESC
    ');
    $rules = $rStmt->fetchAll();

    $groups = $db->query('SELECT * FROM user_groups ORDER BY priority ASC')->fetchAll();
    $countries = $db->query('SELECT id, name FROM countries WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
    $services = $db->query('SELECT id, name FROM services WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
} catch (Throwable $e) {
    error_log('[PRICING FETCH ERROR] ' . $e->getMessage());
}
?>

<div class="space-y-8">
    <div>
        <h2 class="text-xl font-extrabold text-gray-900">Dynamic Pricing Engine & User Tiers</h2>
        <p class="text-xs text-gray-500 mt-0.5">Define margin formulas (wholesale cost + fixed / percentage markup) and membership tier benefits.</p>
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

    <!-- User Tiers Bar -->
    <div class="card-premium p-6">
        <h3 class="font-bold text-gray-900 text-sm mb-4">Membership Tiers & Volume Discounts</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($groups as $g): ?>
                <div class="p-4 rounded-xl border border-gray-200 bg-gray-50">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-gray-900 text-xs"><?= e($g['name']) ?></span>
                        <span class="badge-purple text-[10px]"><?= e($g['slug']) ?></span>
                    </div>

                    <form method="POST" class="flex items-center gap-2 mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_tier">
                        <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">

                        <div class="relative flex-1">
                            <input type="number" step="0.5" min="0" max="90" name="discount_percentage" value="<?= (float)$g['discount_percentage'] ?>"
                                class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg font-mono focus:outline-hidden focus:border-[#6D28D9]">
                            <span class="absolute right-2 top-1.5 text-xs text-gray-400">%</span>
                        </div>

                        <button type="submit" class="btn-primary text-[11px] py-1.5 px-3">
                            Save
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Pricing Rules Table -->
    <div class="card-premium overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-bold text-gray-900 text-sm">Active Markup & Pricing Rules</h3>
            <button onclick="document.getElementById('new-rule-modal').classList.remove('hidden')" class="btn-primary text-xs py-1.5 px-3">
                + Add Rule
            </button>
        </div>

        <?php if (empty($rules)): ?>
            <div class="p-12 text-center text-xs text-gray-400">
                No custom pricing rules configured yet. The platform currently applies default global 25% markup.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 font-semibold uppercase tracking-wider">
                            <th class="py-3 px-6">Rule Name</th>
                            <th class="py-3 px-6">Scope</th>
                            <th class="py-3 px-6">Target Service / Country</th>
                            <th class="py-3 px-6">Margin Formula</th>
                            <th class="py-3 px-6 text-center">Priority</th>
                            <th class="py-3 px-6 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($rules as $r): ?>
                            <tr>
                                <td class="py-3.5 px-6 font-bold text-gray-900"><?= e($r['name']) ?></td>
                                <td class="py-3.5 px-6 capitalize font-mono text-gray-600"><?= e($r['rule_type']) ?></td>
                                <td class="py-3.5 px-6 text-gray-700">
                                    <?= e($r['service_name'] ?: 'All Services') ?> / <?= e($r['country_name'] ?: 'All Regions') ?>
                                </td>
                                <td class="py-3.5 px-6 font-mono font-bold text-[#6D28D9]">
                                    <?= $r['margin_type'] === 'fixed' ? ('+$' . number_format((float)$r['margin_value'], 2)) : ('+' . number_format((float)$r['margin_value'], 1) . '%') ?>
                                </td>
                                <td class="py-3.5 px-6 text-center font-mono text-gray-400"><?= (int)$r['priority'] ?></td>
                                <td class="py-3.5 px-6 text-right">
                                    <span class="badge-success text-[10px]">Active</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: New Rule -->
<div id="new-rule-modal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="card-premium p-6 max-w-md w-full bg-white relative">
        <button onclick="document.getElementById('new-rule-modal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        <h3 class="text-base font-bold text-gray-900 mb-1">Add Pricing Rule</h3>
        <p class="text-xs text-gray-500 mb-4">Define how the system markups wholesale carrier line costs.</p>

        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_rule">

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Rule Name *</label>
                <input type="text" name="name" required placeholder="e.g. Premium North America Markup" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Margin Type</label>
                    <select name="margin_type" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl bg-white focus:outline-hidden focus:border-[#6D28D9]">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount ($)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Margin Value *</label>
                    <input type="number" step="0.01" name="margin_value" value="25.00" required class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Specific Country</label>
                    <select name="country_id" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl bg-white focus:outline-hidden focus:border-[#6D28D9]">
                        <option value="">All Countries</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Specific Service</label>
                    <select name="service_id" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl bg-white focus:outline-hidden focus:border-[#6D28D9]">
                        <option value="">All Services</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Priority Weight</label>
                <input type="number" name="priority" value="20" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                <span class="text-[10px] text-gray-400">Higher integer rules take precedence</span>
            </div>

            <button type="submit" class="btn-primary w-full py-2.5 text-xs font-semibold mt-2">
                Save Pricing Rule
            </button>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>

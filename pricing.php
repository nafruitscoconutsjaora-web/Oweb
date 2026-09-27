<?php
declare(strict_types=1);
$pageTitle = 'Pricing';
require_once __DIR__ . '/includes/header.php';

$db = Database::getConnection();
$user = current_user();
$userId = $user ? (int)$user['id'] : null;

$countries = [];
$services = [];
$samplePricing = [];

try {
    $countries = $db->query('SELECT * FROM countries WHERE is_active = 1 ORDER BY sort_order ASC, name ASC')->fetchAll();
    $services = $db->query('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 20')->fetchAll();

    if (!empty($countries) && !empty($services)) {
        $firstCountry = $countries[0];
        foreach ($services as $svc) {
            $calc = Pricing::calculate((int)$firstCountry['id'], (int)$svc['id'], $userId);
            if ($calc['eligible']) {
                $samplePricing[] = [
                    'service_name' => $svc['name'],
                    'country_name' => $firstCountry['name'],
                    'flag'         => $firstCountry['flag_emoji'],
                    'price'        => $calc['price']
                ];
            }
        }
    }
} catch (Throwable $e) {
    error_log('[PRICING PAGE ERROR] ' . $e->getMessage());
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="text-center max-w-3xl mx-auto mb-12">
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Pricing</h1>
        <p class="mt-2 text-sm text-gray-600">
            Pay only for successful verifications. Automatic refund if no SMS is received.
        </p>
    </div>

    <!-- Pricing Table -->
    <div class="card-premium overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="font-bold text-gray-900 text-lg">Current Service Pricing</h3>
                <p class="text-xs text-gray-500">Prices are per successful SMS verification code received.</p>
            </div>
            <a href="/user/activate.php" class="btn-primary text-xs py-2 px-4">
                Get a Number &rarr;
            </a>
        </div>

        <?php if (empty($samplePricing)): ?>
            <div class="p-12 text-center">
                <h4 class="text-base font-bold text-gray-900 mb-1">No pricing information is currently available</h4>
                <p class="text-xs text-gray-500 max-w-sm mx-auto">
                    Please check back shortly or select a service directly from the dashboard.
                </p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            <th class="py-3.5 px-6">Service</th>
                            <th class="py-3.5 px-6">Country</th>
                            <th class="py-3.5 px-6">Refund Guarantee</th>
                            <th class="py-3.5 px-6 text-right">Price</th>
                            <th class="py-3.5 px-6 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($samplePricing as $row): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-4 px-6 font-bold text-gray-900">
                                    <?= e($row['service_name']) ?>
                                </td>
                                <td class="py-4 px-6 text-gray-700">
                                    <span class="mr-1.5"><?= e($row['flag']) ?></span>
                                    <?= e($row['country_name']) ?>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="badge-success text-xs">Included</span>
                                </td>
                                <td class="py-4 px-6 text-right font-mono font-bold text-gray-900">
                                    <?= format_currency($row['price']) ?>
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <a href="/user/activate.php" class="btn-secondary text-xs py-1 px-3">
                                        Select
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

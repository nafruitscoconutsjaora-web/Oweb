<?php
declare(strict_types=1);
$pageTitle = 'Purchase Activation';
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$user = current_user();
$userId = (int)$user['id'];
$db = Database::getConnection();

$errorMessage = '';
$selectedCountryId = (int)($_GET['country'] ?? 0);
$selectedServiceCode = trim($_GET['service'] ?? '');

$countries = [];
$services = [];

try {
    $countries = $db->query('SELECT * FROM countries WHERE is_active = 1 ORDER BY sort_order ASC, name ASC')->fetchAll();
    $services = $db->query('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC, name ASC')->fetchAll();

    if ($selectedCountryId === 0 && !empty($countries)) {
        $selectedCountryId = (int)$countries[0]['id'];
    }
} catch (Throwable $e) {
    error_log('[ACTIVATE PAGE ERROR] ' . $e->getMessage());
}

// Handle Purchase Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Session validation expired. Please refresh the page.';
    } else {
        $countryId = (int)($_POST['country_id'] ?? 0);
        $serviceId = (int)($_POST['service_id'] ?? 0);

        if (!$countryId || !$serviceId) {
            $errorMessage = 'Please choose both a valid destination country and verification service.';
        } else {
            // 1. Calculate authoritative server-side price
            $quote = Pricing::calculate($countryId, $serviceId, $userId);

            if (!$quote['eligible']) {
                $errorMessage = $quote['error'] ?? 'Service currently unavailable in selected country.';
            } else {
                $price = (float)$quote['price'];
                $providerCost = (float)$quote['provider_cost'];
                $margin = (float)$quote['margin'];
                $providerId = (int)$quote['provider_id'];

                // 2. Check wallet balance
                $userBal = (float)($user['wallet_balance'] ?? 0.0);
                if ($userBal < $price) {
                    $errorMessage = sprintf('Insufficient balance (%s). Price is %s. Please add funds to proceed.', format_currency($userBal), format_currency($price));
                } else {
                    // 3. Instantiate provider adapter
                    $adapter = ProviderFactory::createById($providerId);
                    if (!$adapter) {
                        $errorMessage = 'Carrier route temporarily offline. Please try another country or retry shortly.';
                    } else {
                        // 4. Request carrier allocation from provider API
                        $providerRes = $adapter->requestActivation($quote['provider_country_code'], $quote['provider_service_code']);

                        if (!$providerRes['success']) {
                            // Do not charge customer! Rule: If provider fails, do not debit.
                            $errorMessage = 'Provider allocation failed: ' . ($providerRes['error'] ?? 'No numbers available at this moment. You were not charged.');
                        } else {
                            // 5. Success from real provider: Atomically process wallet debit & order creation
                            $db->beginTransaction();
                            try {
                                $orderRef = generate_ref('ORD');

                                // Atomic debit
                                $debit = Wallet::debit(
                                    $userId,
                                    $price,
                                    'Activation purchase: Order #' . $orderRef,
                                    'order',
                                    $orderRef
                                );

                                if (!$debit['success']) {
                                    $db->rollBack();
                                    $errorMessage = $debit['error'] ?? 'Failed to debit wallet balance.';
                                } else {
                                    // Insert Order
                                    $insOrder = $db->prepare('
                                        INSERT INTO orders
                                        (order_number, user_id, service_id, country_id, provider_id, price, provider_cost, margin, status, created_at, updated_at)
                                        VALUES
                                        (:ord, :uid, :sid, :cid, :pid, :price, :cost, :margin, "active", NOW(), NOW())
                                    ');
                                    $insOrder->execute([
                                        'ord'    => $orderRef,
                                        'uid'    => $userId,
                                        'sid'    => $serviceId,
                                        'cid'    => $countryId,
                                        'pid'    => $providerId,
                                        'price'  => number_format($price, 4, '.', ''),
                                        'cost'   => number_format($providerCost, 4, '.', ''),
                                        'margin' => number_format($margin, 4, '.', '')
                                    ]);
                                    $newOrderId = (int)$db->lastInsertId();

                                    // Insert Activation
                                    $insAct = $db->prepare('
                                        INSERT INTO activations
                                        (order_id, user_id, provider_id, provider_activation_id, phone_number, full_phone_number, status, expires_at, created_at, updated_at)
                                        VALUES
                                        (:oid, :uid, :pid, :paid, :pnum, :fpnum, "waiting_sms", DATE_ADD(NOW(), INTERVAL :timeout MINUTE), NOW(), NOW())
                                    ');
                                    $insAct->execute([
                                        'oid'     => $newOrderId,
                                        'uid'     => $userId,
                                        'pid'     => $providerId,
                                        'paid'    => $providerRes['provider_activation_id'],
                                        'pnum'    => $providerRes['phone_number'],
                                        'fpnum'   => $providerRes['full_phone_number'],
                                        'timeout' => 20
                                    ]);
                                    $newActId = (int)$db->lastInsertId();

                                    $db->commit();

                                    Notifications::sendUser(
                                        $userId,
                                        'activation',
                                        'Number Assigned',
                                        'Virtual line assigned: ' . $providerRes['full_phone_number'] . '. Awaiting incoming SMS code.',
                                        '/user/active.php?id=' . $newActId
                                    );

                                    header('Location: /user/active.php?id=' . $newActId);
                                    exit;
                                }
                            } catch (Throwable $e) {
                                if ($db->inTransaction()) {
                                    $db->rollBack();
                                }
                                error_log('[ORDER TRANSACTION ERROR] ' . $e->getMessage());
                                $errorMessage = 'Transaction processing failed. Your wallet balance was not deducted.';
                            }
                        }
                    }
                }
            }
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="max-w-3xl mb-8">
        <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Get a Number</h1>
        <p class="text-sm text-gray-500 mt-1">
            Select a country and service to receive an SMS verification code.
        </p>
    </div>

    <?php if ($errorMessage): ?>
        <div class="card-premium p-4 mb-6 bg-red-50 border-red-200 text-red-700 text-xs font-semibold flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span><?= e($errorMessage) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Left: Country Selection -->
        <div class="lg:col-span-4">
            <div class="card-premium p-5">
                <h3 class="font-bold text-gray-900 text-sm mb-3">1. Select Country</h3>
                
                <?php if (empty($countries)): ?>
                    <p class="text-xs text-gray-400">No countries are currently available.</p>
                <?php else: ?>
                    <div class="space-y-1.5 max-h-[420px] overflow-y-auto pr-1">
                        <?php foreach ($countries as $c): ?>
                            <a href="?country=<?= (int)$c['id'] ?>&service=<?= urlencode($selectedServiceCode) ?>"
                               class="flex items-center justify-between p-2.5 rounded-xl text-xs transition-colors <?= $selectedCountryId === (int)$c['id'] ? 'bg-[#F3E8FF] text-[#6D28D9] font-bold border border-purple-200' : 'text-gray-700 hover:bg-gray-50' ?>">
                                <div class="flex items-center gap-2">
                                    <span class="text-base"><?= e($c['flag_emoji']) ?></span>
                                    <span><?= e($c['name']) ?></span>
                                </div>
                                <span class="text-[11px] font-mono text-gray-400"><?= e($c['dial_code']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Service Selection -->
        <div class="lg:col-span-8">
            <div class="card-premium p-6">
                <h3 class="font-bold text-gray-900 text-sm mb-4">2. Select Service</h3>

                <?php if (empty($services)): ?>
                    <div class="py-12 text-center text-xs text-gray-400">
                        No services are currently available.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php foreach ($services as $svc): ?>
                            <?php 
                                $pricing = Pricing::calculate($selectedCountryId, (int)$svc['id'], $userId);
                                $isEligible = $pricing['eligible'];
                            ?>
                            <div class="p-4 rounded-2xl border transition-all <?= $isEligible ? 'border-gray-200 hover:border-purple-300 bg-white' : 'border-gray-100 bg-gray-50/60 opacity-60' ?>">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-9 h-9 rounded-xl bg-purple-50 text-[#6D28D9] flex items-center justify-center shrink-0">
                                            <?php if (!empty($svc['icon_svg'])): ?>
                                                <?= $svc['icon_svg'] ?>
                                            <?php else: ?>
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-gray-900 text-sm"><?= e($svc['name']) ?></div>
                                            <div class="text-[11px] text-gray-400 capitalize"><?= e($svc['category'] ?? 'General') ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-right">
                                        <?php if ($isEligible): ?>
                                            <div class="font-mono font-bold text-gray-900 text-sm">
                                                <?= format_currency($pricing['price']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-[11px] text-gray-400 italic">Unavailable</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                                    <span class="text-[11px] text-gray-500">20-min window</span>
                                    
                                    <?php if ($isEligible): ?>
                                        <form method="POST">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="country_id" value="<?= $selectedCountryId ?>">
                                            <input type="hidden" name="service_id" value="<?= (int)$svc['id'] ?>">
                                            <button type="submit" class="btn-primary text-xs py-1.5 px-3">
                                                Get Number
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button disabled class="btn-secondary text-xs py-1.5 px-3 opacity-50 cursor-not-allowed">
                                            Unavailable
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

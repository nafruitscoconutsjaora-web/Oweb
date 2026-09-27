<?php
declare(strict_types=1);
$pageTitle = 'Refund Policy';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="mb-10">
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Refund & Cancellation Policy</h1>
        <p class="mt-2 text-sm text-gray-500">Effective Date: October 1, 2026</p>
    </div>

    <div class="card-premium p-8 prose max-w-none text-sm text-gray-700 space-y-6 leading-relaxed">
        <section>
            <h2 class="text-base font-bold text-gray-900">1. Core Refund Guarantee ("Zero SMS = Zero Charge")</h2>
            <p>
                Our billing engine is architected around absolute fairness. If an allocated virtual mobile number fails to receive a legitimate SMS verification code within the designated timeout window (standard 20 minutes), the full transaction fee is refunded immediately and automatically back to your platform wallet balance.
            </p>
        </section>

        <section>
            <h2 class="text-base font-bold text-gray-900">2. User-Initiated Cancellations</h2>
            <p>
                You may cancel any pending number reservation directly from the Live Activation dashboard at any time prior to an incoming SMS being received. Upon cancellation, the escrowed funds are instantly released back to your wallet.
            </p>
        </section>

        <section>
            <h2 class="text-base font-bold text-gray-900">3. Non-Refundable Scenarios</h2>
            <p>
                Once an SMS message has been successfully received and delivered to your account dashboard, the telecommunication allocation is deemed fulfilled and cannot be cancelled, returned, or refunded.
            </p>
        </section>

        <section>
            <h2 class="text-base font-bold text-gray-900">4. Wallet Balance Withdrawals</h2>
            <p>
                Users seeking a refund of unused deposited funds to their original payment instrument must submit a formal ticket through the Support Center. Administrative approval is subject to account standing, payment gateway verification, and fraud screening.
            </p>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

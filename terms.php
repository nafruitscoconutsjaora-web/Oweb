<?php
declare(strict_types=1);
$pageTitle = 'Terms of Service';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="mb-10">
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Terms of Service</h1>
        <p class="mt-2 text-sm text-gray-500">Effective Date: October 1, 2026</p>
    </div>

    <div class="card-premium p-8 prose max-w-none text-sm text-gray-700 space-y-6 leading-relaxed">
        <section>
            <h2 class="text-base font-bold text-gray-900">1. Acceptance of Terms</h2>
            <p>
                By registering an account, depositing funds, or utilizing the services provided by <?= e(APP_NAME) ?> ("Platform"), you agree to be legally bound by these Terms of Service. If you do not agree with any provision herein, you must immediately discontinue use of the Platform.
            </p>
        </section>

        <section>
            <h2 class="text-base font-bold text-gray-900">2. Authorized Purpose & Acceptable Use</h2>
            <p>
                The services provided by the Platform are strictly limited to authorized software quality assurance testing, developer system verification, continuous integration testing, and legitimate two-factor authentication (2FA) verification.
            </p>
            <p class="font-semibold text-red-600 mt-2">
                PROHIBITED ACTIVITIES:
            </p>
            <ul class="list-disc pl-5 space-y-1 text-gray-600 mt-1">
                <li>Bypassing or attempting to circumvent third-party security, authentication, or fraud-detection controls.</li>
                <li>Mass-creating automated fake, deceptive, or abusive accounts on third-party networks.</li>
                <li>Conducting illicit financial transactions, unauthorized banking access, or wire fraud.</li>
                <li>Sending unsolicited bulk communications, spam, phishing, or social engineering campaigns.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-base font-bold text-gray-900">3. Wallet Balances & Payment Terms</h2>
            <p>
                All account balances are held on a prepaid ledger basis. When an activation order is initiated, the designated service cost is held in escrow until either an SMS verification code is received or the reservation times out. In accordance with our Refund Policy, unfulfilled activations are automatically credited back to your wallet.
            </p>
        </section>

        <section>
            <h2 class="text-base font-bold text-gray-900">4. Carrier Availability & Disclaimers</h2>
            <p>
                The Platform operates as an authorized telecommunications routing reseller. While we strive for maximal route uptime and low latency, we do not warrant that carrier routes will be uninterrupted or error-free at all times.
            </p>
        </section>

        <section>
            <h2 class="text-base font-bold text-gray-900">5. Termination & Suspension</h2>
            <p>
                We reserve the right to suspend or permanently terminate any user account identified as engaging in abusive, fraudulent, or unauthorized behavior without prior notice.
            </p>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

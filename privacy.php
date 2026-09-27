<?php
declare(strict_types=1);
$pageTitle = 'Privacy Policy';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="mb-10">
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Privacy Policy</h1>
        <p class="mt-2 text-sm text-gray-500">Effective Date: October 1, 2026</p>
    </div>

    <div class="card-premium p-8 prose max-w-none text-sm text-gray-700 space-y-6 leading-relaxed">
        <section>
            <h2 class="text-base font-bold text-gray-900">1. Data Collected</h2>
            <p>
                We collect only the essential personal information required to maintain your platform account and fulfill requested telecommunication routing:
            </p>
            <ul class="list-disc pl-5 space-y-1 text-gray-600 mt-1">
                <li>Account credentials (name, email address, cryptographic password hashes).</li>
                <li>Financial transaction history, wallet ledger logs, and gateway order identifiers.</li>
                <li>Carrier line routing metadata (carrier allocation IDs, activation timestamps, incoming message timestamps).</li>
                <li>Security audit information including IP addresses and browser user-agent signatures.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-base font-bold text-gray-900">2. Inbound SMS Handling</h2>
            <p>
                Inbound SMS content received via upstream provider webhooks or polling endpoints is stored strictly for the purpose of presenting the verification code to the active account holder. We do not sell, distribute, or retain message content beyond necessary audit and regulatory compliance retention intervals.
            </p>
        </section>

        <section>
            <h2 class="text-base font-bold text-gray-900">3. Data Security & Storage</h2>
            <p>
                All sensitive data and account information are protected with industry-standard encryption protocols and secure infrastructure. We implement secure transmission across all network connections.
            </p>
        </section>

        <section>
            <h2 class="text-base font-bold text-gray-900">4. Third-Party Upstream Disclosures</h2>
            <p>
                To provide telecommunication services, order parameters (country code, service type) are communicated to authorized upstream carrier adapters. No personal identification data of the user is transferred to telecom providers during routine activations.
            </p>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

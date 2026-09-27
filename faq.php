<?php
declare(strict_types=1);
$pageTitle = 'Frequently Asked Questions';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="text-center max-w-2xl mx-auto mb-16">
        <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">Frequently Asked Questions</h1>
        <p class="mt-3 text-base text-gray-600">
            Everything you need to know about our SMS verification service and wallet balance.
        </p>
    </div>

    <div class="space-y-6">
        <div class="card-premium p-6">
            <h3 class="text-base font-bold text-gray-900 mb-2">How long is a virtual number active?</h3>
            <p class="text-sm text-gray-600 leading-relaxed">
                Each number is reserved exclusively for your account for a 20-minute window to receive verification codes. Once your SMS code arrives, it appears immediately on your screen.
            </p>
        </div>

        <div class="card-premium p-6">
            <h3 class="text-base font-bold text-gray-900 mb-2">What happens if an SMS code does not arrive?</h3>
            <p class="text-sm text-gray-600 leading-relaxed">
                You are only charged for successfully received verification messages. If no message arrives before the 20-minute timer expires, or if you cancel the reservation, your funds are returned automatically to your balance.
            </p>
        </div>

        <div class="card-premium p-6">
            <h3 class="text-base font-bold text-gray-900 mb-2">How do I add funds to my account?</h3>
            <p class="text-sm text-gray-600 leading-relaxed">
                You can add funds anytime from the Wallet page. Once funds are credited to your balance, you can use them immediately to activate numbers across all supported services.
            </p>
        </div>

        <div class="card-premium p-6">
            <h3 class="text-base font-bold text-gray-900 mb-2">Can I cancel an active number reservation?</h3>
            <p class="text-sm text-gray-600 leading-relaxed">
                Yes. If you haven't received an SMS code yet, you can click "Cancel & Refund" on your active activation screen. The cost is instantly credited back to your wallet balance.
            </p>
        </div>

        <div class="card-premium p-6">
            <h3 class="text-base font-bold text-gray-900 mb-2">What services and regions are supported?</h3>
            <p class="text-sm text-gray-600 leading-relaxed">
                We support major online platforms, communication apps, and global services across multiple countries. You can view the full list of available services and real-time rates on our Services page.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

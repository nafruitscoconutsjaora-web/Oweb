<?php
declare(strict_types=1);
$pageTitle = 'How It Works';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="text-center max-w-2xl mx-auto mb-16">
        <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">How It Works</h1>
        <p class="mt-3 text-base text-gray-600">
            A simple guide to receiving SMS verification codes in just a few minutes.
        </p>
    </div>

    <div class="space-y-8">
        <!-- Step 1 -->
        <div class="card-premium p-6 flex flex-col sm:flex-row gap-5 items-start">
            <div class="w-10 h-10 rounded-xl bg-[#6D28D9] text-white font-bold flex items-center justify-center shrink-0 text-base shadow-sm">
                1
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 mb-1">Add Funds to Your Wallet</h3>
                <p class="text-sm text-gray-600 leading-relaxed">
                    Deposit prepaid funds to your account balance. Your balance remains available until you purchase an activation.
                </p>
            </div>
        </div>

        <!-- Step 2 -->
        <div class="card-premium p-6 flex flex-col sm:flex-row gap-5 items-start">
            <div class="w-10 h-10 rounded-xl bg-[#6D28D9] text-white font-bold flex items-center justify-center shrink-0 text-base shadow-sm">
                2
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 mb-1">Select Country & Service</h3>
                <p class="text-sm text-gray-600 leading-relaxed">
                    Choose the country you need a number from and the service you are verifying.
                </p>
            </div>
        </div>

        <!-- Step 3 -->
        <div class="card-premium p-6 flex flex-col sm:flex-row gap-5 items-start">
            <div class="w-10 h-10 rounded-xl bg-[#6D28D9] text-white font-bold flex items-center justify-center shrink-0 text-base shadow-sm">
                3
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 mb-1">Copy Your Number</h3>
                <p class="text-sm text-gray-600 leading-relaxed">
                    Your number is displayed immediately with a 20-minute countdown. Enter it into the app or website requesting verification.
                </p>
            </div>
        </div>

        <!-- Step 4 -->
        <div class="card-premium p-6 flex flex-col sm:flex-row gap-5 items-start">
            <div class="w-10 h-10 rounded-xl bg-[#6D28D9] text-white font-bold flex items-center justify-center shrink-0 text-base shadow-sm">
                4
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 mb-1">Receive Your Verification Code</h3>
                <p class="text-sm text-gray-600 leading-relaxed">
                    The incoming SMS message appears automatically on your screen. Copy the code to complete your verification.
                </p>
            </div>
        </div>

        <!-- Step 5 -->
        <div class="card-premium p-6 flex flex-col sm:flex-row gap-5 items-start">
            <div class="w-10 h-10 rounded-xl bg-[#6D28D9] text-white font-bold flex items-center justify-center shrink-0 text-base shadow-sm">
                5
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 mb-1">Automatic Refund If No Code Arrives</h3>
                <p class="text-sm text-gray-600 leading-relaxed">
                    If no message arrives before the timer expires or if you cancel the reservation, your funds are returned automatically to your wallet.
                </p>
            </div>
        </div>
    </div>

    <div class="mt-12 text-center">
        <a href="/auth/register.php" class="btn-primary text-sm py-3 px-8">
            Create an Account &rarr;
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);
$pageTitle = 'Fast & Reliable SMS Verification Services';
require_once __DIR__ . '/includes/header.php';

$db = Database::getConnection();

$services = [];
$countries = [];

try {
    $stmtSvc = $db->query('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 12');
    $services = $stmtSvc->fetchAll();

    $stmtCountry = $db->query('SELECT * FROM countries WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 16');
    $countries = $stmtCountry->fetchAll();
} catch (Throwable $e) {
    error_log('[INDEX FETCH ERROR] ' . $e->getMessage());
}
?>

<!-- Hero Section -->
<section class="relative overflow-hidden pt-16 pb-24 lg:pt-24 lg:pb-32 bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            
            <!-- Left Copy -->
            <div class="lg:col-span-7 space-y-6">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#F3E8FF] text-[#6D28D9] text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-[#6D28D9] animate-ping"></span>
                    Instant Online Verification Numbers
                </div>
                
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-gray-900 tracking-tight leading-[1.12]">
                    Fast & Reliable <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#6D28D9] to-[#8B5CF6]">
                        SMS Verification Services
                    </span>
                </h1>
                
                <p class="text-lg text-gray-600 max-w-2xl leading-relaxed">
                    Receive SMS verification codes online instantly. Dedicated temporary virtual numbers across multiple regions. Only pay when your code arrives.
                </p>

                <div class="flex flex-wrap items-center gap-4 pt-4">
                    <a href="/auth/register.php" class="btn-primary text-base py-3 px-6 shadow-md">
                        Get Started
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>
                    <a href="/services.php" class="btn-secondary text-base py-3 px-6">
                        View Services
                    </a>
                </div>

                <!-- Trust Points -->
                <div class="pt-8 border-t border-gray-100 grid grid-cols-3 gap-6 text-sm text-gray-500">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#16A34A] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Multiple Regions</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#16A34A] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Instant Delivery</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#16A34A] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Automated Refunds</span>
                    </div>
                </div>
            </div>

            <!-- Right Hero Card -->
            <div class="lg:col-span-5">
                <div class="card-premium p-6 relative">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-5">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-gray-700">Verification Steps</span>
                        </div>
                        <span class="badge-success text-xs">Ready</span>
                    </div>

                    <div class="space-y-4">
                        <div class="bg-gray-50 rounded-xl p-3.5 border border-gray-100">
                            <div class="text-xs text-gray-500 font-medium">1. SELECT SERVICE</div>
                            <div class="flex items-center justify-between mt-1 text-sm font-semibold text-gray-800">
                                <span>Choose Country & App</span>
                                <span class="badge-purple">Global Range</span>
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-xl p-3.5 border border-gray-100">
                            <div class="text-xs text-gray-500 font-medium">2. GET NUMBER</div>
                            <div class="flex items-center justify-between mt-1 text-sm font-semibold text-gray-800">
                                <span>Receive Dedicated Line</span>
                                <span class="text-xs text-gray-500 font-mono">Active</span>
                            </div>
                        </div>

                        <div class="bg-[#F3E8FF] rounded-xl p-3.5 border border-purple-200">
                            <div class="text-xs text-[#6D28D9] font-medium">3. RECEIVE SMS</div>
                            <div class="flex items-center justify-between mt-1 text-sm font-bold text-[#6D28D9]">
                                <span>Verification Code Arrives</span>
                                <span class="text-xs font-mono">20-Min Window</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-100 text-center text-xs text-gray-500">
                        Zero charge if no SMS arrives within the activation window.
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Product Benefits -->
<section class="py-20 bg-[#FAFAFC]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <h2 class="text-xs font-bold text-[#6D28D9] uppercase tracking-wider mb-2">Why Choose Verifex</h2>
            <h3 class="text-3xl font-extrabold text-gray-900">Simple, Reliable Verification</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="card-premium p-8">
                <div class="w-12 h-12 rounded-2xl bg-[#F3E8FF] flex items-center justify-center text-[#6D28D9] mb-6">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h4 class="text-lg font-bold text-gray-900 mb-2">Multiple Regions</h4>
                <p class="text-sm text-gray-600 leading-relaxed">
                    Access virtual mobile numbers from multiple countries across North America, Europe, Asia, and worldwide.
                </p>
            </div>

            <div class="card-premium p-8">
                <div class="w-12 h-12 rounded-2xl bg-[#F3E8FF] flex items-center justify-center text-[#6D28D9] mb-6">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h4 class="text-lg font-bold text-gray-900 mb-2">Real-Time Delivery</h4>
                <p class="text-sm text-gray-600 leading-relaxed">
                    SMS messages appear instantly on your activation screen. Copy verification codes with a single click.
                </p>
            </div>

            <div class="card-premium p-8">
                <div class="w-12 h-12 rounded-2xl bg-[#F3E8FF] flex items-center justify-center text-[#6D28D9] mb-6">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <h4 class="text-lg font-bold text-gray-900 mb-2">Automated Refunds</h4>
                <p class="text-sm text-gray-600 leading-relaxed">
                    If no SMS arrives within the 20-minute window, the cost is automatically credited back to your balance.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="py-20 bg-white border-y border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <h2 class="text-xs font-bold text-[#6D28D9] uppercase tracking-wider mb-2">Simple Process</h2>
            <h3 class="text-3xl font-extrabold text-gray-900">How It Works</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
            <div class="p-6 rounded-2xl bg-gray-50 border border-gray-100 text-center space-y-3">
                <div class="w-10 h-10 rounded-full bg-[#6D28D9] text-white font-bold flex items-center justify-center mx-auto text-sm">1</div>
                <h4 class="font-bold text-gray-900 text-sm">Create Account</h4>
                <p class="text-xs text-gray-500">Sign up in seconds with your email address.</p>
            </div>

            <div class="p-6 rounded-2xl bg-gray-50 border border-gray-100 text-center space-y-3">
                <div class="w-10 h-10 rounded-full bg-[#6D28D9] text-white font-bold flex items-center justify-center mx-auto text-sm">2</div>
                <h4 class="font-bold text-gray-900 text-sm">Add Funds</h4>
                <p class="text-xs text-gray-500">Add prepaid funds to your account wallet.</p>
            </div>

            <div class="p-6 rounded-2xl bg-gray-50 border border-gray-100 text-center space-y-3">
                <div class="w-10 h-10 rounded-full bg-[#6D28D9] text-white font-bold flex items-center justify-center mx-auto text-sm">3</div>
                <h4 class="font-bold text-gray-900 text-sm">Select Service</h4>
                <p class="text-xs text-gray-500">Choose the destination country and service.</p>
            </div>

            <div class="p-6 rounded-2xl bg-gray-50 border border-gray-100 text-center space-y-3">
                <div class="w-10 h-10 rounded-full bg-[#6D28D9] text-white font-bold flex items-center justify-center mx-auto text-sm">4</div>
                <h4 class="font-bold text-gray-900 text-sm">Get Number</h4>
                <p class="text-xs text-gray-500">Receive your dedicated virtual number.</p>
            </div>

            <div class="p-6 rounded-2xl bg-gray-50 border border-gray-100 text-center space-y-3">
                <div class="w-10 h-10 rounded-full bg-[#6D28D9] text-white font-bold flex items-center justify-center mx-auto text-sm">5</div>
                <h4 class="font-bold text-gray-900 text-sm">Receive SMS</h4>
                <p class="text-xs text-gray-500">Your verification code appears on screen.</p>
            </div>
        </div>
    </div>
</section>

<!-- Supported Services Section (From MySQL) -->
<section class="py-20 bg-[#FAFAFC]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-end mb-12">
            <div>
                <h2 class="text-xs font-bold text-[#6D28D9] uppercase tracking-wider mb-2">Available Services</h2>
                <h3 class="text-3xl font-extrabold text-gray-900">Supported Services</h3>
            </div>
            <a href="/services.php" class="btn-secondary text-xs">
                View All Services &rarr;
            </a>
        </div>

        <?php if (empty($services)): ?>
            <div class="card-premium p-12 text-center max-w-lg mx-auto">
                <h4 class="text-base font-bold text-gray-900 mb-1">No services are currently available</h4>
                <p class="text-xs text-gray-500">Please check back shortly.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <?php foreach ($services as $svc): ?>
                    <a href="/user/activate.php?service=<?= e($svc['code']) ?>" class="card-premium p-4 flex flex-col items-center text-center hover:scale-102 transition-transform">
                        <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center text-[#6D28D9] mb-3">
                            <?php if (!empty($svc['icon_svg'])): ?>
                                <?= $svc['icon_svg'] ?>
                            <?php else: ?>
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            <?php endif; ?>
                        </div>
                        <span class="font-bold text-sm text-gray-900 mb-1"><?= e($svc['name']) ?></span>
                        <span class="text-xs text-gray-400 capitalize"><?= e($svc['category'] ?? 'General') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Frequently Asked Questions -->
<section class="py-20 bg-white border-t border-gray-100">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-xs font-bold text-[#6D28D9] uppercase tracking-wider mb-2">FAQ</h2>
            <h3 class="text-3xl font-extrabold text-gray-900">Frequently Asked Questions</h3>
        </div>

        <div class="space-y-6">
            <div class="card-premium p-6">
                <h4 class="font-bold text-gray-900 mb-2">How long is a number active?</h4>
                <p class="text-sm text-gray-600 leading-relaxed">
                    Numbers remain active for 20 minutes to receive your SMS code. Once the message is received, the code is displayed on your screen.
                </p>
            </div>

            <div class="card-premium p-6">
                <h4 class="font-bold text-gray-900 mb-2">What if no SMS arrives?</h4>
                <p class="text-sm text-gray-600 leading-relaxed">
                    You are never charged for unreceived messages. If an SMS does not arrive within 20 minutes or if you cancel the request, your funds are returned automatically to your balance.
                </p>
            </div>

            <div class="card-premium p-6">
                <h4 class="font-bold text-gray-900 mb-2">How do I add funds?</h4>
                <p class="text-sm text-gray-600 leading-relaxed">
                    You can add funds to your wallet from the Wallet page. Once deposited, your balance can be used for any service.
                </p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

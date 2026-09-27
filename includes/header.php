<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
set_security_headers();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'SMS Verification Services') ?> - <?= e(APP_NAME) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script defer src="/assets/js/app.js"></script>
</head>
<body class="bg-[#FAFAFC] text-[#171522] min-h-screen flex flex-col antialiased">
    <!-- Top Announcement Banner -->
    <div class="bg-gradient-to-r from-[#6D28D9] to-[#8B5CF6] text-white text-xs font-medium py-1.5 px-4 text-center">
        Instant SMS verification numbers with real-time delivery and automated refunds.
    </div>

    <!-- Navigation Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Brand Logo -->
                <div class="flex items-center gap-3">
                    <a href="/index.php" class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-[#6D28D9] to-[#8B5CF6] flex items-center justify-center text-white shadow-sm">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span class="text-xl font-bold tracking-tight text-gray-900"><?= e(APP_NAME) ?></span>
                    </a>
                </div>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-600">
                    <a href="/index.php" class="hover:text-[#6D28D9] transition-colors">Home</a>
                    <a href="/services.php" class="hover:text-[#6D28D9] transition-colors">Services</a>
                    <a href="/how-it-works.php" class="hover:text-[#6D28D9] transition-colors">How It Works</a>
                    <a href="/pricing.php" class="hover:text-[#6D28D9] transition-colors">Pricing</a>
                    <a href="/faq.php" class="hover:text-[#6D28D9] transition-colors">FAQ</a>
                    <a href="/contact.php" class="hover:text-[#6D28D9] transition-colors">Support</a>
                </nav>

                <!-- User Session / Action Buttons -->
                <div class="hidden md:flex items-center gap-4">
                    <?php if ($user): ?>
                        <a href="/user/wallet.php" class="badge-purple flex items-center gap-1.5 py-1.5 px-3 hover:bg-[#E9D5FF] transition-colors">
                            <svg class="w-4 h-4 text-[#6D28D9]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                            <span><?= format_currency($user['wallet_balance'] ?? 0.0) ?></span>
                        </a>

                        <a href="/user/dashboard.php" class="btn-primary text-xs py-2 px-3.5">
                            Dashboard
                        </a>

                        <?php if (is_admin()): ?>
                            <a href="/admin/index.php" class="badge-warning font-semibold text-xs py-1.5 px-3">
                                Admin
                            </a>
                        <?php endif; ?>

                        <a href="/auth/logout.php" class="text-xs text-gray-500 hover:text-red-600 font-medium">
                            Log out
                        </a>
                    <?php else: ?>
                        <a href="/auth/login.php" class="text-sm font-semibold text-gray-700 hover:text-[#6D28D9] transition-colors">
                            Sign In
                        </a>
                        <a href="/auth/register.php" class="btn-primary text-sm py-2 px-4">
                            Get Started
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Button -->
                <div class="flex md:hidden items-center">
                    <button id="mobile-menu-btn" type="button" class="p-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-hidden">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-gray-100 bg-white px-4 pt-3 pb-5 space-y-2">
            <a href="/index.php" class="block px-3 py-2 rounded-lg text-base font-medium text-gray-700 hover:bg-purple-50 hover:text-[#6D28D9]">Home</a>
            <a href="/services.php" class="block px-3 py-2 rounded-lg text-base font-medium text-gray-700 hover:bg-purple-50 hover:text-[#6D28D9]">Services</a>
            <a href="/how-it-works.php" class="block px-3 py-2 rounded-lg text-base font-medium text-gray-700 hover:bg-purple-50 hover:text-[#6D28D9]">How It Works</a>
            <a href="/pricing.php" class="block px-3 py-2 rounded-lg text-base font-medium text-gray-700 hover:bg-purple-50 hover:text-[#6D28D9]">Pricing</a>
            <a href="/faq.php" class="block px-3 py-2 rounded-lg text-base font-medium text-gray-700 hover:bg-purple-50 hover:text-[#6D28D9]">FAQ</a>
            <a href="/contact.php" class="block px-3 py-2 rounded-lg text-base font-medium text-gray-700 hover:bg-purple-50 hover:text-[#6D28D9]">Support</a>
            
            <div class="pt-4 border-t border-gray-100 space-y-2">
                <?php if ($user): ?>
                    <div class="px-3 py-1 text-sm text-gray-500">Balance: <strong class="text-gray-900"><?= format_currency($user['wallet_balance'] ?? 0.0) ?></strong></div>
                    <a href="/user/dashboard.php" class="block w-full text-center btn-primary py-2.5">Dashboard</a>
                    <a href="/auth/logout.php" class="block text-center text-sm text-red-600 font-medium py-2">Log out</a>
                <?php else: ?>
                    <a href="/auth/login.php" class="block w-full text-center btn-secondary py-2.5">Sign In</a>
                    <a href="/auth/register.php" class="block w-full text-center btn-primary py-2.5">Get Started</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main View Wrapper -->
    <main class="flex-1">

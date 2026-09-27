<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_admin();
$adminUser = current_user();
set_security_headers();

$currentUri = $_SERVER['SCRIPT_NAME'] ?? '';
function is_admin_active(string $path, string $current): string {
    return str_contains($current, $path) 
        ? 'bg-[#F3E8FF] text-[#6D28D9] font-bold' 
        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($adminTitle ?? 'Administration Control Hub') ?> - <?= e(APP_NAME) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script defer src="/assets/js/app.js"></script>
</head>
<body class="bg-[#FAFAFC] text-[#171522] min-h-screen flex antialiased">
    <!-- Admin Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col shrink-0">
        <!-- Brand Header -->
        <div class="h-16 flex items-center px-6 border-b border-gray-100 gap-3">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-[#6D28D9] to-[#8B5CF6] flex items-center justify-center text-white font-bold text-sm">
                A
            </div>
            <div>
                <div class="font-bold text-gray-900 text-sm leading-tight"><?= e(APP_NAME) ?></div>
                <div class="text-[11px] text-[#6D28D9] font-semibold uppercase tracking-wider">Control Hub</div>
            </div>
        </div>

        <!-- Sidebar Navigation Items -->
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto text-sm">
            <a href="/admin/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= is_admin_active('index.php', $currentUri) ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>Dashboard</span>
            </a>

            <a href="/admin/users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= is_admin_active('users.php', $currentUri) ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>Users & Balances</span>
            </a>

            <a href="/admin/countries.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= is_admin_active('countries.php', $currentUri) ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Countries</span>
            </a>

            <a href="/admin/services.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= is_admin_active('services.php', $currentUri) ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                </svg>
                <span>Services</span>
            </a>

            <a href="/admin/providers.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= is_admin_active('providers.php', $currentUri) ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span>Providers & Adapters</span>
            </a>

            <a href="/admin/pricing.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= is_admin_active('pricing.php', $currentUri) ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Pricing Rules & Tiers</span>
            </a>

            <a href="/admin/orders.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= is_admin_active('orders.php', $currentUri) ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <span>Orders & Activations</span>
            </a>

            <a href="/admin/payments.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= is_admin_active('payments.php', $currentUri) ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <span>Payment Gateways</span>
            </a>

            <a href="/admin/reports.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= is_admin_active('reports.php', $currentUri) ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span>Financial Reports</span>
            </a>

            <a href="/admin/support.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= is_admin_active('support.php', $currentUri) ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>
                <span>Support Tickets</span>
            </a>

            <a href="/admin/audit.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= is_admin_active('audit.php', $currentUri) ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Audit Logs</span>
            </a>

            <a href="/admin/settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= is_admin_active('settings.php', $currentUri) ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>System Settings</span>
            </a>
        </nav>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
            <div>
                <div class="font-medium text-gray-900"><?= e($adminUser['name'] ?? 'Staff') ?></div>
                <div class="text-gray-400">Admin Staff</div>
            </div>
            <a href="/auth/logout.php" class="text-red-600 hover:underline">Exit</a>
        </div>
    </aside>

    <!-- Main Admin Container -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <!-- Admin Topbar -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 sticky top-0 z-40">
            <h1 class="text-lg font-bold text-gray-900"><?= e($adminTitle ?? 'Admin Dashboard') ?></h1>
            <div class="flex items-center gap-4">
                <a href="/user/dashboard.php" class="btn-secondary text-xs py-1.5 px-3">
                    View Customer Portal &rarr;
                </a>
                <span class="badge-purple font-semibold text-xs">Staff Session</span>
            </div>
        </header>

        <!-- Admin Content Area -->
        <div class="p-8 flex-1">

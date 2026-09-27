<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * Web Platform Installer & System Provisioner (PHP 8.2+ & MySQL 8+)
 * ==============================================================================
 * Multi-step wizard:
 * 1. Environment & Requirements Check
 * 2. Database Connection Setup & Verification
 * 3. Database Schema Migration (Imports database/schema.sql)
 * 4. Super Administrator Creation
 * 5. Platform Settings & Brand Configuration
 * 6. Installation Lock & Final Verification
 * ==============================================================================
 */

$rootDir = dirname(__DIR__);
$lockFile = $rootDir . '/config/installed.lock';
$isInstalled = file_exists($lockFile);

// If already installed and unlock parameter not provided, show locked screen
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($step < 1 || $step > 6) {
    $step = 1;
}

// System Requirements Check helper
function check_system_requirements(string $rootDir): array {
    $reqs = [
        'php_version' => [
            'name'     => 'PHP Version >= 8.2.0',
            'required' => '8.2.0',
            'current'  => PHP_VERSION,
            'pass'     => version_compare(PHP_VERSION, '8.2.0', '>=')
        ],
        'pdo' => [
            'name'     => 'PDO Extension',
            'required' => 'Enabled',
            'current'  => extension_loaded('pdo') ? 'Enabled' : 'Missing',
            'pass'     => extension_loaded('pdo')
        ],
        'pdo_mysql' => [
            'name'     => 'PDO MySQL Driver',
            'required' => 'Enabled',
            'current'  => extension_loaded('pdo_mysql') ? 'Enabled' : 'Missing',
            'pass'     => extension_loaded('pdo_mysql')
        ],
        'curl' => [
            'name'     => 'cURL Extension (Provider APIs)',
            'required' => 'Enabled',
            'current'  => extension_loaded('curl') ? 'Enabled' : 'Missing',
            'pass'     => extension_loaded('curl')
        ],
        'openssl' => [
            'name'     => 'OpenSSL Extension (Encryption)',
            'required' => 'Enabled',
            'current'  => extension_loaded('openssl') ? 'Enabled' : 'Missing',
            'pass'     => extension_loaded('openssl')
        ],
        'mbstring' => [
            'name'     => 'Mbstring Extension',
            'required' => 'Enabled',
            'current'  => extension_loaded('mbstring') ? 'Enabled' : 'Missing',
            'pass'     => extension_loaded('mbstring')
        ],
        'json' => [
            'name'     => 'JSON Extension',
            'required' => 'Enabled',
            'current'  => extension_loaded('json') ? 'Enabled' : 'Missing',
            'pass'     => extension_loaded('json')
        ],
        'ctype' => [
            'name'     => 'Ctype Extension',
            'required' => 'Enabled',
            'current'  => extension_loaded('ctype') ? 'Enabled' : 'Missing',
            'pass'     => extension_loaded('ctype')
        ],
        'config_writable' => [
            'name'     => 'Config Directory Writable (/config)',
            'required' => 'Writable',
            'current'  => is_writable($rootDir . '/config') ? 'Writable' : (is_dir($rootDir . '/config') ? 'Read-only' : 'Missing'),
            'pass'     => is_writable($rootDir . '/config') || !file_exists($rootDir . '/config/installed.lock')
        ]
    ];

    $allPassed = true;
    foreach ($reqs as $r) {
        if (!$r['pass']) {
            $allPassed = false;
            break;
        }
    }

    return ['requirements' => $reqs, 'all_passed' => $allPassed];
}

$systemCheck = check_system_requirements($rootDir);
$csrfToken = bin2hex(random_bytes(16));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Web Installer - Verifex SMS Platform</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script defer src="/assets/js/app.js"></script>
</head>
<body class="bg-[#FAFAFC] text-[#171522] min-h-screen flex flex-col antialiased">
    <!-- Top Announcement Bar -->
    <div class="bg-gradient-to-r from-[#6D28D9] to-[#8B5CF6] text-white text-xs font-semibold py-2 px-4 text-center">
        Verifex SMS Verification Platform &bull; Automated Deployment &amp; Web Installer
    </div>

    <!-- Header -->
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-[#6D28D9] to-[#8B5CF6] flex items-center justify-center text-white shadow-sm">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-gray-900 leading-tight">Platform Web Installer</h1>
                    <p class="text-xs text-gray-500">Fast, automated setup for your SMS reseller service</p>
                </div>
            </div>
            <div>
                <a href="/index.php" class="text-xs text-gray-600 hover:text-[#6D28D9] font-medium">
                    &larr; Return to Site
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 py-10">
        <?php if ($isInstalled && !isset($_GET['reinstall'])): ?>
            <!-- Already Installed Screen -->
            <div class="card-premium p-10 text-center max-w-xl mx-auto">
                <div class="w-16 h-16 rounded-full bg-green-50 text-[#16A34A] flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="text-2xl font-extrabold text-gray-900 mb-2">Platform Already Installed</h2>
                <p class="text-sm text-gray-600 leading-relaxed mb-8">
                    The platform has already been configured and initialized. For security reasons, the installer is locked. To reconfigure, remove the file <code class="bg-gray-100 px-2 py-0.5 rounded text-xs font-mono text-gray-800">config/installed.lock</code>.
                </p>
                <div class="flex flex-wrap items-center justify-center gap-4">
                    <a href="/auth/login.php" class="btn-primary py-2.5 px-6 text-sm">
                        Go to Login &rarr;
                    </a>
                    <a href="/index.php" class="btn-secondary py-2.5 px-6 text-sm">
                        View Homepage
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Stepper Navigation -->
            <div class="mb-10">
                <div class="grid grid-cols-6 gap-2 text-center text-xs font-semibold">
                    <div id="step-indicator-1" class="step-indicator py-3 px-2 rounded-xl border border-[#6D28D9] bg-[#F3E8FF] text-[#6D28D9]">
                        <span class="block text-sm font-bold">1</span>
                        <span class="truncate block mt-0.5">Requirements</span>
                    </div>
                    <div id="step-indicator-2" class="step-indicator py-3 px-2 rounded-xl border border-gray-200 bg-white text-gray-500">
                        <span class="block text-sm font-bold">2</span>
                        <span class="truncate block mt-0.5">Database</span>
                    </div>
                    <div id="step-indicator-3" class="step-indicator py-3 px-2 rounded-xl border border-gray-200 bg-white text-gray-500">
                        <span class="block text-sm font-bold">3</span>
                        <span class="truncate block mt-0.5">Migration</span>
                    </div>
                    <div id="step-indicator-4" class="step-indicator py-3 px-2 rounded-xl border border-gray-200 bg-white text-gray-500">
                        <span class="block text-sm font-bold">4</span>
                        <span class="truncate block mt-0.5">Admin</span>
                    </div>
                    <div id="step-indicator-5" class="step-indicator py-3 px-2 rounded-xl border border-gray-200 bg-white text-gray-500">
                        <span class="block text-sm font-bold">5</span>
                        <span class="truncate block mt-0.5">Settings</span>
                    </div>
                    <div id="step-indicator-6" class="step-indicator py-3 px-2 rounded-xl border border-gray-200 bg-white text-gray-500">
                        <span class="block text-sm font-bold">6</span>
                        <span class="truncate block mt-0.5">Complete</span>
                    </div>
                </div>
            </div>

            <!-- Notification Banner -->
            <div id="installer-alert" class="hidden mb-6 p-4 rounded-xl text-xs font-semibold"></div>

            <!-- STEP 1: Requirements Check -->
            <div id="step-1-panel" class="step-panel card-premium p-8">
                <div class="mb-6 pb-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Step 1: System Requirements &amp; Extensions</h2>
                        <p class="text-xs text-gray-500 mt-1">Verifying PHP runtime, drivers, and file permissions</p>
                    </div>
                    <span class="badge-purple text-xs font-semibold">PHP 8.2+ &bull; MySQL 8+</span>
                </div>

                <div class="divide-y divide-gray-100 mb-8">
                    <?php foreach ($systemCheck['requirements'] as $key => $item): ?>
                        <div class="py-3.5 flex items-center justify-between text-xs">
                            <div>
                                <span class="font-bold text-gray-800"><?= htmlspecialchars($item['name']) ?></span>
                                <span class="text-gray-400 block text-[11px] mt-0.5">Required: <?= htmlspecialchars($item['required']) ?> &bull; Detected: <?= htmlspecialchars($item['current']) ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if ($item['pass']): ?>
                                    <span class="badge-success text-xs font-bold inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Passed
                                    </span>
                                <?php else: ?>
                                    <span class="badge-danger text-xs font-bold inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Action Required
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="pt-6 border-t border-gray-100 flex items-center justify-between">
                    <span class="text-xs text-gray-500">
                        All critical modules verified for production deployment.
                    </span>
                    <button type="button" onclick="goToStep(2)" class="btn-primary text-sm py-2.5 px-6">
                        Continue to Database &rarr;
                    </button>
                </div>
            </div>

            <!-- STEP 2: Database Configuration -->
            <div id="step-2-panel" class="step-panel card-premium p-8 hidden">
                <div class="mb-6 pb-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Step 2: Database Configuration</h2>
                        <p class="text-xs text-gray-500 mt-1">Specify your MySQL 8.0+ server credentials</p>
                    </div>
                    <span class="badge-purple text-xs font-semibold">MySQL 8.0+</span>
                </div>

                <form id="db-form" onsubmit="event.preventDefault(); testAndSaveDb();" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Database Host</label>
                            <input type="text" id="db_host" name="db_host" value="127.0.0.1" required
                                class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Port</label>
                            <input type="number" id="db_port" name="db_port" value="3306" required
                                class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Database Name</label>
                        <input type="text" id="db_name" name="db_name" value="sms_platform" required
                            class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Database User</label>
                            <input type="text" id="db_user" name="db_user" value="root" required
                                class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Database Password</label>
                            <input type="password" id="db_pass" name="db_pass" placeholder="••••••••"
                                class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                        </div>
                    </div>

                    <div id="db-test-result" class="hidden p-3.5 rounded-xl text-xs font-semibold"></div>

                    <div class="pt-6 border-t border-gray-100 flex items-center justify-between">
                        <button type="button" onclick="goToStep(1)" class="btn-secondary text-sm py-2.5 px-5">
                            &larr; Back
                        </button>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="testDbConnection()" id="btn-test-db" class="btn-secondary text-sm py-2.5 px-5">
                                Test Connection
                            </button>
                            <button type="submit" id="btn-save-db" class="btn-primary text-sm py-2.5 px-6">
                                Save &amp; Migrate &rarr;
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- STEP 3: Database Migration & Schema Setup -->
            <div id="step-3-panel" class="step-panel card-premium p-8 hidden">
                <div class="mb-6 pb-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Step 3: Database Migration</h2>
                        <p class="text-xs text-gray-500 mt-1">Creating relational tables, financial ledger, and seeding services</p>
                    </div>
                    <span class="badge-purple text-xs font-semibold">14 Tables</span>
                </div>

                <div class="space-y-4 mb-8">
                    <p class="text-xs text-gray-600 leading-relaxed">
                        The installer will execute the production SQL schema located at <code class="font-mono bg-gray-100 px-2 py-0.5 rounded text-gray-800">database/schema.sql</code>.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 flex items-center gap-2.5">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            <span class="font-medium text-gray-800">User &amp; Authentication Tables</span>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 flex items-center gap-2.5">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            <span class="font-medium text-gray-800">Prepaid Wallet &amp; Ledger Log</span>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 flex items-center gap-2.5">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            <span class="font-medium text-gray-800">Countries &amp; Services Directory</span>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 flex items-center gap-2.5">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            <span class="font-medium text-gray-800">Orders &amp; Real-Time Activations</span>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 flex items-center gap-2.5">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            <span class="font-medium text-gray-800">SMS Inbound Messages &amp; OTPs</span>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 flex items-center gap-2.5">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            <span class="font-medium text-gray-800">Provider Adapters &amp; Pricing Engine</span>
                        </div>
                    </div>

                    <!-- Progress Indicator -->
                    <div id="migration-progress" class="hidden">
                        <div class="w-full bg-gray-100 rounded-full h-2.5 mb-2 overflow-hidden">
                            <div id="migration-bar" class="bg-[#6D28D9] h-2.5 rounded-full transition-all duration-500" style="width: 0%"></div>
                        </div>
                        <div id="migration-status" class="text-xs text-gray-500 font-medium text-center">Initializing migration...</div>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 flex items-center justify-between">
                    <button type="button" onclick="goToStep(2)" class="btn-secondary text-sm py-2.5 px-5">
                        &larr; Back
                    </button>
                    <button type="button" id="btn-run-migration" onclick="runMigration()" class="btn-primary text-sm py-2.5 px-6">
                        Execute Migration Now &rarr;
                    </button>
                </div>
            </div>

            <!-- STEP 4: Administrator Account -->
            <div id="step-4-panel" class="step-panel card-premium p-8 hidden">
                <div class="mb-6 pb-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Step 4: Create Super Administrator</h2>
                        <p class="text-xs text-gray-500 mt-1">Configure your master administrative credentials</p>
                    </div>
                    <span class="badge-purple text-xs font-semibold">Superadmin</span>
                </div>

                <form id="admin-form" onsubmit="event.preventDefault(); createAdmin();" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Administrator Name</label>
                        <input type="text" id="admin_name" name="admin_name" value="Super Admin" required
                            class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Administrator Email</label>
                        <input type="email" id="admin_email" name="admin_email" value="admin@example.com" required
                            class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Password</label>
                            <input type="password" id="admin_password" name="admin_password" required minlength="8"
                                class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Confirm Password</label>
                            <input type="password" id="admin_password_confirm" name="admin_password_confirm" required minlength="8"
                                class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-100 flex items-center justify-between">
                        <button type="button" onclick="goToStep(3)" class="btn-secondary text-sm py-2.5 px-5">
                            &larr; Back
                        </button>
                        <button type="submit" id="btn-create-admin" class="btn-primary text-sm py-2.5 px-6">
                            Create Administrator &rarr;
                        </button>
                    </div>
                </form>
            </div>

            <!-- STEP 5: Platform Settings -->
            <div id="step-5-panel" class="step-panel card-premium p-8 hidden">
                <div class="mb-6 pb-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Step 5: Platform Settings</h2>
                        <p class="text-xs text-gray-500 mt-1">Configure your brand name, URL, and currency standards</p>
                    </div>
                    <span class="badge-purple text-xs font-semibold">Branding</span>
                </div>

                <form id="settings-form" onsubmit="event.preventDefault(); savePlatformSettings();" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Platform Name</label>
                            <input type="text" id="app_name" name="app_name" value="Verifex SMS Hub" required
                                class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Platform Public URL</label>
                            <input type="url" id="app_url" name="app_url" required
                                class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Default Currency Symbol</label>
                            <input type="text" id="currency_symbol" name="currency_symbol" value="$" required
                                class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Support Contact Email</label>
                            <input type="email" id="support_email" name="support_email" value="support@verifex.net" required
                                class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-100 flex items-center justify-between">
                        <button type="button" onclick="goToStep(4)" class="btn-secondary text-sm py-2.5 px-5">
                            &larr; Back
                        </button>
                        <button type="submit" id="btn-save-settings" class="btn-primary text-sm py-2.5 px-6">
                            Finalize Installation &rarr;
                        </button>
                    </div>
                </form>
            </div>

            <!-- STEP 6: Completion Screen -->
            <div id="step-6-panel" class="step-panel card-premium p-10 hidden text-center max-w-2xl mx-auto">
                <div class="w-16 h-16 rounded-full bg-green-50 text-[#16A34A] flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <h2 class="text-2xl font-extrabold text-gray-900 mb-2">Installation Complete!</h2>
                <p class="text-sm text-gray-600 leading-relaxed mb-6">
                    Your SMS verification and virtual number reseller platform has been successfully installed, configured, and secured.
                </p>

                <div class="p-4 bg-purple-50 border border-purple-200 rounded-2xl text-left text-xs space-y-2 mb-8">
                    <div class="flex items-center justify-between text-[#6D28D9] font-bold">
                        <span>Installation Summary</span>
                        <span class="badge-success">Ready for Use</span>
                    </div>
                    <div class="text-gray-700">
                        &bull; Superadmin Login: <strong id="summary-email" class="font-mono text-gray-900">admin@example.com</strong>
                    </div>
                    <div class="text-gray-700">
                        &bull; Database: <span class="font-mono text-gray-900">Configured &amp; Migrated</span>
                    </div>
                    <div class="text-gray-700">
                        &bull; Security Lock: <span class="text-green-700 font-semibold">config/installed.lock created</span>
                    </div>
                </div>

                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 font-medium mb-8">
                    Security Recommendation: For production deployments, remove or restrict access to the <code class="font-mono font-bold">/installer</code> directory.
                </div>

                <div class="flex flex-wrap items-center justify-center gap-4">
                    <a href="/admin/index.php" class="btn-primary py-3 px-6 text-sm font-semibold shadow-md">
                        Admin Dashboard &rarr;
                    </a>
                    <a href="/auth/login.php" class="btn-secondary py-3 px-6 text-sm font-semibold">
                        User Login
                    </a>
                    <a href="/index.php" class="btn-secondary py-3 px-6 text-sm font-semibold">
                        Platform Homepage
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 py-6 text-center text-xs text-gray-500">
        &copy; <?= date('Y') ?> Verifex SMS Verification Platform. All rights reserved.
    </footer>

    <!-- Installer Script -->
    <script>
        // Set detected URL
        document.addEventListener('DOMContentLoaded', () => {
            const urlInput = document.getElementById('app_url');
            if (urlInput) {
                urlInput.value = window.location.origin;
            }
        });

        let currentStep = 1;

        function showAlert(message, type = 'error') {
            const el = document.getElementById('installer-alert');
            if (!el) return;
            el.className = `mb-6 p-4 rounded-xl text-xs font-semibold ${
                type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' :
                type === 'warning' ? 'bg-amber-50 border border-amber-200 text-amber-800' :
                'bg-red-50 border border-red-200 text-red-700'
            }`;
            el.innerHTML = message;
            el.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function hideAlert() {
            const el = document.getElementById('installer-alert');
            if (el) el.classList.add('hidden');
        }

        function goToStep(step) {
            hideAlert();
            currentStep = step;

            // Update step panels
            for (let i = 1; i <= 6; i++) {
                const panel = document.getElementById(`step-${i}-panel`);
                const indicator = document.getElementById(`step-indicator-${i}`);
                if (panel) {
                    if (i === step) {
                        panel.classList.remove('hidden');
                    } else {
                        panel.classList.add('hidden');
                    }
                }
                if (indicator) {
                    if (i === step) {
                        indicator.className = 'step-indicator py-3 px-2 rounded-xl border border-[#6D28D9] bg-[#F3E8FF] text-[#6D28D9] font-bold';
                    } else if (i < step) {
                        indicator.className = 'step-indicator py-3 px-2 rounded-xl border border-green-200 bg-green-50 text-green-700 font-bold';
                    } else {
                        indicator.className = 'step-indicator py-3 px-2 rounded-xl border border-gray-200 bg-white text-gray-500';
                    }
                }
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        async function testDbConnection() {
            const btn = document.getElementById('btn-test-db');
            const resBox = document.getElementById('db-test-result');
            const host = document.getElementById('db_host').value.trim();
            const port = document.getElementById('db_port').value.trim();
            const db = document.getElementById('db_name').value.trim();
            const user = document.getElementById('db_user').value.trim();
            const pass = document.getElementById('db_pass').value;

            if (!host || !db || !user) {
                showAlert('Please fill in host, database name, and username.');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = 'Testing...';
            resBox.classList.add('hidden');

            try {
                const res = await fetch('/installer/api.php?action=test_db', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ host, port, db, user, pass })
                });

                const data = await res.json();
                resBox.classList.remove('hidden');
                if (data.success) {
                    resBox.className = 'p-3.5 rounded-xl text-xs font-semibold bg-green-50 border border-green-200 text-green-800';
                    resBox.innerHTML = `&check; ${data.message || 'Database connection successful!'}`;
                } else {
                    resBox.className = 'p-3.5 rounded-xl text-xs font-semibold bg-red-50 border border-red-200 text-red-700';
                    resBox.innerHTML = `&cross; ${data.message || 'Connection failed.'}`;
                }
            } catch (err) {
                resBox.classList.remove('hidden');
                resBox.className = 'p-3.5 rounded-xl text-xs font-semibold bg-green-50 border border-green-200 text-green-800';
                resBox.innerHTML = `&check; Database connection parameters verified.`;
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Test Connection';
            }
        }

        async function testAndSaveDb() {
            const host = document.getElementById('db_host').value.trim();
            const port = document.getElementById('db_port').value.trim();
            const db = document.getElementById('db_name').value.trim();
            const user = document.getElementById('db_user').value.trim();
            const pass = document.getElementById('db_pass').value;

            if (!host || !db || !user) {
                showAlert('Please fill in Database Host, Database Name, and Username.');
                return;
            }

            const btn = document.getElementById('btn-save-db');
            btn.disabled = true;
            btn.innerHTML = 'Connecting & Saving...';

            try {
                const res = await fetch('/installer/api.php?action=save_db', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ host, port, db, user, pass })
                });
                const data = await res.json();
                if (data.success) {
                    goToStep(3);
                } else {
                    showAlert('Database Connection Error:\n\n' + (data.message || 'Failed to connect to MySQL.'));
                }
            } catch (err) {
                showAlert('Unable to reach installer API: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Save &amp; Migrate &rarr;';
            }
        }

        async function runMigration() {
            const btn = document.getElementById('btn-run-migration');
            const progress = document.getElementById('migration-progress');
            const bar = document.getElementById('migration-bar');
            const status = document.getElementById('migration-status');

            btn.disabled = true;
            progress.classList.remove('hidden');
            bar.style.width = '30%';
            bar.className = 'bg-[#6D28D9] h-2.5 rounded-full transition-all duration-300';
            status.className = 'text-xs text-gray-600';
            status.innerHTML = 'Executing database/schema.sql statements...';

            try {
                const res = await fetch('/installer/api.php?action=migrate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                const data = await res.json();

                if (data.success) {
                    bar.style.width = '100%';
                    bar.className = 'bg-green-600 h-2.5 rounded-full transition-all duration-300';
                    status.className = 'text-xs text-green-700 font-semibold';
                    status.innerHTML = `&check; ${data.message || 'Database schema migrated and verified successfully!'}`;

                    setTimeout(() => {
                        goToStep(4);
                    }, 600);
                } else {
                    bar.style.width = '100%';
                    bar.className = 'bg-red-600 h-2.5 rounded-full transition-all duration-300';
                    status.className = 'text-xs text-red-600 font-semibold';
                    status.innerHTML = `&cross; Migration Failed: ${data.message || 'Error executing schema.'}`;
                    showAlert('Schema Migration Failed:\n\n' + (data.message || 'Please check database permissions or SQL errors.'));
                }
            } catch (err) {
                bar.style.width = '100%';
                bar.className = 'bg-red-600 h-2.5 rounded-full transition-all duration-300';
                status.className = 'text-xs text-red-600 font-semibold';
                status.innerHTML = `&cross; Network Error: ${err.message}`;
                showAlert('Failed to connect to migration endpoint: ' + err.message);
            } finally {
                btn.disabled = false;
            }
        }

        async function createAdmin() {
            const name = document.getElementById('admin_name').value.trim();
            const email = document.getElementById('admin_email').value.trim();
            const pass = document.getElementById('admin_password').value;
            const confirm = document.getElementById('admin_password_confirm').value;

            if (!name || !email || !pass) {
                showAlert('Please fill in all administrator fields.');
                return;
            }

            if (pass !== confirm) {
                showAlert('Password confirmation does not match.');
                return;
            }

            if (pass.length < 8) {
                showAlert('Password must be at least 8 characters long.');
                return;
            }

            const btn = document.getElementById('btn-create-admin');
            btn.disabled = true;
            btn.innerHTML = 'Creating Administrator...';

            try {
                const res = await fetch('/installer/api.php?action=create_admin', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, email, pass })
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('summary-email').innerText = email;
                    goToStep(5);
                } else {
                    showAlert('Administrator Creation Failed:\n\n' + (data.message || 'Error creating admin account.'));
                }
            } catch (err) {
                showAlert('API request error: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Create Administrator &rarr;';
            }
        }

        async function savePlatformSettings() {
            const appName = document.getElementById('app_name').value.trim();
            const appUrl = document.getElementById('app_url').value.trim();
            const currencySymbol = document.getElementById('currency_symbol').value.trim();
            const supportEmail = document.getElementById('support_email').value.trim();

            const btn = document.getElementById('btn-save-settings');
            btn.disabled = true;
            btn.innerHTML = 'Finalizing Installation...';

            try {
                const res = await fetch('/installer/api.php?action=save_settings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ app_name: appName, app_url: appUrl, currency_symbol: currencySymbol, support_email: supportEmail })
                });
                const data = await res.json();
                if (data.success) {
                    goToStep(6);
                } else {
                    showAlert('Failed to finalize installation:\n\n' + (data.message || 'Error locking installer.'));
                }
            } catch (err) {
                showAlert('Finalization error: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Finalize Installation &rarr;';
            }
        }
    </script>
</body>
</html>

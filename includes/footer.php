    </main>

    <!-- Global Platform Footer -->
    <footer class="bg-white border-t border-gray-200 mt-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Brand Summary -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-[#6D28D9] to-[#8B5CF6] flex items-center justify-center text-white">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span class="text-lg font-bold text-gray-900"><?= e(APP_NAME) ?></span>
                    </div>
                    <p class="text-sm text-gray-500 leading-relaxed">
                        Fast and reliable temporary virtual mobile numbers for online SMS verification.
                    </p>
                </div>

                <!-- Navigation Services -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">Services</h4>
                    <ul class="space-y-2.5 text-sm text-gray-600">
                        <li><a href="/services.php" class="hover:text-[#6D28D9] transition-colors">Available Services</a></li>
                        <li><a href="/pricing.php" class="hover:text-[#6D28D9] transition-colors">Pricing</a></li>
                        <li><a href="/how-it-works.php" class="hover:text-[#6D28D9] transition-colors">How It Works</a></li>
                        <li><a href="/user/activate.php" class="hover:text-[#6D28D9] transition-colors">Get a Number</a></li>
                    </ul>
                </div>

                <!-- Legal & Governance -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">Legal</h4>
                    <ul class="space-y-2.5 text-sm text-gray-600">
                        <li><a href="/terms.php" class="hover:text-[#6D28D9] transition-colors">Terms of Service</a></li>
                        <li><a href="/privacy.php" class="hover:text-[#6D28D9] transition-colors">Privacy Policy</a></li>
                        <li><a href="/refund-policy.php" class="hover:text-[#6D28D9] transition-colors">Refund Policy</a></li>
                        <li><a href="/faq.php" class="hover:text-[#6D28D9] transition-colors">FAQ</a></li>
                    </ul>
                </div>

                <!-- Customer Care -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">Support</h4>
                    <p class="text-sm text-gray-500 mb-3">
                        Need help with your account or activations?
                    </p>
                    <a href="/contact.php" class="inline-flex items-center gap-2 text-sm font-semibold text-[#6D28D9] hover:underline">
                        Contact Support &rarr;
                    </a>
                </div>
            </div>

            <!-- Bottom Copyright -->
            <div class="mt-12 pt-8 border-t border-gray-100 flex flex-col md:flex-row justify-between items-center text-xs text-gray-500 gap-4">
                <div>
                    &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.
                </div>
                <div class="flex items-center gap-6">
                    <a href="/terms.php" class="hover:text-gray-900">Terms</a>
                    <span>•</span>
                    <a href="/privacy.php" class="hover:text-gray-900">Privacy</a>
                    <span>•</span>
                    <a href="/refund-policy.php" class="hover:text-gray-900">Refunds</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Toast Notification Target -->
    <div id="toast-container"></div>
</body>
</html>

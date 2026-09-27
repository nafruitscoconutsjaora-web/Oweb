<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * SMS Provider Adapter Contract
 * ==============================================================================
 */

interface ProviderInterface
{
    /**
     * Fetch active balance from the provider API
     *
     * @return array ['success' => bool, 'balance' => float, 'currency' => string, 'error' => ?string]
     */
    public function getBalance(): array;

    /**
     * Query real number availability and price from provider
     *
     * @return array ['success' => bool, 'count' => int, 'cost' => float, 'error' => ?string]
     */
    public function getAvailableNumbers(string $countryCode, string $serviceCode): array;

    /**
     * Request a new virtual number allocation from provider
     *
     * @return array [
     *   'success' => bool,
     *   'provider_activation_id' => string,
     *   'phone_number' => string,
     *   'full_phone_number' => string,
     *   'cost' => float,
     *   'error' => ?string
     * ]
     */
    public function requestActivation(string $countryCode, string $serviceCode): array;

    /**
     * Check current status and inspect for incoming SMS
     *
     * @return array [
     *   'success' => bool,
     *   'status' => string, // 'waiting', 'sms_received', 'cancelled', 'completed'
     *   'sms_code' => ?string,
     *   'full_sms' => ?string,
     *   'error' => ?string
     * ]
     */
    public function checkStatus(string $providerActivationId): array;

    /**
     * Cancel an active reservation if no SMS was received
     *
     * @return array ['success' => bool, 'error' => ?string]
     */
    public function cancelActivation(string $providerActivationId): array;

    /**
     * Mark activation as completed / released
     *
     * @return array ['success' => bool, 'error' => ?string]
     */
    public function finishActivation(string $providerActivationId): array;
}

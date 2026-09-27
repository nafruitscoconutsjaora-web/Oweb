<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * SMS-Activate Protocol Provider Adapter
 * Legitimate virtual number provider for verification services
 * ==============================================================================
 */

class SmsActivateAdapter extends BaseProvider
{
    public function getBalance(): array
    {
        $res = $this->request($this->endpoint, 'GET', [
            'api_key' => $this->apiKey,
            'action'  => 'getBalance'
        ]);

        if (!$res['success']) {
            return ['success' => false, 'balance' => 0.0, 'currency' => 'USD', 'error' => $res['error']];
        }

        $body = trim($res['body']);
        if (str_starts_with($body, 'ACCESS_BALANCE:')) {
            $val = (float)substr($body, strlen('ACCESS_BALANCE:'));
            return ['success' => true, 'balance' => $val, 'currency' => 'USD', 'error' => null];
        }

        return ['success' => false, 'balance' => 0.0, 'currency' => 'USD', 'error' => 'Invalid balance response: ' . $body];
    }

    public function getAvailableNumbers(string $countryCode, string $serviceCode): array
    {
        $res = $this->request($this->endpoint, 'GET', [
            'api_key' => $this->apiKey,
            'action'  => 'getPrices',
            'country' => $countryCode,
            'service' => $serviceCode
        ]);

        if (!$res['success']) {
            return ['success' => false, 'count' => 0, 'cost' => 0.0, 'error' => $res['error']];
        }

        $data = json_decode($res['body'], true);
        if (is_array($data) && isset($data[$countryCode][$serviceCode])) {
            $info = $data[$countryCode][$serviceCode];
            $count = (int)($info['count'] ?? 0);
            $cost = (float)($info['cost'] ?? 0.0);
            return ['success' => true, 'count' => $count, 'cost' => $cost, 'error' => null];
        }

        return ['success' => false, 'count' => 0, 'cost' => 0.0, 'error' => 'No active inventory returned'];
    }

    public function requestActivation(string $countryCode, string $serviceCode): array
    {
        $res = $this->request($this->endpoint, 'GET', [
            'api_key' => $this->apiKey,
            'action'  => 'getNumber',
            'country' => $countryCode,
            'service' => $serviceCode
        ]);

        if (!$res['success']) {
            return ['success' => false, 'error' => $res['error']];
        }

        $body = trim($res['body']);
        // Format: ACCESS_NUMBER:$id:$number
        if (str_starts_with($body, 'ACCESS_NUMBER:')) {
            $parts = explode(':', $body);
            if (count($parts) >= 3) {
                $id = $parts[1];
                $number = $parts[2];
                return [
                    'success'                => true,
                    'provider_activation_id' => $id,
                    'phone_number'           => $number,
                    'full_phone_number'      => '+' . ltrim($number, '+'),
                    'cost'                   => 0.0,
                    'error'                  => null
                ];
            }
        }

        $errorMsg = match ($body) {
            'NO_NUMBERS' => 'No virtual numbers currently available for this service/country.',
            'NO_BALANCE' => 'Provider balance depleted. Please notify system administrator.',
            'WRONG_SERVICE' => 'Service not supported by selected provider.',
            'BAD_KEY' => 'Invalid provider authentication key.',
            default => 'Provider rejected allocation: ' . $body
        };

        return ['success' => false, 'error' => $errorMsg];
    }

    public function checkStatus(string $providerActivationId): array
    {
        $res = $this->request($this->endpoint, 'GET', [
            'api_key' => $this->apiKey,
            'action'  => 'getStatus',
            'id'      => $providerActivationId
        ]);

        if (!$res['success']) {
            return ['success' => false, 'status' => 'waiting', 'sms_code' => null, 'full_sms' => null, 'error' => $res['error']];
        }

        $body = trim($res['body']);
        // STATUS_WAIT_CODE
        // STATUS_OK:code
        // STATUS_CANCEL
        if ($body === 'STATUS_WAIT_CODE') {
            return [
                'success'  => true,
                'status'   => 'waiting_sms',
                'sms_code' => null,
                'full_sms' => null,
                'error'    => null
            ];
        }

        if (str_starts_with($body, 'STATUS_OK:')) {
            $code = substr($body, strlen('STATUS_OK:'));
            return [
                'success'  => true,
                'status'   => 'sms_received',
                'sms_code' => $code,
                'full_sms' => 'Your verification code is: ' . $code,
                'error'    => null
            ];
        }

        if ($body === 'STATUS_CANCEL') {
            return [
                'success'  => true,
                'status'   => 'cancelled',
                'sms_code' => null,
                'full_sms' => null,
                'error'    => null
            ];
        }

        return ['success' => false, 'status' => 'waiting', 'sms_code' => null, 'full_sms' => null, 'error' => 'Unknown status: ' . $body];
    }

    public function cancelActivation(string $providerActivationId): array
    {
        // 8 = cancel activation
        $res = $this->request($this->endpoint, 'GET', [
            'api_key' => $this->apiKey,
            'action'  => 'setStatus',
            'id'      => $providerActivationId,
            'status'  => 8
        ]);

        if (!$res['success']) {
            return ['success' => false, 'error' => $res['error']];
        }

        $body = trim($res['body']);
        if ($body === 'ACCESS_CANCEL') {
            return ['success' => true, 'error' => null];
        }

        return ['success' => false, 'error' => 'Provider cancellation error: ' . $body];
    }

    public function finishActivation(string $providerActivationId): array
    {
        // 6 = complete activation
        $res = $this->request($this->endpoint, 'GET', [
            'api_key' => $this->apiKey,
            'action'  => 'setStatus',
            'id'      => $providerActivationId,
            'status'  => 6
        ]);

        return ['success' => $res['success'], 'error' => $res['error'] ?? null];
    }
}

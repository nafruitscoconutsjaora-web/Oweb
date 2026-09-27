<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * Generic REST API Adapter for Modern SMS Verification Platforms
 * ==============================================================================
 */

class GenericRestAdapter extends BaseProvider
{
    private function headers(): array
    {
        return [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
    }

    public function getBalance(): array
    {
        $res = $this->request($this->endpoint . '/balance', 'GET', [], $this->headers());
        if (!$res['success']) {
            return ['success' => false, 'balance' => 0.0, 'currency' => 'USD', 'error' => $res['error']];
        }

        $data = json_decode($res['body'], true);
        return [
            'success'  => true,
            'balance'  => (float)($data['balance'] ?? 0.0),
            'currency' => (string)($data['currency'] ?? 'USD'),
            'error'    => null
        ];
    }

    public function getAvailableNumbers(string $countryCode, string $serviceCode): array
    {
        $res = $this->request($this->endpoint . '/inventory', 'GET', ['country' => $countryCode, 'service' => $serviceCode], $this->headers());
        if (!$res['success']) {
            return ['success' => false, 'count' => 0, 'cost' => 0.0, 'error' => $res['error']];
        }

        $data = json_decode($res['body'], true);
        return [
            'success' => true,
            'count'   => (int)($data['available_count'] ?? 0),
            'cost'    => (float)($data['unit_cost'] ?? 0.0),
            'error'   => null
        ];
    }

    public function requestActivation(string $countryCode, string $serviceCode): array
    {
        $payload = ['country' => $countryCode, 'service' => $serviceCode];
        $res = $this->request($this->endpoint . '/activations/create', 'POST', $payload, $this->headers());

        if (!$res['success']) {
            return ['success' => false, 'error' => $res['error']];
        }

        $data = json_decode($res['body'], true);
        if (!empty($data['id']) && !empty($data['number'])) {
            return [
                'success'                => true,
                'provider_activation_id' => (string)$data['id'],
                'phone_number'           => (string)$data['number'],
                'full_phone_number'      => (string)($data['full_number'] ?? ('+' . ltrim((string)$data['number'], '+'))),
                'cost'                   => (float)($data['cost'] ?? 0.0),
                'error'                  => null
            ];
        }

        return ['success' => false, 'error' => $data['message'] ?? 'Provider error on activation'];
    }

    public function checkStatus(string $providerActivationId): array
    {
        $res = $this->request($this->endpoint . '/activations/' . urlencode($providerActivationId), 'GET', [], $this->headers());
        if (!$res['success']) {
            return ['success' => false, 'status' => 'waiting', 'sms_code' => null, 'full_sms' => null, 'error' => $res['error']];
        }

        $data = json_decode($res['body'], true);
        $status = $data['status'] ?? 'waiting';
        $smsCode = $data['sms_code'] ?? null;
        $smsText = $data['sms_text'] ?? null;

        return [
            'success'  => true,
            'status'   => ($smsCode || $smsText) ? 'sms_received' : $status,
            'sms_code' => $smsCode,
            'full_sms' => $smsText,
            'error'    => null
        ];
    }

    public function cancelActivation(string $providerActivationId): array
    {
        $res = $this->request($this->endpoint . '/activations/' . urlencode($providerActivationId) . '/cancel', 'POST', [], $this->headers());
        return ['success' => $res['success'], 'error' => $res['error'] ?? null];
    }

    public function finishActivation(string $providerActivationId): array
    {
        $res = $this->request($this->endpoint . '/activations/' . urlencode($providerActivationId) . '/complete', 'POST', [], $this->headers());
        return ['success' => $res['success'], 'error' => $res['error'] ?? null];
    }
}

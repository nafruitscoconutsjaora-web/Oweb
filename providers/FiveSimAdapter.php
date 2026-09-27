<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * 5SIM Protocol Provider Adapter
 * ==============================================================================
 */

class FiveSimAdapter extends BaseProvider
{
    private function authHeaders(): array
    {
        return [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json'
        ];
    }

    public function getBalance(): array
    {
        $url = $this->endpoint . '/user/profile';
        $res = $this->request($url, 'GET', [], $this->authHeaders());

        if (!$res['success']) {
            return ['success' => false, 'balance' => 0.0, 'currency' => 'USD', 'error' => $res['error']];
        }

        $data = json_decode($res['body'], true);
        if (is_array($data) && isset($data['balance'])) {
            return [
                'success'  => true,
                'balance'  => (float)$data['balance'],
                'currency' => 'USD',
                'error'    => null
            ];
        }

        return ['success' => false, 'balance' => 0.0, 'currency' => 'USD', 'error' => 'Invalid JSON from 5SIM'];
    }

    public function getAvailableNumbers(string $countryCode, string $serviceCode): array
    {
        $url = $this->endpoint . '/guest/prices';
        $res = $this->request($url, 'GET', ['country' => $countryCode, 'product' => $serviceCode], ['Accept: application/json']);

        if (!$res['success']) {
            return ['success' => false, 'count' => 0, 'cost' => 0.0, 'error' => $res['error']];
        }

        $data = json_decode($res['body'], true);
        if (isset($data[$countryCode][$serviceCode])) {
            $operators = $data[$countryCode][$serviceCode];
            $totalCount = 0;
            $minCost = 9999.0;
            foreach ($operators as $op) {
                if (isset($op['count'])) $totalCount += (int)$op['count'];
                if (isset($op['cost'])) $minCost = min($minCost, (float)$op['cost']);
            }
            return ['success' => true, 'count' => $totalCount, 'cost' => $minCost === 9999.0 ? 0.0 : $minCost, 'error' => null];
        }

        return ['success' => false, 'count' => 0, 'cost' => 0.0, 'error' => 'No price info'];
    }

    public function requestActivation(string $countryCode, string $serviceCode): array
    {
        // 5sim route: /user/buy/activation/{country}/any/{product}
        $url = sprintf('%s/user/buy/activation/%s/any/%s', $this->endpoint, urlencode($countryCode), urlencode($serviceCode));
        $res = $this->request($url, 'GET', [], $this->authHeaders());

        if (!$res['success']) {
            return ['success' => false, 'error' => $res['error']];
        }

        $data = json_decode($res['body'], true);
        if (is_array($data) && isset($data['id'], $data['phone'])) {
            return [
                'success'                => true,
                'provider_activation_id' => (string)$data['id'],
                'phone_number'           => (string)$data['phone'],
                'full_phone_number'      => '+' . ltrim((string)$data['phone'], '+'),
                'cost'                   => (float)($data['price'] ?? 0.0),
                'error'                  => null
            ];
        }

        return ['success' => false, 'error' => $res['body'] ?: 'Purchase rejected by 5SIM'];
    }

    public function checkStatus(string $providerActivationId): array
    {
        $url = sprintf('%s/user/check/%s', $this->endpoint, urlencode($providerActivationId));
        $res = $this->request($url, 'GET', [], $this->authHeaders());

        if (!$res['success']) {
            return ['success' => false, 'status' => 'waiting', 'sms_code' => null, 'full_sms' => null, 'error' => $res['error']];
        }

        $data = json_decode($res['body'], true);
        if (!is_array($data)) {
            return ['success' => false, 'status' => 'waiting', 'sms_code' => null, 'full_sms' => null, 'error' => 'Bad response'];
        }

        $status = $data['status'] ?? 'PENDING';
        if ($status === 'RECEIVED' || !empty($data['sms'])) {
            $smsList = $data['sms'] ?? [];
            $latestSms = end($smsList);
            return [
                'success'  => true,
                'status'   => 'sms_received',
                'sms_code' => $latestSms['code'] ?? null,
                'full_sms' => $latestSms['text'] ?? null,
                'error'    => null
            ];
        }

        if ($status === 'CANCELED' || $status === 'TIMEOUT') {
            return [
                'success'  => true,
                'status'   => 'cancelled',
                'sms_code' => null,
                'full_sms' => null,
                'error'    => null
            ];
        }

        return [
            'success'  => true,
            'status'   => 'waiting_sms',
            'sms_code' => null,
            'full_sms' => null,
            'error'    => null
        ];
    }

    public function cancelActivation(string $providerActivationId): array
    {
        $url = sprintf('%s/user/cancel/%s', $this->endpoint, urlencode($providerActivationId));
        $res = $this->request($url, 'GET', [], $this->authHeaders());
        return ['success' => $res['success'], 'error' => $res['error'] ?? null];
    }

    public function finishActivation(string $providerActivationId): array
    {
        $url = sprintf('%s/user/finish/%s', $this->endpoint, urlencode($providerActivationId));
        $res = $this->request($url, 'GET', [], $this->authHeaders());
        return ['success' => $res['success'], 'error' => $res['error'] ?? null];
    }
}

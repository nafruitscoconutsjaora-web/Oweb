<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * Abstract Base Provider Adapter
 * ==============================================================================
 */

abstract class BaseProvider implements ProviderInterface
{
    protected string $endpoint;
    protected string $apiKey;
    protected string $apiSecret;
    protected int $timeout;
    protected int $retryLimit;

    public function __construct(array $providerConfig)
    {
        $this->endpoint = rtrim((string)($providerConfig['api_endpoint'] ?? ''), '/');
        
        // Decrypt stored keys if encrypted, or use direct value
        $rawKey = (string)($providerConfig['api_key_encrypted'] ?? '');
        $this->apiKey = str_starts_with($rawKey, 'enc:') 
            ? decrypt_secret(substr($rawKey, 4)) 
            : $rawKey;

        $rawSecret = (string)($providerConfig['api_secret_encrypted'] ?? '');
        $this->apiSecret = str_starts_with($rawSecret, 'enc:') 
            ? decrypt_secret(substr($rawSecret, 4)) 
            : $rawSecret;

        $this->timeout = max(5, (int)($providerConfig['timeout_sec'] ?? 15));
        $this->retryLimit = max(1, (int)($providerConfig['retry_limit'] ?? 2));
    }

    /**
     * Execute secure HTTP request with retry logic
     */
    protected function request(
        string $url,
        string $method = 'GET',
        array $params = [],
        array $headers = []
    ): array {
        $attempts = 0;
        $lastError = '';

        while ($attempts < $this->retryLimit) {
            $attempts++;
            $ch = curl_init();

            $optHeaders = $headers;
            $optHeaders[] = 'User-Agent: Verifex-SMS-Platform/1.0';

            $fullUrl = $url;
            if ($method === 'GET' && !empty($params)) {
                $fullUrl .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
            }

            curl_setopt($ch, CURLOPT_URL, $fullUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if (in_array('Content-Type: application/json', $headers, true)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                }
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $optHeaders);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
                return [
                    'success'   => true,
                    'http_code' => $httpCode,
                    'body'      => (string)$response
                ];
            }

            $lastError = $curlError ?: ('HTTP Error ' . $httpCode . ': ' . substr((string)$response, 0, 200));
            usleep(200000); // 200ms delay between retries
        }

        error_log(sprintf('[PROVIDER REQUEST FAILED] URL: %s | Error: %s', $url, $lastError));
        return [
            'success'   => false,
            'http_code' => 0,
            'error'     => $lastError
        ];
    }
}

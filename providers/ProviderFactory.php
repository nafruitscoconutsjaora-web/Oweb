<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * Provider Factory & Routing Gateway
 * ==============================================================================
 */

require_once __DIR__ . '/ProviderInterface.php';
require_once __DIR__ . '/BaseProvider.php';
require_once __DIR__ . '/SmsActivateAdapter.php';
require_once __DIR__ . '/FiveSimAdapter.php';
require_once __DIR__ . '/GenericRestAdapter.php';

class ProviderFactory
{
    /**
     * Instantiate adapter for a specific provider record
     */
    public static function createById(int $providerId): ?ProviderInterface
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT * FROM providers WHERE id = :id AND is_active = 1 LIMIT 1');
            $stmt->execute(['id' => $providerId]);
            $provider = $stmt->fetch();

            if (!$provider) {
                return null;
            }

            return self::instantiateAdapter($provider);
        } catch (Throwable $e) {
            error_log('[PROVIDER FACTORY ERROR] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Instantiate provider adapter from database row
     */
    public static function instantiateAdapter(array $providerRow): ?ProviderInterface
    {
        $className = (string)($providerRow['adapter_class'] ?? 'GenericRestAdapter');

        if (!class_exists($className)) {
            error_log('[PROVIDER ERROR] Class not found: ' . $className);
            return null;
        }

        return new $className($providerRow);
    }
}

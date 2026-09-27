<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * Database Connection Manager (PDO MySQL 8+ Singleton)
 * ==============================================================================
 */

final class Database
{
    private static ?PDO $instance = null;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {}
    private function __clone() {}

    /**
     * Get or initialize the active PDO connection instance
     *
     * @return PDO
     * @throws RuntimeException If database connection fails
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log connection failure details to secure server log
                error_log('[DB CONNECTION ERROR] ' . $e->getMessage());

                // Return sanitized error message without exposing connection parameters
                throw new RuntimeException('Unable to establish secure database connection: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    /**
     * Safely get PDO instance or return null if not connected
     */
    public static function getSafeConnection(): ?PDO
    {
        try {
            return self::getConnection();
        } catch (Throwable $e) {
            error_log('[SAFE DB CONNECTION ERROR] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Reset active connection instance (useful after configuration changes)
     */
    public static function resetConnection(): void
    {
        self::$instance = null;
    }

    /**
     * Check if database connection is alive
     */
    public static function isConnected(): bool
    {
        try {
            $conn = self::getSafeConnection();
            if ($conn === null) {
                return false;
            }
            $conn->query('SELECT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}

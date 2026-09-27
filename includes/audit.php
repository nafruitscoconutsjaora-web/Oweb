<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * Comprehensive Audit Logger
 * ==============================================================================
 */

class AuditLogger
{
    /**
     * Log an administrative or critical security action
     */
    public static function log(
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $actorType = 'admin'
    ): void {
        try {
            $user = current_user();
            $actorId = $user ? (int)$user['id'] : null;
            $actorEmail = $user ? (string)$user['email'] : 'system';
            $ip = get_client_ip();
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

            $db = Database::getConnection();
            $stmt = $db->prepare('
                INSERT INTO audit_logs
                (actor_type, actor_id, actor_email, action, target_type, target_id, old_values, new_values, ip_address, user_agent, created_at)
                VALUES
                (:actor_type, :actor_id, :actor_email, :action, :target_type, :target_id, :old_val, :new_val, :ip, :ua, NOW())
            ');
            $stmt->execute([
                'actor_type'  => $actorType,
                'actor_id'    => $actorId,
                'actor_email' => $actorEmail,
                'action'      => $action,
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'old_val'     => $oldValues ? json_encode($oldValues) : null,
                'new_val'     => $newValues ? json_encode($newValues) : null,
                'ip'          => $ip,
                'ua'          => $userAgent
            ]);
        } catch (Throwable $e) {
            error_log('[AUDIT LOG ERROR] ' . $e->getMessage());
        }
    }
}

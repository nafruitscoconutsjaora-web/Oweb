<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * System & User Notification Engine
 * ==============================================================================
 */

class Notifications
{
    /**
     * Dispatch notification to a specific user
     */
    public static function sendUser(int $userId, string $type, string $title, string $message, ?string $link = null): bool
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('
                INSERT INTO notifications (user_id, is_admin, type, title, message, link, is_read, created_at)
                VALUES (:uid, 0, :type, :title, :msg, :link, 0, NOW())
            ');
            return $stmt->execute([
                'uid'   => $userId,
                'type'  => $type,
                'title' => $title,
                'msg'   => $message,
                'link'  => $link
            ]);
        } catch (Throwable $e) {
            error_log('[NOTIF ERROR] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Dispatch administrative security or operational alert
     */
    public static function sendAdminAlert(string $type, string $title, string $message, ?string $link = null): bool
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('
                INSERT INTO notifications (user_id, is_admin, type, title, message, link, is_read, created_at)
                VALUES (NULL, 1, :type, :title, :msg, :link, 0, NOW())
            ');
            return $stmt->execute([
                'type'  => $type,
                'title' => $title,
                'msg'   => $message,
                'link'  => $link
            ]);
        } catch (Throwable $e) {
            error_log('[ADMIN ALERT ERROR] ' . $e->getMessage());
            return false;
        }
    }
}

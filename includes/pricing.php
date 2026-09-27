<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * Dynamic Server-Side Pricing Engine
 * ==============================================================================
 */

class Pricing
{
    /**
     * Compute authoritative customer price based on active provider cost,
     * margin policies, country/service rules, and user tier discounts.
     *
     * @return array [
     *   'eligible' => bool,
     *   'price' => float,
     *   'provider_cost' => float,
     *   'margin' => float,
     *   'provider_id' => int,
     *   'provider_service_code' => string,
     *   'provider_country_code' => string
     * ]
     */
    public static function calculate(int $countryId, int $serviceId, ?int $userId = null): array
    {
        $db = Database::getConnection();

        // 1. Find healthy provider mapped to both country & service, ordered by priority
        $stmt = $db->prepare('
            SELECT
                p.id AS provider_id,
                p.name AS provider_name,
                ps.provider_service_code,
                ps.provider_cost,
                pc.provider_country_code
            FROM providers p
            JOIN provider_countries pc ON pc.provider_id = p.id AND pc.country_id = :cid AND pc.is_active = 1
            JOIN provider_services ps ON ps.provider_id = p.id AND ps.service_id = :sid AND ps.is_active = 1
            WHERE p.is_active = 1 AND p.health_status != "offline"
            ORDER BY p.priority ASC, ps.provider_cost ASC
            LIMIT 1
        ');
        $stmt->execute(['cid' => $countryId, 'sid' => $serviceId]);
        $route = $stmt->fetch();

        if (!$route) {
            return [
                'eligible' => false,
                'error'    => 'No active provider route currently available for this service in the selected country.'
            ];
        }

        $providerCost = (float)$route['provider_cost'];

        // 2. Fetch highest priority pricing rule
        $ruleStmt = $db->prepare('
            SELECT * FROM pricing_rules
            WHERE is_active = 1
              AND (
                  (country_id = :cid AND service_id = :sid)
                  OR (service_id = :sid AND country_id IS NULL)
                  OR (country_id = :cid AND service_id IS NULL)
                  OR (provider_id = :pid AND country_id IS NULL AND service_id IS NULL)
                  OR (rule_type = "global")
              )
            ORDER BY priority DESC, id DESC
            LIMIT 1
        ');
        $ruleStmt->execute([
            'cid' => $countryId,
            'sid' => $serviceId,
            'pid' => $route['provider_id']
        ]);
        $rule = $ruleStmt->fetch();

        $marginType = $rule['margin_type'] ?? 'percentage';
        $marginValue = isset($rule['margin_value']) ? (float)$rule['margin_value'] : 25.0; // default 25%

        if ($marginType === 'fixed') {
            $margin = $marginValue;
            $basePrice = $providerCost + $margin;
        } else {
            $margin = $providerCost * ($marginValue / 100.0);
            $basePrice = $providerCost + $margin;
        }

        // 3. User tier discount calculation
        $discountAmount = 0.0;
        if ($userId !== null) {
            $groupStmt = $db->prepare('
                SELECT ug.discount_percentage
                FROM users u
                JOIN user_groups ug ON ug.id = u.user_group_id
                WHERE u.id = :uid AND ug.is_active = 1
                LIMIT 1
            ');
            $groupStmt->execute(['uid' => $userId]);
            $discountPct = (float)$groupStmt->fetchColumn();

            if ($discountPct > 0) {
                $discountAmount = $basePrice * ($discountPct / 100.0);
            }
        }

        $finalPrice = max($providerCost, $basePrice - $discountAmount);
        $finalMargin = $finalPrice - $providerCost;

        return [
            'eligible'              => true,
            'price'                 => round($finalPrice, 2),
            'provider_cost'         => round($providerCost, 4),
            'margin'                => round($finalMargin, 4),
            'provider_id'           => (int)$route['provider_id'],
            'provider_name'         => $route['provider_name'],
            'provider_service_code' => $route['provider_service_code'],
            'provider_country_code' => $route['provider_country_code']
        ];
    }
}

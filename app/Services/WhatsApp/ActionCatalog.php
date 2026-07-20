<?php

namespace App\Services\WhatsApp;

use App\Enums\AlertType;
use App\Enums\RecommendedAction;

class ActionCatalog
{
    private const SECRET_KEY = 'whatsapp_button_hmac';

    public static function getAllowedActions(AlertType $alertType): array
    {
        return match ($alertType) {
            AlertType::Fragmentation => [
                'rebuild' => 'REBUILD',
                'reorganize' => 'REORGANIZE',
                'review' => 'REVIEW',
            ],
            AlertType::FillFactor => [
                'rebuild' => 'REBUILD',
                'reorganize' => 'REORGANIZE',
                'review' => 'REVIEW',
            ],
            AlertType::PageSplits => [
                'rebuild' => 'REBUILD',
                'reorganize' => 'REORGANIZE',
                'review' => 'REVIEW',
            ],
            AlertType::StaleStatistics => [
                'stats' => 'UPDATE STATS',
                'review' => 'REVIEW',
            ],
            AlertType::MissingIndex => [
                'review' => 'REVIEW',
                'create_index' => 'CREATE INDEX',
            ],
            AlertType::Inactive => [
                'review' => 'REVIEW',
                'disable_index' => 'DISABLE INDEX',
                'drop_index' => 'DROP INDEX',
            ],
            AlertType::DuplicateIndex => [
                'review' => 'REVIEW',
                'drop_index' => 'DROP INDEX',
            ],
            AlertType::Heap => [
                'review' => 'REVIEW',
                'create_clustered' => 'CREATE CLUSTERED',
            ],
            default => [
                'review' => 'REVIEW',
            ],
        };
    }

    public static function getRecommendedAction(string $actionKey, AlertType $alertType): ?RecommendedAction
    {
        $mapping = match ($alertType) {
            AlertType::Fragmentation => [
                'rebuild' => RecommendedAction::Rebuild,
                'reorganize' => RecommendedAction::Reorganize,
                'review' => RecommendedAction::Review,
            ],
            AlertType::FillFactor => [
                'rebuild' => RecommendedAction::Rebuild,
                'reorganize' => RecommendedAction::Reorganize,
                'review' => RecommendedAction::Review,
            ],
            AlertType::PageSplits => [
                'rebuild' => RecommendedAction::Rebuild,
                'reorganize' => RecommendedAction::Reorganize,
                'review' => RecommendedAction::Review,
            ],
            AlertType::StaleStatistics => [
                'stats' => RecommendedAction::UpdateStatistics,
                'review' => RecommendedAction::Review,
            ],
            AlertType::MissingIndex => [
                'review' => RecommendedAction::Review,
                'create_index' => RecommendedAction::CreateIndex,
            ],
            AlertType::Inactive => [
                'review' => RecommendedAction::Review,
                'disable_index' => RecommendedAction::DisableIndex,
                'drop_index' => RecommendedAction::DropIndex,
            ],
            AlertType::DuplicateIndex => [
                'review' => RecommendedAction::Review,
                'drop_index' => RecommendedAction::DropIndex,
            ],
            AlertType::Heap => [
                'review' => RecommendedAction::Review,
                'create_clustered' => RecommendedAction::CreateClustered,
            ],
            default => [
                'review' => RecommendedAction::Review,
            ],
        };

        return $mapping[$actionKey] ?? null;
    }

    public static function requiresDoubleConfirmation(string $actionKey, AlertType $alertType): bool
    {
        $action = self::getRecommendedAction($actionKey, $alertType);

        return $action?->requiresDoubleConfirmation() ?? false;
    }

    /**
     * Build a signed button ID: action|alertId|HMAC
     * The HMAC prevents forged button IDs from being accepted.
     */
    public static function makeButtonId(string $actionKey, int $alertId): string
    {
        $payload = "{$actionKey}|{$alertId}";
        $signature = self::sign($payload);

        return "{$actionKey}_{$alertId}_{$signature}";
    }

    /**
     * Parse and verify a button ID. Returns ['action' => ..., 'alert_id' => ...] or null.
     */
    public static function parseButtonId(string $buttonId): ?array
    {
        $parts = explode('_', $buttonId);
        if (count($parts) < 3) {
            return null;
        }

        // action_key may contain underscores (e.g. "create_index"), so we need to
        // find the signature (last part) and alert_id (second to last part).
        $signature = array_pop($parts);
        $alertId = (int) array_pop($parts);
        $action = implode('_', $parts);

        if ($action === '' || $alertId <= 0) {
            return null;
        }

        // Verify HMAC
        $payload = "{$action}|{$alertId}";
        $expected = self::sign($payload);

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        return [
            'action' => $action,
            'alert_id' => $alertId,
        ];
    }

    private static function sign(string $payload): string
    {
        $secret = config('services.whatsapp.app_secret', '');

        if ($secret === '') {
            // In tests or local dev without app_secret, use a deterministic fallback
            // so tests are reproducible. NOT used in production.
            $secret = env('APP_KEY', 'insecure-dev-fallback');
        }

        // Use first 10 chars of HMAC for short button IDs (still collision-resistant enough
        // for button IDs which are validated server-side against the real alert state)
        return substr(hash_hmac('sha256', $payload, $secret), 0, 10);
    }
}

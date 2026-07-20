<?php

namespace App\Services\WhatsApp;

use App\Enums\AlertType;
use App\Enums\RecommendedAction;

class ActionCatalog
{
    /**
     * Returns allowed button actions for a given alert type.
     * Format: ['action_key' => 'Button Title']
     */
    public static function getAllowedActions(AlertType $alertType): array
    {
        return match ($alertType) {
            AlertType::Fragmentation => [
                'rebuild'       => 'REBUILD',
                'reorganize'    => 'REORGANIZE',
                'review'        => 'REVIEW',
            ],
            AlertType::FillFactor => [
                'rebuild'       => 'REBUILD',
                'reorganize'    => 'REORGANIZE',
                'review'        => 'REVIEW',
            ],
            AlertType::PageSplits => [
                'rebuild'       => 'REBUILD',
                'reorganize'    => 'REORGANIZE',
                'review'        => 'REVIEW',
            ],
            AlertType::StaleStatistics => [
                'stats'         => 'UPDATE STATS',
                'review'        => 'REVIEW',
            ],
            AlertType::MissingIndex => [
                'review'        => 'REVIEW',
                'create_index'  => 'CREATE INDEX',
            ],
            AlertType::Inactive => [
                'review'        => 'REVIEW',
                'disable_index' => 'DISABLE INDEX',
                'drop_index'    => 'DROP INDEX',
            ],
            AlertType::DuplicateIndex => [
                'review'        => 'REVIEW',
                'drop_index'    => 'DROP INDEX',
            ],
            AlertType::Heap => [
                'review'            => 'REVIEW',
                'create_clustered'  => 'CREATE CLUSTERED',
            ],
            default => [
                'review' => 'REVIEW',
            ],
        };
    }

    /**
     * Returns the RecommendedAction enum for a given action key and alert type.
     */
    public static function getRecommendedAction(string $actionKey, AlertType $alertType): ?RecommendedAction
    {
        $mapping = match ($alertType) {
            AlertType::Fragmentation => [
                'rebuild'       => RecommendedAction::Rebuild,
                'reorganize'    => RecommendedAction::Reorganize,
                'review'        => RecommendedAction::Review,
            ],
            AlertType::FillFactor => [
                'rebuild'       => RecommendedAction::Rebuild,
                'reorganize'    => RecommendedAction::Reorganize,
                'review'        => RecommendedAction::Review,
            ],
            AlertType::PageSplits => [
                'rebuild'       => RecommendedAction::Rebuild,
                'reorganize'    => RecommendedAction::Reorganize,
                'review'        => RecommendedAction::Review,
            ],
            AlertType::StaleStatistics => [
                'stats'         => RecommendedAction::UpdateStatistics,
                'review'        => RecommendedAction::Review,
            ],
            AlertType::MissingIndex => [
                'review'        => RecommendedAction::Review,
                'create_index'  => RecommendedAction::CreateIndex,
            ],
            AlertType::Inactive => [
                'review'        => RecommendedAction::Review,
                'disable_index' => RecommendedAction::DisableIndex,
                'drop_index'    => RecommendedAction::DropIndex,
            ],
            AlertType::DuplicateIndex => [
                'review'        => RecommendedAction::Review,
                'drop_index'    => RecommendedAction::DropIndex,
            ],
            AlertType::Heap => [
                'review'            => RecommendedAction::Review,
                'create_clustered'  => RecommendedAction::CreateClustered,
            ],
            default => [
                'review' => RecommendedAction::Review,
            ],
        };

        return $mapping[$actionKey] ?? null;
    }

    /**
     * Check if action requires double confirmation (high/very_high risk)
     */
    public static function requiresDoubleConfirmation(string $actionKey, AlertType $alertType): bool
    {
        $action = self::getRecommendedAction($actionKey, $alertType);
        return $action?->requiresDoubleConfirmation() ?? false;
    }

    /**
     * Get button payload ID format: action_alertId
     */
    public static function makeButtonId(string $actionKey, int $alertId): string
    {
        return "{$actionKey}_{$alertId}";
    }

    /**
     * Parse button ID back into action and alert ID
     */
    public static function parseButtonId(string $buttonId): ?array
    {
        $parts = explode('_', $buttonId, 2);
        if (count($parts) !== 2) {
            return null;
        }

        return [
            'action' => $parts[0],
            'alert_id' => (int) $parts[1],
        ];
    }
}
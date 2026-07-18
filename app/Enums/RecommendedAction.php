<?php

namespace App\Enums;

enum RecommendedAction: string
{
    case Rebuild = 'REBUILD';
    case Reorganize = 'REORGANIZE';
    case UpdateStatistics = 'UPDATE STATISTICS';
    case CreateIndex = 'CREATE INDEX';
    case DisableIndex = 'DISABLE INDEX';
    case DropIndex = 'DROP INDEX';
    case CreateClustered = 'CREATE CLUSTERED';
    case Review = 'REVIEW';
    case Ignore = 'IGNORE';

    public static function fromFragmentation(float $fragmentationPercent, float $warningThreshold, float $criticalThreshold): self
    {
        return match (true) {
            $fragmentationPercent >= $criticalThreshold => self::Rebuild,
            $fragmentationPercent >= $warningThreshold => self::Reorganize,
            default => self::Ignore,
        };
    }

    /**
     * Nivel de riesgo según la tabla de políticas de la sección 8 del plan v2.
     * Determina si la acción requiere vista previa y doble confirmación.
     */
    public function riskLevel(): string
    {
        return match ($this) {
            self::Ignore, self::Review => 'none',
            self::Reorganize, self::UpdateStatistics => 'medium',
            self::Rebuild => 'high',
            self::CreateIndex, self::DisableIndex => 'high_risk',
            self::DropIndex, self::CreateClustered => 'very_high_risk',
        };
    }

    public function requiresDoubleConfirmation(): bool
    {
        return in_array($this->riskLevel(), ['high_risk', 'very_high_risk'], true);
    }
}

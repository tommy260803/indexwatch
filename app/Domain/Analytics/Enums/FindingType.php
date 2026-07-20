<?php

namespace App\Domain\Analytics\Enums;

enum FindingType: string
{
    case MissingIndex = 'missing_index';
    case UnusedIndex = 'unused_index';
    case DuplicateIndex = 'duplicate_index';
    case Heap = 'heap';
    case FillFactor = 'fill_factor';
    case PageSplits = 'page_splits';
    case Fragmentation = 'fragmentation';
    case StaleStatistics = 'stale_statistics';

    public function label(): string
    {
        return match ($this) {
            self::MissingIndex => 'Índice faltante',
            self::UnusedIndex => 'Índice no usado',
            self::DuplicateIndex => 'Índice duplicado',
            self::Heap => 'Tabla Heap',
            self::FillFactor => 'Fill factor óptimo',
            self::PageSplits => 'Page splits excesivos',
            self::Fragmentation => 'Fragmentación',
            self::StaleStatistics => 'Estadísticas obsoletas',
        };
    }

    public function toAlertType(): \App\Enums\AlertType
    {
        return match ($this) {
            self::MissingIndex => \App\Enums\AlertType::MissingIndex,
            self::UnusedIndex => \App\Enums\AlertType::Inactive,
            self::DuplicateIndex => \App\Enums\AlertType::DuplicateIndex,
            self::Heap => \App\Enums\AlertType::Heap,
            self::FillFactor => \App\Enums\AlertType::FillFactor,
            self::PageSplits => \App\Enums\AlertType::PageSplits,
            self::Fragmentation => \App\Enums\AlertType::Fragmentation,
            self::StaleStatistics => \App\Enums\AlertType::StaleStatistics,
        };
    }
}
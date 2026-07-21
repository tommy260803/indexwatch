<?php

namespace App\Services\Monitoring;

use App\Domain\Monitoring\DTO\HealthScoreResult;

class HealthScoreService
{
    public function calculate(
        int $criticalIndexes,
        int $staleStatistics,
        int $sustainedPageSplitIndexes,
    ): HealthScoreResult {
        // El score parte de 100 y se descuenta por tipos de riesgo distintos.
        // Cada tipo tiene un cap para que una sola categoría no arruine toda la lectura.
        $settings = config('indexwatch.health_score');
        $criticalDeduction = min(
            $criticalIndexes * $settings['critical_index_penalty'],
            $settings['critical_index_cap'],
        );
        $statisticsDeduction = min(
            $staleStatistics * $settings['stale_statistics_penalty'],
            $settings['stale_statistics_cap'],
        );
        $pageSplitDeduction = min(
            $sustainedPageSplitIndexes * $settings['sustained_page_splits_penalty'],
            $settings['sustained_page_splits_cap'],
        );
        $totalDeduction = $criticalDeduction + $statisticsDeduction + $pageSplitDeduction;

        // La versión del algoritmo queda persistida para poder comparar cambios en el futuro.
        return new HealthScoreResult(
            score: max(0, 100 - $totalDeduction),
            version: $settings['version'],
            details: [
                'complete' => true,
                'critical_indexes' => [
                    'count' => $criticalIndexes,
                    'deduction' => $criticalDeduction,
                ],
                'stale_statistics' => [
                    'count' => $staleStatistics,
                    'deduction' => $statisticsDeduction,
                ],
                'sustained_page_splits' => [
                    'count' => $sustainedPageSplitIndexes,
                    'deduction' => $pageSplitDeduction,
                ],
                'heaps' => [
                    'count' => 0,
                    'deduction' => 0,
                    'note' => 'Heap analysis is supplied by the advanced analytics domain.',
                ],
                'total_deduction' => $totalDeduction,
            ],
        );
    }

    /** @param list<string> $unavailableComponents */
    public function incomplete(array $unavailableComponents): HealthScoreResult
    {
        // Cuando faltan baselines, preferimos decir "no calculado" antes que
        // dar un número que parezca exacto pero no lo sea.
        return new HealthScoreResult(
            score: null,
            version: config('indexwatch.health_score.version'),
            details: [
                'complete' => false,
                'unavailable_components' => $unavailableComponents,
                'note' => 'The score was not calculated because required metrics have no previous baseline.',
            ],
        );
    }
}

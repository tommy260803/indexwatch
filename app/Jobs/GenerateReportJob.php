<?php

namespace App\Jobs;

use App\Models\GeneratedReport;
use App\Models\User;
use App\Services\Reports\ReportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $reportId,
    ) {}

    public function handle(ReportExportService $exportService): void
    {
        $report = GeneratedReport::findOrFail($this->reportId);

        Log::info('Generando reporte IndexWatch', ['report_id' => $report->id]);

        $exportService->generate($report);

        $report->refresh();

        if ($report->status === 'completed') {
            Log::info('Reporte completado', [
                'report_id' => $report->id,
                'file_path' => $report->file_path,
            ]);
        } else {
            Log::error('Reporte falló', [
                'report_id' => $report->id,
                'error' => $report->metadata['error'] ?? 'Unknown',
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $report = GeneratedReport::find($this->reportId);

        if ($report) {
            $report->forceFill([
                'status' => 'failed',
                'metadata' => array_merge($report->metadata ?? [], [
                    'error' => $exception->getMessage(),
                    'failed_at' => now()->toISOString(),
                ]),
            ])->save();
        }

        Log::error('GenerateReportJob failed', [
            'report_id' => $this->reportId,
            'error' => $exception->getMessage(),
        ]);
    }
}
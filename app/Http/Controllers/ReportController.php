<?php

namespace App\Http\Controllers;

use App\Enums\ReportFormat;
use App\Jobs\GenerateReportJob;
use App\Models\GeneratedReport;
use App\Services\Reports\ReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_id' => 'integer|exists:servers,id',
            'filters' => 'array',
            'format' => 'required|in:pdf,xlsx',
        ]);

        $report = GeneratedReport::create([
            'requested_by_user_id' => $request->user()->id,
            'server_id' => $validated['server_id'] ?? null,
            'filters' => $validated['filters'] ?? [],
            'format' => $validated['format'],
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        GenerateReportJob::dispatch($report->id);

        return response()->json(['data' => $report->fresh()], 201);
    }

    public function show(GeneratedReport $report): JsonResponse
    {
        return response()->json(['data' => $report]);
    }

    public function download(GeneratedReport $report, ReportExportService $exportService): BinaryFileResponse|JsonResponse
    {
        if ($report->status !== 'completed') {
            return response()->json(['message' => 'Report not ready'], 409);
        }

        return $exportService->download($report);
    }
}
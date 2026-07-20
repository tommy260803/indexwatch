<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte IndexWatch - {{ $data['period']['start'] }} a {{ $data['period']['end'] }}</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { font-size: 10px; line-height: 1.4; color: #333; }
        .header { text-align: center; padding: 20px 0; border-bottom: 3px solid #2563eb; margin-bottom: 20px; }
        .header h1 { font-size: 24px; color: #1e40af; margin-bottom: 5px; }
        .header .subtitle { font-size: 12px; color: #64748b; }
        .header .meta { font-size: 10px; color: #94a3b8; margin-top: 5px; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        .summary-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; text-align: center; }
        .summary-card .value { font-size: 28px; font-weight: bold; color: #1e293b; }
        .summary-card .label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-card.critical .value { color: #dc2626; }
        .summary-card.warning .value { color: #ea580c; }
        .summary-card.info .value { color: #2563eb; }
        .summary-card.ok .value { color: #16a34a; }
        section { margin-bottom: 30px; page-break-inside: avoid; }
        h2 { font-size: 14px; color: #1e40af; border-bottom: 2px solid #2563eb; padding-bottom: 5px; margin-bottom: 15px; }
        h3 { font-size: 12px; color: #334155; margin: 15px 0 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 8px; }
        th, td { border: 1px solid #e2e8f0; padding: 4px 6px; text-align: left; }
        th { background: #f1f5f9; font-weight: 600; color: #334155; }
        tr:nth-child(even) { background: #f8fafc; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .badge-critical { background: #fef2f2; color: #dc2626; }
        .badge-warning { background: #fff7ed; color: #ea580c; }
        .badge-info { background: #eff6ff; color: #2563eb; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-approved { background: #dcfce7; color: #16a34a; }
        .badge-scheduled { background: #e0e7ff; color: #4f46e5; }
        .badge-running { background: #fce7f3; color: #db2777; }
        .badge-succeeded { background: #dcfce7; color: #16a34a; }
        .badge-failed { background: #fef2f2; color: #dc2626; }
        .badge-dismissed { background: #f1f5f9; color: #64748b; }
        .server-section { margin-bottom: 25px; padding: 15px; background: #fafafa; border: 1px solid #e2e8f0; border-radius: 8px; }
        .server-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .server-name { font-size: 13px; font-weight: 600; color: #1e293b; }
        .server-meta { font-size: 9px; color: #64748b; }
        .health-score { font-size: 18px; font-weight: bold; padding: 5px 12px; border-radius: 20px; }
        .health-score.good { background: #dcfce7; color: #16a34a; }
        .health-score.fair { background: #fef3c7; color: #d97706; }
        .health-score.poor { background: #fef2f2; color: #dc2626; }
        .alerts-table th, .indexes-table th { font-size: 7px; }
        .no-data { text-align: center; color: #94a3b8; font-style: italic; padding: 20px; }
        .footer { text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; margin-top: 30px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>IndexWatch - Reporte de Monitoreo</h1>
        <div class="subtitle">Análisis de fragmentación, estadísticas y salud de índices SQL Server</div>
        <div class="meta">
            Generado: {{ now()->format('d/m/Y H:i:s') }} |
            Período: {{ $data['period']['start'] }} - {{ $data['period']['end'] }} |
            Servidores: {{ $data['summary']['total_servers'] }}
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card ok">
            <div class="value">{{ $data['summary']['total_servers'] }}</div>
            <div class="label">Servidores Monitoreados</div>
        </div>
        <div class="summary-card critical">
            <div class="value">{{ $data['summary']['critical_alerts'] }}</div>
            <div class="label">Alertas Críticas</div>
        </div>
        <div class="summary-card warning">
            <div class="value">{{ $data['summary']['warning_alerts'] }}</div>
            <div class="label">Alertas Advertencia</div>
        </div>
        <div class="summary-card info">
            <div class="value">{{ $data['summary']['info_alerts'] }}</div>
            <div class="label">Alertas Informativas</div>
        </div>
    </div>

    @foreach ($data['servers'] as $serverData)
        <div class="server-section">
            <div class="server-header">
                <div>
                    <div class="server-name">{{ $serverData['server']['name'] }}</div>
                    <div class="server-meta">
                        {{ $serverData['server']['host'] }} / {{ $serverData['server']['database_name'] }} |
                        Último scan: {{ $serverData['server']['last_scanned_at'] ? \Carbon\Carbon::parse($serverData['server']['last_scanned_at'])->format('d/m/Y H:i') : 'Nunca' }} |
                        Estado: {{ $serverData['server']['last_scan_status'] }}
                    </div>
                </div>
                @if ($serverData['server']['health_score'] !== null)
                    <div class="health-score {{ $serverData['server']['health_score'] >= 80 ? 'good' : ($serverData['server']['health_score'] >= 50 ? 'fair' : 'poor') }}">
                        Health Score: {{ $serverData['server']['health_score'] }}/100
                    </div>
                @endif
            </div>

            @if (!empty($serverData['alerts']))
                <h3>Alertas ({{ count($serverData['alerts']) }})</h3>
                <table class="alerts-table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Severidad</th>
                            <th>Estado</th>
                            <th>Asunto</th>
                            <th>Acción</th>
                            <th>Frag. %</th>
                            <th>Creado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($serverData['alerts'] as $alert)
                            <tr>
                                <td>{{ $alert['type_label'] ?? $alert['type'] }}</td>
                                <td><span class="badge badge-{{ $alert['severity'] }}">{{ $alert['severity_label'] ?? ucfirst($alert['severity']) }}</span></td>
                                <td><span class="badge badge-{{ $alert['status'] }}">{{ $alert['status_label'] ?? ucfirst($alert['status']) }}</span></td>
                                <td>{{ $alert['subject_display'] }}</td>
                                <td>{{ $alert['recommended_action'] }}</td>
                                <td>{{ $alert['fragmentation_percent'] ? number_format($alert['fragmentation_percent'], 1) . '%' : '—' }}</td>
                                <td>{{ \Carbon\Carbon::parse($alert['created_at'])->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="no-data">Sin alertas en el período</p>
            @endif

            @if (!empty($serverData['indexes']))
                <h3>Índices Principales</h3>
                <table class="indexes-table">
                    <thead>
                        <tr>
                            <th>Esquema.Tabla.Índice</th>
                            <th>Tipo</th>
                            <th>PK/Único</th>
                            <th>Frag. %</th>
                            <th>Tamaño (MB)</th>
                            <th>Fill Factor</th>
                            <th>Seeks</th>
                            <th>Scans</th>
                            <th>Lookups</th>
                            <th>Updates</th>
                            <th>Ratio L/E</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_slice($serverData['indexes'], 0, 30) as $index)
                            <tr>
                                <td>{{ $index['schema_name'] }}.{{ $index['table_name'] }}.{{ $index['index_name'] }}</td>
                                <td>{{ $index['type'] }}</td>
                                <td>
                                    @if ($index['is_primary_key']) PK
                                    @elseif ($index['is_unique']) UNIQUE
                                    @else — @endif
                                </td>
                                <td>{{ $index['fragmentation_percent'] ? number_format($index['fragmentation_percent'], 1) . '%' : '—' }}</td>
                                <td>{{ $index['size_mb'] ? number_format($index['size_mb'], 1) : '—' }}</td>
                                <td>{{ $index['fill_factor'] ?? '—' }}</td>
                                <td>{{ number_format($index['user_seeks']) }}</td>
                                <td>{{ number_format($index['user_scans']) }}</td>
                                <td>{{ number_format($index['user_lookups']) }}</td>
                                <td>{{ number_format($index['user_updates']) }}</td>
                                <td>{{ $index['read_write_ratio'] ? number_format($index['read_write_ratio'], 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (count($serverData['indexes']) > 30)
                    <p class="no-data">Mostrando 30 de {{ count($serverData['indexes']) }} índices</p>
                @endif
            @endif
        </div>
    @endforeach

    <div class="footer">
        <p>IndexWatch v2 - Reporte generado automáticamente</p>
        <p>Este reporte contiene información confidencial. No compartir sin autorización.</p>
        <p>Limitaciones: Los datos provienen de DMVs de SQL Server que se reinician al reiniciar la instancia. Las recomendaciones son basadas en métricas observadas y requieren validación de DBA antes de ejecución.</p>
    </div>
</body>
</html>
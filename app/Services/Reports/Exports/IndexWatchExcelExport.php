<?php

namespace App\Services\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IndexWatchExcelExport implements WithMultipleSheets
{
    public function __construct(private readonly array $data) {}

    public function sheets(): array
    {
        return [
            new ResumenSheet($this->data),
            new FragmentacionSheet($this->data),
            new EstadisticasSheet($this->data),
            new AlertasSheet($this->data),
            new MantenimientoSheet($this->data),
            new AuditoriaSheet($this->data),
        ];
    }
}

class ResumenSheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    public function __construct(private readonly array $data) {}

    public function array(): array
    {
        $meta = $this->data['meta'] ?? [];
        $summary = $this->data['executive_summary'] ?? [];

        return [
            ['IndexWatch - Reporte de Salud de Índices'],
            ['Generado:', $meta['generated_at'] ?? ''],
            ['Generado por:', $meta['generated_by'] ?? ''],
            ['Período:', ($meta['period']['start'] ?? '') . ' - ' . ($meta['period']['end'] ?? '')],
            ['Versión algoritmo:', $meta['algorithm_version'] ?? '1.0'],
            [],
            ['KPIs Principales'],
            ['Servidores monitoreados', $summary['servers_count'] ?? 0],
            ['Total índices', $summary['total_indexes'] ?? 0],
            ['Tamaño total (MB)', $summary['total_size_mb'] ?? 0],
            ['Fragmentación crítica', $summary['critical_fragmentation'] ?? 0],
            ['Fragmentación advertencia', $summary['warning_fragmentation'] ?? 0],
            ['Índices saludables', $summary['healthy_indexes'] ?? 0],
            ['Alertas en período', $summary['alerts_count'] ?? 0],
            ['Acciones mantenimiento', $summary['maintenance_actions_count'] ?? 0],
            ['Estadísticas obsoletas', $summary['stale_statistics_count'] ?? 0],
        ];
    }

    public function headings(): array
    {
        return ['Concepto', 'Valor'];
    }

    public function title(): string
    {
        return 'Resumen';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A7:A19')->getFont()->setBold(true);
        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 35, 'B' => 25];
    }
}

class FragmentacionSheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    public function __construct(private readonly array $data) {}

    public function array(): array
    {
        $rows = [];
        foreach ($this->data['fragmentation'] ?? [] as $idx) {
            $rows[] = [
                $idx['server'] ?? '',
                $idx['schema'] ?? '',
                $idx['table'] ?? '',
                $idx['index'] ?? '',
                $idx['type'] ?? '',
                $idx['fragmentation'] ?? 0,
                $idx['size_mb'] ?? 0,
                $idx['page_count'] ?? 0,
                $idx['fill_factor'] ?? 0,
                $idx['reads'] ?? 0,
                $idx['writes'] ?? 0,
                $idx['last_checked'] ?? '',
            ];
        }
        return $rows;
    }

    public function headings(): array
    {
        return ['Servidor', 'Esquema', 'Tabla', 'Índice', 'Tipo', 'Fragmentación %', 'Tamaño MB', 'Páginas', 'Fill Factor', 'Lecturas', 'Escrituras', 'Última verificación'];
    }

    public function title(): string
    {
        return 'Fragmentación';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 15, 'C' => 25, 'D' => 30, 'E' => 15, 'F' => 15, 'G' => 12, 'H' => 10, 'I' => 12, 'J' => 12, 'K' => 12, 'L' => 20];
    }
}

class EstadisticasSheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    public function __construct(private readonly array $data) {}

    public function array(): array
    {
        $rows = [];
        foreach ($this->data['statistics'] ?? [] as $stat) {
            $rows[] = [
                $stat['server'] ?? '',
                $stat['schema'] ?? '',
                $stat['table'] ?? '',
                $stat['stats_name'] ?? '',
                $stat['row_count'] ?? 0,
                $stat['modification_count'] ?? 0,
                $stat['modification_percent'] ?? 0,
                $stat['last_updated'] ?? '',
            ];
        }
        return $rows;
    }

    public function headings(): array
    {
        return ['Servidor', 'Esquema', 'Tabla', 'Estadísticas', 'Filas', 'Modificaciones', '% Modificación', 'Última actualización'];
    }

    public function title(): string
    {
        return 'Estadísticas';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 15, 'C' => 25, 'D' => 30, 'E' => 15, 'F' => 15, 'G' => 15, 'H' => 20];
    }
}

class AlertasSheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    public function __construct(private readonly array $data) {}

    public function array(): array
    {
        $rows = [];
        foreach ($this->data['alerts'] ?? [] as $alert) {
            $rows[] = [
                $alert['server'] ?? '',
                $alert['type'] ?? '',
                $alert['severity'] ?? '',
                $alert['status'] ?? '',
                $alert['subject'] ?? '',
                $alert['action'] ?? '',
                $alert['created_at'] ?? '',
                $alert['resolved_at'] ?? '',
            ];
        }
        return $rows;
    }

    public function headings(): array
    {
        return ['Servidor', 'Tipo', 'Severidad', 'Estado', 'Asunto', 'Acción', 'Creado', 'Resuelto'];
    }

    public function title(): string
    {
        return 'Alertas';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 20, 'C' => 15, 'D' => 15, 'E' => 35, 'F' => 15, 'G' => 20, 'H' => 20];
    }
}

class MantenimientoSheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    public function __construct(private readonly array $data) {}

    public function array(): array
    {
        $rows = [];
        foreach ($this->data['maintenance'] ?? [] as $action) {
            $rows[] = [
                $action['server'] ?? '',
                $action['type'] ?? '',
                $action['status'] ?? '',
                $action['scheduled_for'] ?? '',
                $action['started_at'] ?? '',
                $action['finished_at'] ?? '',
                $action['result'] ?? '',
                $action['error'] ?? '',
            ];
        }
        return $rows;
    }

    public function headings(): array
    {
        return ['Servidor', 'Tipo', 'Estado', 'Programado', 'Iniciado', 'Finalizado', 'Resultado', 'Error'];
    }

    public function title(): string
    {
        return 'Mantenimiento';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 15, 'C' => 15, 'D' => 20, 'E' => 20, 'F' => 20, 'G' => 15, 'H' => 25];
    }
}

class AuditoriaSheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    public function __construct(private readonly array $data) {}

    public function array(): array
    {
        $rows = [];
        foreach ($this->data['audit'] ?? [] as $log) {
            $rows[] = [
                $log['server'] ?? '',
                $log['actor'] ?? '',
                $log['source'] ?? '',
                $log['action'] ?? '',
                $log['status'] ?? '',
                $log['description'] ?? '',
                $log['created_at'] ?? '',
            ];
        }
        return $rows;
    }

    public function headings(): array
    {
        return ['Servidor', 'Actor', 'Fuente', 'Acción', 'Estado', 'Descripción', 'Fecha/Hora'];
    }

    public function title(): string
    {
        return 'Auditoría';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 20, 'C' => 15, 'D' => 15, 'E' => 15, 'F' => 40, 'G' => 20];
    }
}
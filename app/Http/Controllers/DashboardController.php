<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Index;
use App\Models\Alert;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }

    public function data()
    {
        $indexes = Index::all();
        
        $total = $indexes->count();
        $critical = $indexes->where('fragmentation_percent', '>', 30)->count();
        $warning = $indexes->where('fragmentation_percent', '>=', 5)->where('fragmentation_percent', '<=', 30)->count();
        $ok = $indexes->where('fragmentation_percent', '<', 5)->count();

        $alerts = Alert::with('index')->orderBy('created_at', 'desc')->take(5)->get()->map(function($alert) {
            $statusStr = '';
            if ($alert->status === 'resolved') {
                $statusStr = 'OK';
            } elseif ($alert->severity === 'critical') {
                $statusStr = 'CRITICAL';
            } else {
                $statusStr = 'WARNING';
            }
            
            $text = '';
            if ($alert->status === 'resolved') {
                $text = "{$alert->action_taken} completado en <code>{$alert->index->index_name}</code> · <code>{$alert->index->table_name}</code>. Fragmentación actual: {$alert->index->fragmentation_percent}%.";
            } else {
                $text = "Índice <code>{$alert->index->index_name}</code> en <code>{$alert->index->table_name}</code> alcanzó {$alert->index->fragmentation_percent}% de fragmentación.";
            }

            return [
                'status' => $statusStr,
                'text' => $text,
                'time_ago' => $alert->created_at->diffForHumans()
            ];
        });

        $indexesList = $indexes->map(function($idx) {
            $action = 'OK';
            if ($idx->fragmentation_percent > 30) {
                $action = 'REBUILD';
            } elseif ($idx->fragmentation_percent >= 5) {
                $action = 'REORGANIZE';
            }

            return [
                'table' => 'DemoDB.dbo.' . $idx->table_name,
                'index' => $idx->index_name,
                'frag' => (float) $idx->fragmentation_percent,
                'size' => $idx->size_mb,
                'lastReorg' => $idx->last_used_at ? $idx->last_used_at->format('d/m/Y') : 'N/A',
                'action' => $action
            ];
        });

        return response()->json([
            'kpis' => [
                'total' => $total,
                'critical' => $critical,
                'warning' => $warning,
                'ok' => $ok
            ],
            'alerts' => $alerts,
            'indexes' => $indexesList
        ]);
    }
}

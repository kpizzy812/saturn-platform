<?php

use App\Models\AiModelPricing;
use App\Models\AiUsageLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/ai-usage', function () {
    // Get stats for different periods
    $stats7d = AiUsageLog::getGlobalStats('7d');
    $stats30d = AiUsageLog::getGlobalStats('30d');
    $stats90d = AiUsageLog::getGlobalStats('90d');

    // By provider (last 30 days)
    $byProvider = AiUsageLog::where('created_at', '>=', now()->subDays(30))
        ->selectRaw('provider, COUNT(*) as count, SUM(cost_usd) as total_cost')
        ->groupBy('provider')
        ->get()
        ->keyBy('provider')
        ->toArray();

    // By operation (last 30 days)
    $byOperation = AiUsageLog::where('created_at', '>=', now()->subDays(30))
        ->selectRaw('operation, COUNT(*) as count, SUM(cost_usd) as total_cost, SUM(input_tokens + output_tokens) as total_tokens')
        ->groupBy('operation')
        ->get()
        ->keyBy('operation')
        ->toArray();

    // Daily usage (last 30 days)
    $dailyUsage = AiUsageLog::where('created_at', '>=', now()->subDays(30))
        ->selectRaw('DATE(created_at) as date, COUNT(*) as requests, SUM(cost_usd) as cost, SUM(input_tokens + output_tokens) as tokens')
        ->groupBy(DB::raw('DATE(created_at)'))
        ->orderBy('date')
        ->get()
        ->map(fn ($row) => [
            'date' => $row->date,
            'requests' => (int) $row->requests,
            'cost' => (float) $row->cost,
            'tokens' => (int) $row->tokens,
        ])
        ->toArray();

    // Top teams (last 30 days)
    $topTeams = AiUsageLog::where('ai_usage_logs.created_at', '>=', now()->subDays(30))
        ->join('teams', 'ai_usage_logs.team_id', '=', 'teams.id')
        ->selectRaw('ai_usage_logs.team_id, teams.name as team_name, COUNT(*) as total_requests, SUM(cost_usd) as total_cost')
        ->groupBy('ai_usage_logs.team_id', 'teams.name')
        ->orderByDesc('total_cost')
        ->limit(10)
        ->get()
        ->map(fn ($row) => [
            'team_id' => $row->team_id,
            'team_name' => $row->team_name,
            'total_requests' => (int) $row->total_requests,
            'total_cost' => (float) $row->total_cost,
        ])
        ->toArray();

    // Model pricing grouped by provider
    $modelPricing = [];
    $pricing = AiModelPricing::where('is_active', true)
        ->orderBy('provider')
        ->orderBy('model_name')
        ->get();

    foreach ($pricing as $model) {
        if (! isset($modelPricing[$model->provider])) {
            $modelPricing[$model->provider] = [];
        }
        $modelPricing[$model->provider][] = [
            'provider' => $model->provider,
            'model_id' => $model->model_id,
            'model_name' => $model->model_name,
            'input_price_per_1m' => (float) $model->input_price_per_1m,
            'output_price_per_1m' => (float) $model->output_price_per_1m,
            'context_window' => $model->context_window,
        ];
    }

    return Inertia::render('Admin/AiUsage/Index', [
        'stats7d' => $stats7d,
        'stats30d' => $stats30d,
        'stats90d' => $stats90d,
        'byProvider' => $byProvider,
        'byOperation' => $byOperation,
        'dailyUsage' => $dailyUsage,
        'topTeams' => $topTeams,
        'modelPricing' => $modelPricing,
        'period' => '30d',
    ]);
})->name('admin.ai-usage.index');

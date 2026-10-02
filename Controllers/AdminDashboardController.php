<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        $startDate = today()->subDays(6)->toDateString();
        $metrics = DB::table('site_metrics')
            ->where('metric_date', '>=', $startDate)
            ->select('event', 'target', DB::raw('SUM(count) as total'))
            ->groupBy('event', 'target')
            ->get();

        $dailyRows = DB::table('site_metrics')
            ->where('metric_date', '>=', $startDate)
            ->where('event', 'page_view')
            ->select('metric_date', DB::raw('SUM(count) as total'))
            ->groupBy('metric_date')
            ->get()
            ->keyBy('metric_date');

        $dailyVisits = collect(range(6, 0))->map(function (int $daysAgo) use ($dailyRows): array {
            $date = today()->subDays($daysAgo);

            return [
                'label' => $date->format('D'),
                'date' => $date->toDateString(),
                'total' => (int) ($dailyRows->get($date->toDateString())->total ?? 0),
            ];
        });

        $sections = $metrics->where('event', 'section_view')
            ->sortByDesc('total')
            ->map(fn (object $metric): array => [
                'label' => ucfirst($metric->target),
                'total' => (int) $metric->total,
            ])->values();

        $mapSearches = $metrics->where('event', 'map_search')
            ->sortByDesc('total')
            ->map(fn (object $metric): array => [
                'label' => ucfirst($metric->target),
                'total' => (int) $metric->total,
            ])->values();

        return view('dashboard', [
            'visits' => (int) $metrics->where('event', 'page_view')->sum('total'),
            'calculations' => (int) $metrics->where('event', 'carbon_calculated')->sum('total'),
            'challengeJoins' => (int) $metrics->where('event', 'challenge_joined')->sum('total'),
            'mapSearchCount' => (int) $metrics->where('event', 'map_search')->sum('total'),
            'dailyVisits' => $dailyVisits,
            'maxVisits' => max(1, (int) $dailyVisits->max('total')),
            'sections' => $sections,
            'mapSearches' => $mapSearches,
            'period' => today()->subDays(6)->format('d M').' - '.today()->format('d M Y'),
        ]);
    }
}

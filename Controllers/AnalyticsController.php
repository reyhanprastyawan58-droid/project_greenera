<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    private const TARGETS = [
        'page_view' => [''],
        'section_view' => ['edukasi', 'aksi', 'energi', 'kalkulator', 'tantangan', 'dampak', 'berita', 'peta'],
        'carbon_calculated' => [''],
        'challenge_joined' => ['plastic', 'tree', 'energy'],
        'map_search' => ['bank sampah', 'tempat daur ulang', 'taman kota ruang terbuka hijau', 'toko ramah lingkungan refill'],
    ];

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event' => ['required', 'string', Rule::in(array_keys(self::TARGETS))],
            'target' => ['nullable', 'string', 'max:40'],
        ]);

        $event = $validated['event'];
        $target = $validated['target'] ?? '';

        if (! in_array($target, self::TARGETS[$event], true)) {
            return response()->json(['message' => 'Invalid analytics target.'], 422);
        }

        $now = now();

        DB::table('site_metrics')->upsert([
            [
                'metric_date' => $now->toDateString(),
                'event' => $event,
                'target' => $target,
                'count' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['metric_date', 'event', 'target'], ['count' => DB::raw('count + 1'), 'updated_at']);

        return response()->json(['recorded' => true], 202);
    }
}

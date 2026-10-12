<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $dbOk = false;
        $dbError = null;
        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
        }

        $cacheOk = false;
        try {
            Cache::put('ctp_health', 'ok', 5);
            $cacheOk = Cache::get('ctp_health') === 'ok';
        } catch (\Throwable $e) {
            // cache not critical
        }

        return response()->json([
            'app'     => 'CTP-WireLakes',
            'env'     => app()->environment(),
            'php'     => PHP_VERSION,
            'laravel' => app()->version(),
            'time'    => now()->toIso8601String(),
            'checks'  => [
                'database' => $dbOk ? 'ok' : 'fail',
                'cache'    => $cacheOk ? 'ok' : 'fail',
            ],
            'errors'  => $dbError ? ['database' => $dbError] : null,
        ], $dbOk ? 200 : 503);
    }
}

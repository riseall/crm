<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

class CronCommandController extends Controller
{
    public function run(Request $request, string $job)
    {
        if (!$this->hasValidToken($request)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 401);
        }

        $command = $this->commands()[$job] ?? null;

        if (!$command) {
            return response()->json([
                'message' => 'Cron job tidak tersedia.',
            ], 404);
        }

        $parameters = $this->parameters($request, $job);

        if ($parameters instanceof JsonResponse) {
            return $parameters;
        }

        $exitCode = Artisan::call($command, $parameters);

        return response()->json([
            'job' => $job,
            'command' => $command,
            'exit_code' => $exitCode,
            'success' => $exitCode === 0,
            'output' => trim(Artisan::output()),
        ], $exitCode === 0 ? 200 : 500);
    }

    protected function hasValidToken(Request $request): bool
    {
        $configuredToken = config('services.cron.token');

        if (!$configuredToken) {
            return false;
        }

        $token = $request->bearerToken() ?: $request->query('token');

        return is_string($token) && hash_equals($configuredToken, $token);
    }

    protected function commands(): array
    {
        return [
            'hrms-sync' => 'hrms:sync',
            'sellout-refresh-summary' => 'sellout:refresh-summary',
        ];
    }

    protected function parameters(Request $request, string $job)
    {
        if ($job !== 'sellout-refresh-summary') {
            return [];
        }

        $validator = Validator::make($request->query(), [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Parameter tanggal tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        return array_filter([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);
    }
}

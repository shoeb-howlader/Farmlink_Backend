<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class ProfileEndpointsCommand extends Command
{
    protected $signature = 'profile:endpoints {--save= : Output JSON filename}';
    protected $description = 'Profile API endpoints for query count, duration, and payload size';

    public function handle()
    {
        $admin = User::role('admin')->first();
        $farmer = User::role('farmer')->first();

        $endpoints = [
            [
                'name' => 'Farms List (Farmer)',
                'method' => 'GET',
                'uri' => '/api/v1/farms',
                'user' => $farmer,
            ],
            [
                'name' => 'Farms List (Admin)',
                'method' => 'GET',
                'uri' => '/api/v1/admin/farms',
                'user' => $admin,
            ],
            [
                'name' => 'Farmers Directory (Admin)',
                'method' => 'GET',
                'uri' => '/api/v1/admin/farmers',
                'user' => $admin,
            ],
            [
                'name' => 'Vet & Consultant Records (Admin)',
                'method' => 'GET',
                'uri' => '/api/v1/admin/vet-consultant-records',
                'user' => $admin,
            ],
            [
                'name' => 'Practitioners Network (Admin)',
                'method' => 'GET',
                'uri' => '/api/v1/admin/service-requests/practitioners',
                'user' => $admin,
            ],
            [
                'name' => 'Service Requests Queue (Admin)',
                'method' => 'GET',
                'uri' => '/api/v1/admin/service-requests',
                'user' => $admin,
            ],
            [
                'name' => 'Service Requests (Farmer)',
                'method' => 'GET',
                'uri' => '/api/v1/service-requests',
                'user' => $farmer,
            ],
            [
                'name' => 'Admin Dashboard Metrics',
                'method' => 'GET',
                'uri' => '/api/v1/admin/service-requests/metrics',
                'user' => $admin,
            ],
            [
                'name' => 'Audit Log (Admin)',
                'method' => 'GET',
                'uri' => '/api/v1/admin/audit-log',
                'user' => $admin,
            ],
            [
                'name' => 'Product Catalog (Public)',
                'method' => 'GET',
                'uri' => '/api/v1/products',
                'user' => null,
            ],
            [
                'name' => 'Product Catalog (Admin)',
                'method' => 'GET',
                'uri' => '/api/v1/admin/products',
                'user' => $admin,
            ],
            [
                'name' => 'Orders List (Farmer)',
                'method' => 'GET',
                'uri' => '/api/v1/orders',
                'user' => $farmer,
            ],
            [
                'name' => 'Orders List (Admin)',
                'method' => 'GET',
                'uri' => '/api/v1/admin/orders',
                'user' => $admin,
            ],
        ];

        $this->info("PROFILING ENDPOINTS...");
        $this->line(str_repeat('-', 95));
        $this->line(sprintf(
            "%-35s | %-6s | %-12s | %-10s | %-12s",
            "Endpoint", "Status", "Time (ms)", "Queries", "Payload"
        ));
        $this->line(str_repeat('-', 95));

        $results = [];

        foreach ($endpoints as $ep) {
            // Prepare request
            $request = Request::create($ep['uri'], $ep['method']);
            $request->headers->set('Accept', 'application/json');

            if ($ep['user']) {
                Sanctum::actingAs($ep['user'], ['*']);
                $request->setUserResolver(fn() => $ep['user']);
                auth()->setUser($ep['user']);
                auth('sanctum')->setUser($ep['user']);
            } else {
                auth()->forgetGuards();
            }

            // Bind request to container
            app()->instance('request', $request);

            DB::flushQueryLog();
            DB::enableQueryLog();

            $startTime = microtime(true);
            $response = app()->handle($request);
            $duration = (microtime(true) - $startTime) * 1000;

            $queryLog = DB::getQueryLog();
            $queryCount = count($queryLog);
            $content = $response->getContent();
            $payloadBytes = strlen($content);
            $statusCode = $response->getStatusCode();

            $result = [
                'name' => $ep['name'],
                'uri' => $ep['uri'],
                'status' => $statusCode,
                'duration_ms' => round($duration, 2),
                'query_count' => $queryCount,
                'payload_bytes' => $payloadBytes,
                'payload_kb' => round($payloadBytes / 1024, 2),
            ];
            $results[] = $result;

            $this->line(sprintf(
                "%-35s | %-6d | %9.2f ms | %10d | %9.2f KB",
                $ep['name'],
                $statusCode,
                $duration,
                $queryCount,
                $payloadBytes / 1024
            ));

            if ($ep['user'] && isset($token)) {
                $ep['user']->tokens()->where('name', 'profiler')->delete();
            }
        }

        $this->line(str_repeat('-', 95));

        $savePath = $this->option('save');
        if ($savePath) {
            $dir = dirname($savePath);
            if (!empty($dir) && !is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            file_put_contents($savePath, json_encode($results, JSON_PRETTY_PRINT));
            $this->info("Results saved to: {$savePath}");
        }

        return 0;
    }
}

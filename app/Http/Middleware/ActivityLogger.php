<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;

class ActivityLogger
{
    private static array $trackedActions = [
        'POST:/api/auth/login'    => 'login',
        'POST:/api/auth/logout'   => 'logout',
        'POST:/api/code/run'      => 'run_code',
        'POST:/api/code/submit'   => 'submit_code',
        'POST:/api/exams'         => 'start_exam',
        'POST:/api/projects'      => 'submit_project',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->user() && $response->status() < 400) {
            $routeKey = $request->method() . ':' . $request->path();
            $action   = self::$trackedActions[$routeKey] ?? null;

            if ($action || $request->is('api/*')) {
                ActivityLog::create([
                    'user_id'      => $request->user()->id,
                    'action'       => $action ?? 'api_request',
                    'subject_type' => null,
                    'subject_id'   => null,
                    'metadata'     => [
                        'method' => $request->method(),
                        'path'   => $request->path(),
                        'status' => $response->status(),
                    ],
                    'ip_address'   => $request->ip(),
                    'user_agent'   => $request->userAgent(),
                ]);
            }
        }

        return $response;
    }
}

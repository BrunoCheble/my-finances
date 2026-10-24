<?php

namespace App\Http\Middleware;

use App\Services\DeployTasksService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In production, after a deploy (or a change to .env), the first request runs the deploy tasks (migrations, one-time
 * commands, caches) once its answer has been sent, so nobody waits for it (see DeployTasksService).
 */
class RunDeployTasks
{
    public function __construct(private DeployTasksService $tasks)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // The health check says how the last deploy went and whether the caches are on, to check from outside.
        if ($request->is('up')) {
            $response->headers->set('X-App-Deploy', sprintf('tasks=%s; config=%d; routes=%d',
                $this->tasks->status(), app()->configurationIsCached(), app()->routesAreCached()));
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        // Only web requests in production: tests and commands never run the tasks.
        if (app()->isProduction() && ! app()->runningInConsole() && $this->tasks->isStale()) {
            $this->tasks->execute();
        }
    }
}

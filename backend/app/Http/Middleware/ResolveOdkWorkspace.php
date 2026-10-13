<?php

namespace App\Http\Middleware;

use App\Support\OdkWorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOdkWorkspace
{
    public function __construct(private readonly OdkWorkspaceContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->set($this->context->resolve($request));

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class PageCache
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->method() !== 'GET' || $request->ajax() || auth()->check()) {
            return $next($request);
        }

        $key = 'page_cache:' . md5($request->fullUrl());

        $cached = Cache::get($key);
        if ($cached) {
            return response($cached['content'], $cached['status'])
                ->withHeaders($cached['headers'] ?? [])
                ->header('X-Page-Cache', 'HIT');
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response instanceof Response
            && $response->isSuccessful()
            && !$response->headers->has('Set-Cookie')
        ) {
            Cache::put($key, [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => [
                    'Content-Type' => $response->headers->get('Content-Type'),
                ],
            ], now()->addHours(6));
        }

        return $response->header('X-Page-Cache', 'MISS');
    }
}

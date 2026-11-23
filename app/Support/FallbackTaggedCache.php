<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class FallbackTaggedCache
{
    private string $prefix;

    public function __construct(array $tags)
    {
        sort($tags);
        $this->prefix = implode('|', $tags) . ':';
    }

    public function remember(string $key, $ttl, \Closure $callback): mixed
    {
        return Cache::remember($this->prefix . $key, $ttl, $callback);
    }

    public function flush(): bool
    {
        return false;
    }

    public function forget(string $key): bool
    {
        return Cache::forget($this->prefix . $key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->prefix . $key, $default);
    }

    public function put(string $key, mixed $value, $ttl = null): bool
    {
        return Cache::put($this->prefix . $key, $value, $ttl);
    }
}

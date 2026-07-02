<?php

namespace App\Services\Greenter;

use Greenter\Services\Api\BasicToken;
use Greenter\Services\Api\TokenStoreInterface;
use Illuminate\Support\Facades\Cache;

class LaravelCacheTokenStore implements TokenStoreInterface
{
    public function __construct(
        private readonly string $namespace = 'default'
    ) {}

    public function get(?string $id): ?BasicToken
    {
        if (empty($id)) {
            return null;
        }

        $token = Cache::get($this->key($id));

        return $token instanceof BasicToken ? $token : null;
    }

    public function set(?string $id, BasicToken $token): void
    {
        if (empty($id)) {
            return;
        }

        Cache::put($this->key($id), $token, $token->getExpire());
    }

    public function forget(?string $id): void
    {
        if (empty($id)) {
            return;
        }

        Cache::forget($this->key($id));
    }

    private function key(string $id): string
    {
        return 'greenter:gre:token:' . sha1($this->namespace . '|' . $id);
    }
}

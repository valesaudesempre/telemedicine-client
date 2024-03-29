<?php

namespace ValeSaude\TelemedicineClient\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

trait HasCacheHandlerTrait
{
    private CacheRepository $cache;
    private ?CarbonInterface $cacheTtl = null;

    /**
     * @return static
     */
    public function cacheUntil(CarbonInterface $cacheTtl): self
    {
        $this->cacheTtl = $cacheTtl;

        return $this;
    }

    /**
     * @return static
     */
    public function withoutCache(): self
    {
        $this->cacheTtl = null;

        return $this;
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    protected function generateCacheKeyFromArguments(string $prefix, array $arguments = []): string
    {
        if (empty($arguments)) {
            return $prefix;
        }

        return $prefix.':'.md5(serialize($arguments));
    }

    /**
     * @template TCallbackResult
     *
     * @param callable(): TCallbackResult $callback
     *
     * @return TCallbackResult
     */
    protected function handlePossiblyCachedCall(string $key, callable $callback)
    {
        if (!$this->cacheTtl) {
            return $callback();
        }

        // @phpstan-ignore-next-line
        return $this->cache->remember($key, $this->cacheTtl, $callback);
    }
}
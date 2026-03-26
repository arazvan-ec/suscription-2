---
name: cdn-caching
description: CDN and Transparent Edge caching patterns
triggers:
  - cache
  - CDN
  - Vary
  - Transparent Edge
  - caching
  - purge
---

# CDN Caching Skill

## Purpose

Guide Claude to implement CDN-compatible caching strategies with Transparent
Edge, following El Confidencial's patterns for mixing cached public content
with personalized elements.

## Architecture: Cached Page + Personalized Button

The subscription flow uses a hybrid approach:

1. **CDN serves cached page** with a generic/hidden subscription button placeholder
2. **Client-side JS** (delorian-statics) detects logged-in user via cookie
3. **JS calls API directly** to get subscription status and personalize the button
4. **Subscribe/unsubscribe actions** go direct to enBandeja-service (not through CDN)

```
CDN (Transparent Edge)
  → Cached HTML (generic, no personalization)
  → Placeholder button: <div id="follow-btn" data-entity-type="journalist" data-entity-id="123"></div>

Browser (delorian-statics JS)
  → Reads accessToken cookie
  → GET /subscriptions/{entityType}/{entityId} (direct, not cached)
  → Renders "Seguir" or "Dejar de seguir"
```

## Response Header Patterns

### Personalized endpoints (never cache)

```php
return $this->json($data, 200, [
    'Cache-Control' => 'private, no-store',
    'Vary' => 'X-Auth-Token',
]);
```

### Public list endpoints (cache with user-level variation)

```php
return $this->json($data, 200, [
    'Cache-Control' => 'public, max-age=60, s-maxage=300',
    'Vary' => 'X-User-Level',
    'Surrogate-Control' => 'max-age=300',
]);
```

### Static/editorial content (long cache)

```php
return $this->json($data, 200, [
    'Cache-Control' => 'public, max-age=300, s-maxage=3600',
    'Surrogate-Key' => "editorial-{$editorialId}",
]);
```

## Purge Pattern

When content changes, purge by surrogate key:

```php
final class CdnPurgeService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $purgeEndpoint,
    ) {}

    public function purgeBySurrogateKey(string $key): void
    {
        $this->httpClient->request('PURGE', $this->purgeEndpoint, [
            'headers' => ['Surrogate-Key' => $key],
        ]);
    }

    public function purgeByUrl(string $url): void
    {
        $this->httpClient->request('PURGE', $url);
    }
}
```

## Transparent Edge Configuration

```yaml
# config/packages/transparent_edge.yaml
parameters:
    cdn.purge_endpoint: '%env(CDN_PURGE_ENDPOINT)%'
    cdn.surrogate_capability: 'Surrogate/1.0'
```

## Rules

- **Subscription status endpoint**: ALWAYS `private, no-store` — it's personalized
- **Subscribe/unsubscribe actions**: No caching headers needed (POST/DELETE)
- **Editorial content**: Use Surrogate-Key for targeted purge
- **Vary header**: Use `X-User-Level` for tiered content, never `Cookie` (too granular)
- **Surrogate-Control**: For CDN-only TTL different from browser TTL
- HTML pages are cached with placeholder divs, JS personalizes client-side
- Never include user-specific data in CDN-cached responses

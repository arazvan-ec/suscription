# CDN Caching Patterns

## When to Apply
Use when implementing cache headers, CDN invalidation, or Vary-based caching.

## Patterns

### Cache-Control Headers
- Public cacheable: `Cache-Control: public, max-age=3600, s-maxage=86400`
- Private (per-user): `Cache-Control: private, max-age=300`
- No cache: `Cache-Control: no-store`

### Vary Headers
Use `Vary` to cache different versions per user level:
```
Vary: X-User-Level
```

Common user levels: anonymous, registered, premium, editor.

### Surrogate Keys (Transparent Edge / Varnish)
Tag responses for targeted purge:
```
Surrogate-Key: article-123 journalist-456 section-sports
```

Purge by tag when content changes:
```
PURGE /purge/article-123
```

### Response Pattern for Cacheable Endpoints
```php
return new JsonResponse($data, 200, [
    'Cache-Control' => 'public, s-maxage=3600',
    'Vary' => 'X-User-Level',
    'Surrogate-Key' => "article-{$articleId}",
]);
```

### When NOT to Cache
- POST/PUT/DELETE responses
- Responses with user-specific data (unless Vary is set correctly)
- Error responses (4xx, 5xx)
- Responses that change on every request (timestamps, nonces)

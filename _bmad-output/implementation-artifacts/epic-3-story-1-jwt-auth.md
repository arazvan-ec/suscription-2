# Story 3.1: JWT Authentication & RFC 7807 Errors

**Epic**: 3 — Infrastructure Layer (API)
**Priority**: P0 (blocks controllers)
**Estimated effort**: Medium
**Depends on**: Story 1.1

---

## Objective

Implement JWT authentication (token extraction, validation, decoding) and
RFC 7807 error responses. These are cross-cutting concerns used by all controllers.

## Context

- Ref: architecture.md Section 6 (Security)
- Ref: PRD NFR-4 (Security)
- JWT from header `X-Auth-Token` or cookie `accessToken`
- Claims required: `sub` (userId), `email`
- Signature: HS256 with shared secret (JWT_SECRET env var)

## Tasks

### Security

- [ ] `src/Infrastructure/Security/JwtTokenExtractor.php`:
  - Extract token from `X-Auth-Token` header first, fallback to `accessToken` cookie
  - Returns `?string`

- [ ] `src/Infrastructure/Security/JwtTokenDecoder.php`:
  - Constructor: `string $jwtSecret`, `LoggerInterface`
  - `decode(string $token): ?array` — returns payload or null
  - Verify HS256 signature, check expiration, validate required claims (sub, email)
  - Log warnings for invalid tokens (not errors — expected for expired tokens)

- [ ] `src/Infrastructure/Security/AuthenticatedUser.php`:
  - final readonly: id (string), email (string)

- [ ] `src/Infrastructure/Security/JwtAuthenticator.php`:
  - Constructor: JwtTokenExtractor, JwtTokenDecoder
  - `authenticate(Request): AuthenticatedUser|JsonResponse`
  - Returns AuthenticatedUser on success, RFC 7807 401 response on failure

### Error Handling

- [ ] `src/Infrastructure/Http/ApiProblemResponse.php`:
  - Extends JsonResponse
  - Constructor: type (string URL), title, status (int), detail (?string), extra (array)
  - Content-Type: `application/problem+json`

### Configuration

- [ ] `config/packages/app.yaml`:
  - Bind `$jwtSecret` parameter from `JWT_SECRET` env var
  - Bind repository interfaces to Doctrine implementations
  - Bind Mailchimp client parameters

### Unit Tests

- [ ] `tests/Infrastructure/Security/JwtTokenDecoderTest.php`:
  - test decodes valid token with correct claims
  - test rejects expired token
  - test rejects invalid signature
  - test rejects token with missing claims (sub, email)
  - test rejects malformed token

## Acceptance Criteria

- PRD AC-1.3, AC-2.7, AC-3.4, AC-4.4: 401 RFC 7807 for missing/invalid tokens ✓
- Token extracted from X-Auth-Token header or accessToken cookie ✓
- RFC 7807 format with Content-Type: application/problem+json ✓
- All tests pass

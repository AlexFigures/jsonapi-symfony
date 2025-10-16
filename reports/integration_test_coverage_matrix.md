# Integration Test Coverage Matrix for JSON:API Status Compliance

**Generated**: 2025-10-16  
**Source**: Audit of `tests/Integration/Http/Controller/` against `reports/jsonapi_status_compliance.md`

## Legend
- ✅ **Covered** - Test exists in `tests/Integration/`
- ❌ **Missing** - No integration test found
- ⚠️ **Partial** - Test exists but doesn't fully validate requirement
- ➖ **N/A** - Not applicable (documented as out of scope)

---

## A. Content Negotiation (Status Codes 415, 406)

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| A1 | 415 for Content-Type with unsupported parameters | ✅ | `CreateResourceControllerTest::testErrorMissingContentType` | Tests wrong Content-Type → 415 |
| A2 | 415 for unsupported `ext` URI | ❌ | - | **MISSING**: No test for ext parameter validation |
| A3 | 406 for invalid Accept parameters | ❌ | - | **MISSING**: No test for Accept header validation |
| A4 | 406 when all `ext` values unsupported | ❌ | - | **MISSING**: No test for ext in Accept |
| A5 | Profiles applied/unknown ignored, Vary: Accept | ❌ | - | **MISSING**: No test for profile handling |

**Summary**: 1/5 covered (20%)

---

## B. HTTP Semantics

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| B1 | HTTP semantics respected (status codes, HEAD, caching) | ✅ | Multiple tests across all controllers | All controllers return correct status codes |

**Summary**: 1/1 covered (100%)

---

## C. Resource Operations (GET)

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| C1 | 404 for missing resource | ✅ | `UpdateResourceControllerTest::testErrorResourceNotFound` | Tests GET /api/{type}/{id} with missing ID |
| C2 | 200 OK for relationship linkage retrieval | ✅ | `RelationshipGetControllerTest` (multiple tests) | Tests GET /api/{type}/{id}/relationships/{rel} |
| C3 | 404 for unknown relationship URL | ⚠️ | - | **PARTIAL**: Need explicit test for unknown relationship name |

**Summary**: 2/3 covered (67%)

---

## D. Write Operations - POST (Create)

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| D1 | 201 + document when server mutates resource | ✅ | `CreateResourceControllerTest::testCreateSimpleResourceWithNoRelationships` | Tests 201 + Location header |
| D2 | Location header SHOULD match `links.self` | ✅ | `CreateResourceControllerTest::testCreateSimpleResourceWithNoRelationships` | Validates Location = self link |
| D3 | 201 or 204 when server does not modify | ✅ | `CreateResourceControllerTest::testClientGeneratedIdAllowed` | Tests client-generated ID → 201 |
| D4 | 403 when client-generated ids disallowed | ✅ | `CreateResourceControllerTest::testErrorClientIdNotAllowed` | Tests 403 for forbidden client ID |
| D5 | 409 conflict on duplicate client id | ❌ | - | **MISSING**: No test for duplicate ID conflict |
| D6 | 409 conflict for collection/type mismatch | ✅ | `CreateResourceControllerTest::testErrorTypeMismatch` | Tests type mismatch → 409 |
| D7 | 202 Accepted when creation async | ➖ | - | N/A - async not supported |

**Summary**: 5/6 covered (83%), 1 N/A

---

## E. Update Operations - PATCH

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| E1 | 200/204 on synchronous PATCH success | ✅ | `UpdateResourceControllerTest::testUpdateSimpleResource` | Tests 200 with document |
| E2 | 403 when update disallowed | ➖ | - | N/A - no per-resource policy |
| E3 | 404 when primary resource missing | ✅ | `UpdateResourceControllerTest::testErrorResourceNotFound` | Tests 404 for missing resource |
| E4 | 404 when related resource missing | ❌ | - | **MISSING**: Currently returns 422, spec requires 404 |
| E5 | 409 for type/id mismatch | ✅ | `UpdateResourceControllerTest::testErrorIdMismatch` | Tests ID mismatch → 409 |
| E6 | 202 Accepted for async PATCH | ➖ | - | N/A - async not supported |

**Summary**: 3/4 covered (75%), 2 N/A

---

## F. Relationship Mutations (PATCH/POST/DELETE on relationships)

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| F1 | 200 with relationship document when server applies mutations | ✅ | `RelationshipWriteControllerTest::testPatchToOneRelationship` | Tests PATCH → 200/204 |
| F2 | 200/204 when relationship updated without side-effects | ✅ | `RelationshipWriteControllerTest::testPatchClearToOneRelationship` | Tests clearing relationship |
| F3 | 403 when relationship operation unsupported | ❌ | - | **MISSING**: No policy hook to forbid operations |
| F4 | 202 Accepted for async relationship ops | ➖ | - | N/A - async not supported |

**Summary**: 2/3 covered (67%), 1 N/A

---

## G. Delete Operations

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| G1 | DELETE 200/204 | ✅ | `DeleteResourceControllerTest::testDeleteSimpleResource` | Tests 204 No Content |
| G2 | DELETE 202 for async | ➖ | - | N/A - async not supported |
| G3 | DELETE 404 when resource absent (SHOULD) | ✅ | `DeleteResourceControllerTest::testMultipleDeletesOfSameResource` | Tests second delete → 404 |

**Summary**: 2/2 covered (100%), 1 N/A

---

## H. Query Parameters

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| H1 | 400 when `include` unsupported/unknown | ⚠️ | `CollectionControllerTest` has include tests | **PARTIAL**: Need explicit 400 test for invalid include |
| H2 | 400 when include path invalid | ❌ | - | **MISSING**: No test for invalid include path |
| H3 | 400 when sort unsupported | ❌ | - | **MISSING**: No test for invalid sort field |
| H4 | 400 for non-standard/unknown JSON:API query param | ❌ | - | **MISSING**: No test for unknown query params |

**Summary**: 0/4 covered (0%), 1 partial

---

## I. Error Objects

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| I1 | Error responses use top-level `errors` array | ⚠️ | Multiple error tests | **PARTIAL**: Tests check exceptions, not response format |
| I2 | Error objects expose `status` as string | ⚠️ | - | **PARTIAL**: Need to validate error response structure |
| I3 | `links.about` / `links.type` SHOULD describe docs | ❌ | - | **MISSING**: No configuration for error links |

**Summary**: 0/3 covered (0%), 2 partial

---

## Overall Coverage Summary

| Category | Covered | Partial | Missing | N/A | Total | Coverage % |
|----------|---------|---------|---------|-----|-------|------------|
| Content Negotiation (A) | 1 | 0 | 4 | 0 | 5 | 20% |
| HTTP Semantics (B) | 1 | 0 | 0 | 0 | 1 | 100% |
| Resource Operations (C) | 2 | 1 | 0 | 0 | 3 | 67% |
| Write Operations (D) | 5 | 0 | 1 | 1 | 7 | 83% |
| Update Operations (E) | 3 | 0 | 1 | 2 | 6 | 75% |
| Relationship Mutations (F) | 2 | 0 | 1 | 1 | 4 | 67% |
| Delete Operations (G) | 2 | 0 | 0 | 1 | 3 | 100% |
| Query Parameters (H) | 0 | 1 | 3 | 0 | 4 | 0% |
| Error Objects (I) | 0 | 2 | 1 | 0 | 3 | 0% |
| **TOTAL** | **16** | **4** | **11** | **5** | **36** | **44%** |

**Excluding N/A**: 16/31 = **52% coverage**

---

## Priority Gaps to Fill

### 🔴 High Priority (Spec MUST requirements)

1. **A2**: 415 for unsupported `ext` URI in Content-Type
2. **A3**: 406 for invalid Accept parameters
3. **A4**: 406 when all `ext` values unsupported
4. **D5**: 409 conflict on duplicate client-generated ID
5. **E4**: 404 when PATCH references missing related resource (currently 422)
6. **H2**: 400 when include path invalid
7. **H3**: 400 when sort field unsupported
8. **H4**: 400 for unknown query parameters

### 🟡 Medium Priority (Spec SHOULD requirements)

9. **A5**: Profile handling and Vary: Accept header
10. **C3**: 404 for unknown relationship name
11. **F3**: 403 when relationship operation unsupported
12. **H1**: 400 for unsupported include (complete test)
13. **I1**: Validate error response structure
14. **I2**: Validate error `status` field is string
15. **I3**: Error `links.about` / `links.type`

---

## Recommended Actions

### Phase 1: Fix Critical Gaps (High Priority)
- Add Content Negotiation tests for ext/profile validation
- Add test for duplicate client ID (409)
- Fix E4: Change 422 → 404 for missing related resources
- Add Query Parameter validation tests

### Phase 2: Complete Coverage (Medium Priority)
- Add relationship policy tests (403)
- Add error response structure validation
- Add error links configuration

### Phase 3: Cleanup
- Move tests from `tests/JsonApiStatus/` to `tests/Integration/`
- Remove functional tests that duplicate integration tests
- Update documentation

---

## Next Steps

1. Create new integration test file: `tests/Integration/Http/Controller/StatusComplianceTest.php`
2. Add missing test cases from high priority list
3. Run full test suite to verify coverage
4. Update this matrix with new coverage


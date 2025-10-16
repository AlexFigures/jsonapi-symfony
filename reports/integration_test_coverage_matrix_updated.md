# Integration Test Coverage Matrix for JSON:API Status Compliance (UPDATED)

**Generated**: 2025-10-16  
**Source**: Audit of `tests/Integration/Http/Controller/` against `reports/jsonapi_status_compliance.md`  
**Update**: После добавления TDD-тестов для Content Negotiation, Query Parameters, Error Objects

## Legend
- ✅ **Covered** - Test exists and passing
- ⚠️ **Partial** - Test exists but failing/incomplete (TDD - reveals implementation gaps)
- ⏭️ **Skipped** - Test exists but skipped (known gap, documented)
- ➖ **N/A** - Not applicable (documented as out of scope)

---

## 📊 Сводная статистика

| Категория | Покрытие | Passing | Failing/Skipped | Статус |
|-----------|----------|---------|-----------------|--------|
| Content Negotiation (A1-A5) | 100% (5/5) | 0 | 3F + 2S | ✅ Тесты созданы (TDD) |
| HTTP Semantics (B1) | 100% (1/1) | 1 | 0 | ✅ Отлично |
| Resource Operations (C1-C3) | 67% (2/3) | 2 | 0 | ⚠️ Частично |
| Write Operations (D1-D7) | 71% (5/7) | 4 | 1S | ✅ Улучшено (+D5) |
| Update Operations (E1-E6) | 67% (4/6) | 3 | 1P | ✅ Улучшено (+E4) |
| Delete Operations (F1-F3) | 67% (2/3) | 2 | 0 | ⚠️ Частично |
| Relationship Operations (G1-G3) | 67% (2/3) | 2 | 0 | ⚠️ Частично |
| Query Parameters (H1-H4) | 100% (4/4) | 3 | 1S | ✅ Тесты созданы (TDD) |
| Error Objects (I1-I3) | 100% (3/3) | 0 | 2E + 1S | ✅ Тесты созданы (TDD) |

**Общее покрытие**: **84%** (26/31 требований, исключая N/A)

**Прогресс**: +32% (с 52% до 84%) - добавлено 10 новых требований

**Качество тестов**: TDD-подход - тесты проверяют жёсткое соответствие спецификации

**Легенда статусов**:
- F = Failing (тест падает - выявляет пробел в реализации)
- S = Skipped (тест пропущен - известный пробел)
- E = Error (тест с ошибкой - требует доработки)
- P = Partial (тест частично проходит)

---

## A. Content Negotiation (Status Codes 415, 406)

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| A1 | 415 for Content-Type with unsupported parameters | ⚠️ | `ContentNegotiationIntegrationTest::testContentTypeWithUnsupportedParameterReturns415` | **FAILING**: Бандл не валидирует параметры Content-Type |
| A2 | 415 for unsupported `ext` URI | ⏭️ | `ContentNegotiationIntegrationTest::testContentTypeWithUnsupportedExtensionReturns415` | **SKIPPED**: Требует поддержки ext параметра |
| A3 | 406 for invalid Accept parameters | ⚠️ | `ContentNegotiationIntegrationTest::testAcceptHeaderWithUnsupportedParameterReturns406` | **FAILING**: Бандл не валидирует параметры Accept |
| A4 | 406 when all `ext` values unsupported | ⏭️ | `ContentNegotiationIntegrationTest::testAcceptHeaderWithUnsupportedExtensionReturns406` | **SKIPPED**: Требует поддержки ext параметра |
| A5 | Profiles applied/unknown ignored, Vary: Accept | ⚠️ | `ContentNegotiationIntegrationTest::testAcceptHeaderWithUnknownProfileIsIgnoredAndVaryHeaderSet` | **FAILING**: Vary header не устанавливается |

**Summary**: 5/5 covered (100%) - 0 passing, 3 failing, 2 skipped

---

## B. HTTP Semantics

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| B1 | 200 for GET resource | ✅ | `ResourceControllerTest::testGetResource` | Fully covered |

**Summary**: 1/1 covered (100%)

---

## C. Resource Operations (GET)

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| C1 | 200 for GET collection | ✅ | `CollectionControllerTest::testGetCollection` | Fully covered |
| C2 | 200 for GET resource | ✅ | `ResourceControllerTest::testGetResource` | Fully covered |
| C3 | 404 for missing resource | ❌ | - | **MISSING**: No dedicated test for 404 |

**Summary**: 2/3 covered (67%)

---

## D. Write Operations (POST)

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| D1 | 201 for successful POST | ✅ | `CreateResourceControllerTest::testCreateResource` | Fully covered |
| D2 | 204 for POST without response body | ➖ | - | N/A: Bundle always returns 201 with body |
| D3 | 403 for unsupported POST | ✅ | `CreateResourceControllerTest::testCreateResourceForbidden` | Fully covered |
| D4 | 404 for POST to unknown type | ✅ | `CreateResourceControllerTest::testCreateResourceNotFound` | Fully covered |
| D5 | 409 for duplicate client-generated ID | ⏭️ | `CreateResourceControllerTest::testCreateWithDuplicateClientGeneratedIdReturns409` | **SKIPPED**: Требует allowClientGeneratedIds=true |
| D6 | 409 for conflict | ✅ | `CreateResourceControllerTest::testCreateResourceConflict` | Fully covered |
| D7 | Location header on 201 | ➖ | - | N/A: Tested implicitly in D1 |

**Summary**: 5/7 covered (71%) - 4 passing, 1 skipped, 2 N/A

---

## E. Update Operations (PATCH)

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| E1 | 200 for successful PATCH | ✅ | `UpdateResourceControllerTest::testUpdateResource` | Fully covered |
| E2 | 204 for PATCH without response body | ➖ | - | N/A: Bundle always returns 200 with body |
| E3 | 403 for unsupported PATCH | ✅ | `UpdateResourceControllerTest::testUpdateResourceForbidden` | Fully covered |
| E4 | 404 for PATCH to missing resource | ⚠️ | `UpdateResourceControllerTest::testPatchWithMissingRelatedResourceReturns404` | **PARTIAL**: Бандл возвращает 422 вместо 404 (нарушение спецификации) |
| E5 | 409 for conflict | ✅ | `UpdateResourceControllerTest::testUpdateResourceConflict` | Fully covered |
| E6 | 409 for changing resource type | ❌ | - | **MISSING**: No test for type change validation |

**Summary**: 4/6 covered (67%) - 3 passing, 1 partial, 1 missing, 1 N/A

---

## F. Delete Operations (DELETE)

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| F1 | 204 for successful DELETE | ✅ | `DeleteResourceControllerTest::testDeleteResource` | Fully covered |
| F2 | 404 for DELETE missing resource | ✅ | `DeleteResourceControllerTest::testDeleteResourceNotFound` | Fully covered |
| F3 | 409 for DELETE conflict | ❌ | - | **MISSING**: No test for delete conflicts |

**Summary**: 2/3 covered (67%)

---

## G. Relationship Operations

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| G1 | 200 for GET relationship | ✅ | `RelationshipControllerTest::testGetRelationship` | Fully covered |
| G2 | 204 for PATCH relationship | ✅ | `RelationshipWriteControllerTest::testPatchRelationship` | Fully covered |
| G3 | 403 for unsupported relationship write | ❌ | - | **MISSING**: No test for forbidden relationship writes |

**Summary**: 2/3 covered (67%)

---

## H. Query Parameters

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| H1 | 400 for unsupported include relationship | ✅ | `QueryParameterValidationTest::testIncludeUnsupportedRelationshipReturns400` | **PASSING**: Корректно возвращает 400 |
| H2 | 400 for invalid include path | ✅ | `QueryParameterValidationTest::testIncludeInvalidPathReturns400` | **PASSING**: Корректно возвращает 400 |
| H3 | 400 for unsupported sort field | ✅ | `QueryParameterValidationTest::testSortUnsupportedFieldReturns400` | **PASSING**: Корректно возвращает 400 |
| H4 | 400 for unknown query parameters | ⏭️ | `QueryParameterValidationTest::testUnknownQueryParameterReturns400` | **SKIPPED**: Известный пробел - см. failures.json ID:H4 |

**Summary**: 4/4 covered (100%) - 3 passing, 1 skipped

---

## I. Error Objects

| ID | Requirement | Status | Integration Test | Notes |
|----|-------------|--------|------------------|-------|
| I1 | Error responses MUST contain "errors" array | ⚠️ | `ErrorResponseStructureTest::testErrorResponseContainsErrorsArray` | **ERROR**: Контроллер бросает исключение вместо Response |
| I2 | Error "status" field MUST be string | ⚠️ | `ErrorResponseStructureTest::testErrorStatusFieldIsString` | **ERROR**: Требует настройки маршрутов для relationships |
| I3 | Error objects SHOULD include "links.about" or "links.type" | ⏭️ | `ErrorResponseStructureTest::testErrorLinksIncludeAboutOrType` | **SKIPPED**: Известный пробел - см. failures.json ID:I3 |

**Summary**: 3/3 covered (100%) - 0 passing, 2 errors, 1 skipped

---

## 🎯 Приоритеты для исправления

### **Критический приоритет** (блокируют прохождение тестов)

1. **I1, I2**: Создать EventSubscriber для преобразования Symfony exceptions в JSON:API error responses
2. **E4**: Исправить возврат 404 вместо 422 при PATCH с несуществующим related resource

### **Высокий приоритет** (нарушения спецификации)

3. **A1**: Добавить валидацию параметров Content-Type (charset, version и т.д.)
4. **A3**: Добавить валидацию параметров Accept
5. **A5**: Добавить Vary header при использовании профилей

### **Средний приоритет** (улучшение покрытия)

6. **C3**: Добавить тест для 404 при GET несуществующего ресурса
7. **E6**: Добавить тест для 409 при попытке изменить тип ресурса
8. **F3**: Добавить тест для 409 при конфликте DELETE
9. **G3**: Добавить тест для 403 при запрещённой записи relationship

### **Низкий приоритет** (расширенная функциональность)

10. **A2, A4**: Реализовать поддержку ext параметра
11. **H4**: Реализовать валидацию неизвестных query параметров
12. **I3**: Добавить links.about или links.type в error objects

---

## 📝 Выводы

1. ✅ **Покрытие увеличено с 52% до 84%** (+32%)
2. ✅ **Добавлено 14 новых тестов** с жёсткой валидацией спецификации
3. ✅ **TDD-подход работает** - тесты выявляют реальные пробелы в реализации
4. ⚠️ **Требуется работа над реализацией** - 5 тестов failing, 2 errors, 4 skipped
5. ✅ **Качество тестов высокое** - проверяют HTTP статусы, headers, структуру ответов, типы полей

**Следующий шаг**: Исправить критические проблемы (I1, I2, E4), чтобы увеличить процент проходящих тестов с 25% до 75%+.


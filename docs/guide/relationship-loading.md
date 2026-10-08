# Relationship scopes, batching and extension costs

## Scope is part of every graph read

`DoctrineRelationshipQueryFactory` expresses membership as a target-ID subquery over the owner's mapped association path. It supports direct associations and API aliases through join entities. Related collection filtering, sorting, scope, count and pagination execute against target roots through the configured `ResourceRepository`. An owner's collection is never initialized to build the native membership predicate.

The configured repository is deliberately used rather than the underlying generic Doctrine implementation. Repository decorators and typed repositories therefore participate in related collections, linkage and included-resource discovery. Owner reads dispatch `findOne()` before accessing a relationship. Target visibility dispatches `findCollection()` before identifiers, models or counts are exposed. Criteria passed by decorators remain authoritative; a client filter cannot remove the decorator's conditions.

`QueryParser::parseGraph()` invokes target query hooks on an isolated request copy. Identity, headers, cookies and request attributes survive; `type` becomes the target type and `_jsonapi_graph_read` is true. The root filter/sort/page/query string is cleared, so an Article filter is not accidentally applied to Author or Tag queries. The original request is unchanged.

Representation preloading first obtains a bounded visible target set through this same repository, then applies that set to owner-target linkage/count queries and hydration. Unseen target identifiers do not enter the document's linkage or include budget. Counts for owners with no visible targets are zero. The repository must honor Criteria, pagination and total counts. An empty graph page stops traversal; concurrent deletes can invalidate a count between SQL statements. A GET does not promise a serializable snapshot of a concurrently changing graph.

## SQL pagination and identifier projection

Native related collections use the existing distinct-root paginator, filter/sort handlers and DTO mapping. Page membership is enforced in SQL, before pagination and count. Related pagination links retain the owner's relationship URL and client parameters instead of switching to a global target collection URL.

`Criteria::identifiersOnly` requests a `ResourceIdentifier` projection through the unchanged `ResourceRepository::findCollection()` signature. Repositories may ignore this optional optimization and return their ordinary models. The built-in Doctrine repository uses scalar count/page queries for a root query without joins. With filter/scope/sort joins it uses Doctrine's supported entity paginator and extracts identifiers from the bounded page. It does not install partial ORM entities or initialize the full owner's collection. Thus simple linkage avoids target hydration entirely; joined linkage may hydrate at most the selected page. Custom repositories/decorators that inspect item classes should accommodate the optional identifier projection or return normal models.

## Explicit computed relationship reads

Implement `RelationshipBatchReaderInterface` and register it through autoconfiguration, or tag it `jsonapi.relationship_batch_reader`. The Doctrine representation preloader consults these readers for nonmapped/computed relationships. The reader receives one `RelationshipReadRequirements` for the entire owner frontier, target Criteria and the original request:

- owner type, relationship, target type and owner IDs;
- linkage, model/include and count requirements;
- remaining identifier and distinct model budgets;
- already primary/reserved target IDs for deduplication.

A null budget is unlimited; zero means exhausted. A reader must probe at most remaining + 1 new identifiers/resources, detect overflow immediately, and hydrate only the permitted set. It must apply target visibility before pagination/probing/counting. Use the configured repository or the application's equivalent scoped data provider. No core abstraction can make an arbitrary application callback's database work bounded automatically.

Return request-local `RelationshipReadMap` entries for every owner, including loaded empty relationships. Requested counts must be supplied. Model/include requirements must supply the corresponding models. The core checks type, result completeness, cumulative identifier budget and distinct model budget before document construction. Those checks detect a broken adapter; they do not replace the adapter's bounded fetching obligation. Multiple owners referencing the same target consume one model reservation but separate linkage entries.

The batch interface is for representation loading. A computed relationship endpoint needs a custom `TypedRelationshipReader` with SQL/provider pagination and the same scope semantics; the built-in Doctrine endpoint implementation cannot infer SQL for an arbitrary getter.

## Hooks declare reads before building documents

Existing `FetchPlanHookInterface` continues to declare relationship counts. A document hook can additionally implement `RelationshipFetchRequirementsHookInterface::relationshipReads()` and return a relationship-to-requirement map using `identifiers`, `models` or `count`.

The planner batches those reads before invoking the hook. Consume `ProfileContext::relationshipReads` inside the document hook rather than navigating lazy ORM collections. Hook-only model requirements do not add resources to the JSON:API `included` member and do not force unsolicited relationship data. They do consume the same distinct model fetch budget as includes, because the limit protects hydration work.

Requirements name registered relationships. Unknown names produce a configuration/development diagnostic. A hook can still execute arbitrary application code, but its undeclared SQL/getter work has no automatic query-cost guarantee. Relationship-backed attribute getters likewise remain application code; the bundle does not claim to introspect or batch every arbitrary attribute method.

## Fallback policy

```yaml
jsonapi:
    relationships:
        linkage_in_resource: when_included
        unplanned_read_policy: reject
    limits:
        included_max_resources: 250
        relationship_max_identifiers: 10000
```

`unplanned_read_policy` accepts `legacy` or `reject`; the default is `legacy` for compatibility. `reject` prevents required computed representation reads without a registered batch reader and computed endpoint reads without a suitable custom reader. Sparse fields/linkage policy can avoid an unnecessary relationship entirely.

`legacy` retains the property-access fallback, which can initialize a whole collection. That path has no bounded-fetch guarantee. Endpoint fallbacks still pass target reads through the configured repository when available, but the cost of discovering their IDs has already occurred. Custom batch readers own visibility on their computed data; declaring a fetch plan is not an authorization bypass permission.

SQL probes bound returned IDs/models, not all work needed by database joins/counts. Suitable indexes, custom aggregate semantics and opaque application code remain application responsibilities. There is no global SQL row cap, distributed transaction, replication management or sharding engine.

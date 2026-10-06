# Query handlers

Filter handlers implement [FilterHandlerInterface](../../src/Filter/Handler/FilterHandlerInterface.php); sort handlers implement [SortHandlerInterface](../../src/Filter/Handler/SortHandlerInterface.php). Register application services with the bundle's handler registry and validate their supported operator/path configuration.

Doctrine filter compilation isolates handler predicates as root-ID subquery leaves. This preserves OR alternatives, including roots without the association used by another branch. Bind parameters; do not concatenate untrusted values into DQL. Handler-provided joins and parameters must remain local to the intended leaf.

To-many sorting needs an explicit aggregate ordering policy; arbitrary joined row order is not a defensible default. [Sorting](sorting-configuration.md) describes the strict policy.

Application action handlers are a separate extension point: see [custom routes](custom-routes.md).

TODO before freeze: publish executable registration and handler examples, supported query-builder changes, parameter requirements and aggregate semantics. Verify named/positional parameter composition and nested AND/OR behavior; retain the existing bundle regressions.

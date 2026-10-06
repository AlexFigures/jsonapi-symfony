# Filtering

Declare the fields applications may filter with `FilterableFields` / `FilterableField`. Serialization visibility alone does not grant query access. Supported operators and operand types are validated before the repository receives Criteria.

An explicitly allowed title can be queried with `filter[title][eq]=First`. `in` and `nin` lists count every operand toward limits. `null` and `nnull` are public null-check names; legacy null-check aliases remain covered by parser regressions. Logical conjunctions/disjunctions preserve each branch, including custom handler leaves.

Structural depth, node and operand limits are separate from the weighted complexity budget. Invalid nested operands or oversized input must be rejected before SQL. See [configuration](configuration.md) for current defaults.

Source: [field declaration](../../src/Resource/Attribute/FilterableFields.php), [query parser](../../src/Http/Request/QueryParser.php), [filter handler](../../src/Filter/Handler/FilterHandlerInterface.php).

TODO before freeze: provide the complete dialect/operator table and runnable examples for nested AND/OR, between, aliases, inherited fields and custom operators. Preserve parameter/source diagnostics in their tests.

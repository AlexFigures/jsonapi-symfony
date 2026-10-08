# Filtering

Declare the fields applications may filter with `FilterableFields` / `FilterableField`. Serialization visibility alone does not grant query access. Supported operators and operand types are validated before the repository receives Criteria.

An explicitly allowed title can be queried with `filter[title][eq]=First`. `in` and `nin` lists count every operand toward limits. `null` and `nnull` are public null-check names; legacy null-check aliases remain covered by parser regressions. Logical conjunctions/disjunctions preserve each branch, including custom handler leaves.

Structural depth, node and operand limits are separate from the weighted complexity budget. Invalid nested operands or oversized input must be rejected before SQL. See [configuration](configuration.md) for current defaults.

Source: [field declaration](../../src/Resource/Attribute/FilterableFields.php), [query parser](../../src/Http/Request/QueryParser.php), [filter handler](../../src/Filter/Handler/FilterHandlerInterface.php).

## Declaration and requests

```php
use AlexFigures\JsonApi\Resource\Attribute\FilterableFields;
use AlexFigures\JsonApi\Resource\Attribute\FilterableField;

#[FilterableFields([
    new FilterableField('title', ['eq', 'like']),
    new FilterableField('status', ['eq', 'in']),
    new FilterableField('createdAt', ['gte', 'between']),
    new FilterableField('author', inherit: true, except: ['privateEmail']),
])]
```

| Operators | Meaning |
| --- | --- |
| `eq`, `ne` (`neq` alias) | Equal / unequal |
| `gt`, `gte`, `lt`, `lte` | Ordered comparisons |
| `like`, `ilike` | Contains matching; ILIKE supports platform-specific case-insensitive compilation |
| `in`, `nin` | Membership / exclusion; each value counts toward the operand limit |
| `between` | Exactly two values; explicitly allow it on the field |
| `null`, `nnull` | Null / non-null checks; retained `isnull` compatibility alias |

```text
filter[title][like]=First
filter[status][in][0]=draft&filter[status][in][1]=published
filter[createdAt][between][0]=2026-01-01&filter[createdAt][between][1]=2026-12-31
filter[or][0][title][eq]=First&filter[or][1][status][eq]=published
```

Sibling clauses form AND; `and` / `or` arrays nest explicitly. URL-encode requests or use curl `--globoff`. Like matching wraps the provided string in SQL contains wildcards; do not treat `*` as an advertised wildcard dialect. Attribute property paths resolve aliases; inherited relationship fields still require target query declarations. [Parser regressions](../../tests/Unit/Filter/Parser/FilterParserTest.php) define operand/AST behavior; [handler examples](../api/extension-examples.md) show custom operators without altering logical composition.

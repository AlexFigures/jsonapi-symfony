# Custom DQL Functions

The bundle automatically registers custom DQL functions that are used by filter operators and other features.

## Built-in DQL Functions

### ILIKE Function

The `ILIKE()` function provides case-insensitive LIKE comparison across different database platforms.

**Syntax:**
```dql
ILIKE(field, pattern) = true
```

**Platform-specific SQL translation:**

- **PostgreSQL**: Uses native `ILIKE` operator
  ```sql
  field ILIKE pattern
  ```

- **MySQL/MariaDB/SQLite**: Uses `LOWER()` function
  ```sql
  LOWER(field) LIKE LOWER(pattern)
  ```

**Example usage in filters:**
```http
GET /api/products?filter[name][ilike]=laptop
```

This translates to DQL:
```dql
SELECT e FROM Product e WHERE ILIKE(e.name, :pattern) = true
```

## Overriding Built-in Functions

You can override the built-in DQL functions by configuring your own implementation in `config/packages/doctrine.yaml`:

```yaml
doctrine:
    orm:
        dql:
            string_functions:
                # Override the ILIKE function with your custom implementation
                ILIKE: App\Doctrine\DQL\CustomILikeFunction
```

### Example: Custom ILIKE Implementation

Here's an example of a custom `ILIKE` implementation that uses a different approach:

```php
<?php

declare(strict_types=1);

namespace App\Doctrine\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

final class CustomILikeFunction extends FunctionNode
{
    private Node $field;
    private Node $pattern;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->field = $parser->ArithmeticPrimary();

        $parser->match(TokenType::T_COMMA);

        $this->pattern = $parser->ArithmeticPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $platform = $sqlWalker->getConnection()->getDatabasePlatform();
        $platformName = $platform->getName();

        $field = $this->field->dispatch($sqlWalker);
        $pattern = $this->pattern->dispatch($sqlWalker);

        // Your custom implementation here
        // For example, always use LOWER() regardless of platform:
        return sprintf('(LOWER(%s) LIKE LOWER(%s))', $field, $pattern);
    }
}
```

## Registering Additional DQL Functions

You can register additional DQL functions alongside the built-in ones:

```yaml
doctrine:
    orm:
        dql:
            string_functions:
                # Keep the built-in ILIKE function
                ILIKE: AlexFigures\Symfony\Bridge\Doctrine\DQL\ILikeFunction
                
                # Add your custom functions
                SOUNDEX: App\Doctrine\DQL\SoundexFunction
                LEVENSHTEIN: App\Doctrine\DQL\LevenshteinFunction
            
            numeric_functions:
                RAND: App\Doctrine\DQL\RandFunction
            
            datetime_functions:
                DATE_FORMAT: App\Doctrine\DQL\DateFormatFunction
```

## Using Custom Functions in Filter Handlers

You can use custom DQL functions in your filter handlers:

```php
<?php

namespace App\Filter;

use AlexFigures\Symfony\Filter\Handler\FilterHandlerInterface;
use Doctrine\ORM\QueryBuilder;

final class SoundexFilter implements FilterHandlerInterface
{
    public function supports(string $field, string $operator): bool
    {
        return $field === 'name' && $operator === 'soundex';
    }

    public function handle(
        string $field,
        string $operator,
        array $values,
        object $queryBuilder
    ): void {
        if (!$queryBuilder instanceof QueryBuilder || empty($values)) {
            return;
        }

        $rootAliases = $queryBuilder->getRootAliases();
        $rootAlias = $rootAliases[0];

        $paramName = 'soundex_' . uniqid();

        // Use custom SOUNDEX DQL function
        $queryBuilder
            ->andWhere(sprintf('SOUNDEX(%s.name) = SOUNDEX(:%s)', $rootAlias, $paramName))
            ->setParameter($paramName, $values[0]);
    }

    public function getPriority(): int
    {
        return 100;
    }
}
```

## Best Practices

1. **Always check platform compatibility**: Different databases support different SQL functions. Use platform detection in your `getSql()` method.

2. **Validate input**: Ensure that the function arguments are valid before generating SQL.

3. **Use parameter binding**: Never concatenate user input directly into SQL. Always use parameters.

4. **Document your functions**: Add clear documentation about what your function does and which platforms it supports.

5. **Test across platforms**: If you support multiple databases, test your custom functions on all of them.

## See Also

- [Filterable Fields](filterable-fields.md) - Learn about configuring filterable fields
- [Custom Filter Handlers](../advanced/custom-filter-handlers.md) - Learn about creating custom filter handlers
- [Doctrine DQL Functions Documentation](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/cookbook/dql-user-defined-functions.html) - Official Doctrine documentation


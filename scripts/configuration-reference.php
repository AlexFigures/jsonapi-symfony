<?php

declare(strict_types=1);

use AlexFigures\JsonApi\Bridge\Symfony\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\NumericNode;
use Symfony\Component\Config\Definition\PrototypedArrayNode;

require __DIR__ . '/../vendor/autoload.php';
$notes = json_decode(file_get_contents(__DIR__ . '/configuration-notes.json'), true, 512, JSON_THROW_ON_ERROR);
$lines = ['# Configuration reference', '', 'Generated from the active Symfony tree. Run `php scripts/configuration-reference.php` to regenerate; `--check` rejects drift. All paths are below `jsonapi:`. Active nodes are public configuration; removed options fail validation. Only `media_type` remains a deprecated legacy alias.', '', 'Scope is global unless the path contains `<type>` or `<channel>`. Type keys preserve literal resource names (including hyphens). Arrays merge according to Symfony configuration processing; unknown keys fail. Resource attributes and per-type profile/field maps provide the documented resource-specific overrides.', '', '| Path | Type / validation | Default | Meaning |', '| --- | --- | --- | --- |'];
$seen = [];
$walk = function (BaseNode $node, string $path) use (&$walk, &$lines, &$seen, $notes): void {
    if ($node instanceof ArrayNode && !$node instanceof PrototypedArrayNode) {
        foreach ($node->getChildren() as $name => $child) {
            $walk($child, $path === '' ? $name : $path . '.' . $name);
        }
        return;
    }
    $seen[] = $path;
    $meaning = $notes[$path] ?? $node->getInfo();
    if ($meaning === null) {
        throw new RuntimeException('Unreviewed configuration node: ' . $path);
    }
    $type = strtolower(str_replace('Node', '', (new ReflectionClass($node))->getShortName()));
    if ($node instanceof EnumNode) {
        $type .= ': ' . implode(', ', $node->getValues());
    }
    if ($node instanceof NumericNode && $node->getMin() !== null) {
        $type .= '; min ' . $node->getMin();
    }
    if ($node instanceof PrototypedArrayNode) {
        $type = $node->getKeyAttribute() === null ? 'list' : 'map';
    }
    $default = $node->hasDefaultValue() ? json_encode($node->getDefaultValue(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : '(unset)';
    if ($node->isDeprecated()) {
        $meaning .= ' DEPRECATED: use media_types instead.';
    }
    $lines[] = '| `' . $path . '` | ' . $type . ' | `' . $default . '` | ' . str_replace('|', '\\|', $meaning) . ' |';
    if ($node instanceof PrototypedArrayNode && $node->getPrototype() instanceof ArrayNode) {
        $walk($node->getPrototype(), $path . '.<' . ($node->getKeyAttribute() ?? 'item') . '>');
    }
};
$walk((new Configuration())->getConfigTreeBuilder()->buildTree(), '');
foreach (array_diff(array_keys($notes), $seen) as $stale) {
    throw new RuntimeException('Stale configuration note: ' . $stale);
}
$lines[] = '';
$lines[] = 'Zero disables each `limits.*` guard. Pagination default/max size and Atomic max operations must be positive. Cache duration zero means zero seconds, not unlimited. Native defaults preserve legacy linkage/fallback/to-many sorting; production applications should select `when_included`/`never`, `reject` fallback and explicit collection sort handlers where needed. See [production policies](../guide/production-policies.md).';
$lines[] = '';
$document = implode("\n", $lines);
$target = __DIR__ . '/../docs/reference/configuration.md';
if (in_array('--check', $argv, true)) {
    if (!is_file($target) || file_get_contents($target) !== $document) {
        throw new RuntimeException('Configuration reference is stale; regenerate it.');
    }
    echo "Configuration reference matches the active tree.\n";
} else {
    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0777, true);
    }
    file_put_contents($target, $document);
    echo "Configuration reference generated.\n";
}

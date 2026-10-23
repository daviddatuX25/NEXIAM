<?php

declare(strict_types=1);

namespace App\Assessment\Support;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class RawMetricFormulaEngine
{
    public const IDENTITY = 'nexiam.raw-formula.v1';

    private const MAX_FORMULA_LENGTH = 2048;

    private const MAX_NODES = 256;

    private const MAX_DEPTH = 32;

    private const MAX_DECIMAL_DIGITS = 64;

    private const MAX_RATIONAL_DIGITS = 256;

    /** @param list<string> $allowedVariables
     * @return array<string, mixed>
     */
    public function compile(string $expression, array $allowedVariables): array
    {
        if (strlen($expression) > self::MAX_FORMULA_LENGTH) {
            throw new InvalidArgumentException('Formula exceeds the 2048 character limit.');
        }

        $source = trim($expression);
        if (str_starts_with($source, '=')) {
            $source = trim(substr($source, 1));
        }
        if ($source === '') {
            throw new InvalidArgumentException('Enter a formula.');
        }

        $allowed = [];
        $hyphenatedCodes = [];
        foreach ($allowedVariables as $variable) {
            if (! is_string($variable)) {
                throw new InvalidArgumentException('Allowed formula variables must be metric codes.');
            }
            if (str_contains($variable, '-')) {
                $hyphenatedCodes[] = $variable;

                continue;
            }
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $variable)) {
                throw new InvalidArgumentException("Metric code '{$variable}' cannot be used as a formula variable.");
            }
            $allowed[strtoupper($variable)] = true;
        }

        $compactSource = strtoupper(preg_replace('/\s+/', '', $source) ?? $source);
        foreach ($hyphenatedCodes as $code) {
            if (str_contains($compactSource, strtoupper($code))) {
                throw new InvalidArgumentException("Metric code '{$code}' contains a hyphen and cannot be referenced in formulas; use a code with letters, digits, or underscores only.");
            }
        }

        $ast = (new RawMetricFormulaParser($source, $allowed))->parse();
        $this->validatedVariables($ast);

        return $ast;
    }

    /** @param array<string, mixed> $ast
     * @param  array<string, string>  $values
     */
    public function evaluate(array $ast, array $values, int $scale = 6): string
    {
        $this->assertScale($scale);
        $this->validatedVariables($ast);
        $normalizedValues = $this->normalizeInputMap($values);
        $result = $this->evaluateNode($ast, $normalizedValues);

        return (string) $result->toScale($scale, RoundingMode::HalfUp);
    }

    /** @param array<string, mixed> $ast
     * @param  array<string, array{min: string, max: string}>  $ranges
     * @return array{min: string, max: string}
     */
    public function bounds(array $ast, array $ranges, int $scale = 6): array
    {
        $this->assertScale($scale);
        $this->validatedVariables($ast);
        $normalizedRanges = $this->normalizeInputMap($ranges);
        [$minimum, $maximum] = $this->rangeNode($ast, $normalizedRanges);

        return [
            'min' => (string) $minimum->toScale($scale, RoundingMode::Floor),
            'max' => (string) $maximum->toScale($scale, RoundingMode::Ceiling),
        ];
    }

    /** @param array<string, mixed> $ast
     * @return list<string>
     */
    public function variables(array $ast): array
    {
        $variables = $this->validatedVariables($ast);
        $result = array_keys($variables);
        sort($result, SORT_STRING);

        return $result;
    }

    private function assertScale(int $scale): void
    {
        if ($scale < 0 || $scale > 6) {
            throw new InvalidArgumentException('Formula precision must be between 0 and 6 decimal places.');
        }
    }

    /** @param array<string, mixed> $ast
     * @return array<string, true>
     */
    private function validatedVariables(array $ast): array
    {
        $variables = [];
        $nodes = 0;
        $this->validateNode($ast, 1, $nodes, $variables);

        return $variables;
    }

    /** @param array<string, mixed> $node
     * @param  array<string, true>  $variables
     */
    private function validateNode(array $node, int $depth, int &$nodes, array &$variables): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException('Formula exceeds the 32-level nesting limit.');
        }
        if (++$nodes > self::MAX_NODES) {
            throw new InvalidArgumentException('Formula exceeds the 256-node limit.');
        }
        $type = $node['type'] ?? null;
        if (! is_string($type)) {
            throw new InvalidArgumentException('Formula AST node has no valid type.');
        }

        if ($type === 'number') {
            $this->assertNodeKeys($node, ['type', 'value']);
            $value = $node['value'] ?? null;
            if (! is_string($value) || $this->canonicalLiteral($value) !== $value) {
                throw new InvalidArgumentException('Formula AST contains a malformed number literal.');
            }

            return;
        }
        if ($type === 'variable') {
            $this->assertNodeKeys($node, ['type', 'name']);
            $name = $node['name'] ?? null;
            if (! is_string($name) || ! preg_match('/^[A-Z_][A-Z0-9_]*$/D', $name)) {
                throw new InvalidArgumentException('Formula AST contains a malformed variable name.');
            }
            $variables[$name] = true;

            return;
        }
        if ($type === 'unary') {
            $this->assertNodeKeys($node, ['type', 'operator', 'operand']);
            if (! in_array($node['operator'] ?? null, ['+', '-'], true) || ! is_array($node['operand'] ?? null)) {
                throw new InvalidArgumentException('Formula AST contains an invalid unary operation.');
            }
            $this->validateNode($node['operand'], $depth + 1, $nodes, $variables);

            return;
        }
        if ($type === 'binary') {
            $this->assertNodeKeys($node, ['type', 'operator', 'left', 'right']);
            if (! in_array($node['operator'] ?? null, ['+', '-', '*', '/'], true)
                || ! is_array($node['left'] ?? null)
                || ! is_array($node['right'] ?? null)) {
                throw new InvalidArgumentException('Formula AST contains an invalid binary operation.');
            }
            $this->validateNode($node['left'], $depth + 1, $nodes, $variables);
            $this->validateNode($node['right'], $depth + 1, $nodes, $variables);

            return;
        }
        if ($type === 'call') {
            $this->assertNodeKeys($node, ['type', 'name', 'arguments']);
            $name = $node['name'] ?? null;
            $arguments = $node['arguments'] ?? null;
            if (! is_string($name) || ! in_array($name, ['SUM', 'AVERAGE', 'MIN', 'MAX', 'ROUND'], true)
                || ! is_array($arguments) || ! array_is_list($arguments)) {
                throw new InvalidArgumentException('Formula AST contains an invalid function call.');
            }
            if (($name === 'ROUND' && count($arguments) !== 2)
                || ($name !== 'ROUND' && count($arguments) < 1)) {
                throw new InvalidArgumentException("{$name} has an invalid number of arguments.");
            }
            foreach ($arguments as $argument) {
                if (! is_array($argument)) {
                    throw new InvalidArgumentException('Formula AST function arguments must be nodes.');
                }
                $this->validateNode($argument, $depth + 1, $nodes, $variables);
            }
            if ($name === 'ROUND') {
                $this->roundScale($arguments[1]);
            }

            return;
        }

        throw new InvalidArgumentException('Formula AST contains an unsupported node type.');
    }

    /** @param array<string, mixed> $node
     * @param  list<string>  $expected
     */
    private function assertNodeKeys(array $node, array $expected): void
    {
        $keys = array_keys($node);
        sort($keys);
        sort($expected);
        if ($keys !== $expected) {
            throw new InvalidArgumentException('Formula AST node contains missing or unsupported fields.');
        }
    }

    private function canonicalLiteral(string $value): ?string
    {
        if (! preg_match('/^([0-9]+)(?:\.([0-9]+))?$/D', $value, $matches)) {
            return null;
        }
        if (strlen(str_replace('.', '', $value)) > self::MAX_DECIMAL_DIGITS) {
            return null;
        }
        $integer = ltrim($matches[1], '0');
        $integer = $integer === '' ? '0' : $integer;

        return isset($matches[2]) ? $integer.'.'.$matches[2] : $integer;
    }

    /** @param array<string, mixed> $node */
    private function roundScale(array $node): int
    {
        if (($node['type'] ?? null) !== 'number' || ! is_string($node['value'] ?? null)
            || ! preg_match('/^[0-6]$/D', $node['value'])) {
            throw new InvalidArgumentException('ROUND precision must be an integer literal from 0 to 6.');
        }

        return (int) $node['value'];
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function normalizeInputMap(array $values): array
    {
        $normalized = [];
        foreach ($values as $code => $value) {
            if (! is_string($code)) {
                continue;
            }
            $key = strtoupper($code);
            if (array_key_exists($key, $normalized)) {
                throw new InvalidArgumentException("Input code '{$code}' is duplicated without regard to case.");
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /** @param array<string, mixed> $values */
    private function evaluateNode(array $node, array $values): BigRational
    {
        return match ($node['type']) {
            'number' => $this->decimal($node['value']),
            'variable' => $this->valueFor($node['name'], $values),
            'unary' => $node['operator'] === '-' ? $this->bounded($this->evaluateNode($node['operand'], $values)->negated()) : $this->evaluateNode($node['operand'], $values),
            'binary' => $this->evaluateBinary($node, $values),
            'call' => $this->evaluateCall($node, $values),
        };
    }

    /** @param array<string, mixed> $node
     * @param  array<string, mixed>  $values
     */
    private function evaluateBinary(array $node, array $values): BigRational
    {
        $left = $this->evaluateNode($node['left'], $values);
        $right = $this->evaluateNode($node['right'], $values);
        if ($node['operator'] === '/' && $right->isZero()) {
            throw new InvalidArgumentException('Formula division by zero is not allowed.');
        }

        $result = match ($node['operator']) {
            '+' => $left->plus($right),
            '-' => $left->minus($right),
            '*' => $left->multipliedBy($right),
            '/' => $left->dividedBy($right),
        };

        return $this->bounded($result);
    }

    /** @param array<string, mixed> $node
     * @param  array<string, mixed>  $values
     */
    private function evaluateCall(array $node, array $values): BigRational
    {
        $name = $node['name'];
        $arguments = $node['arguments'];
        if ($name === 'ROUND') {
            $value = $this->evaluateNode($arguments[0], $values);
            $rounded = $value->toScale($this->roundScale($arguments[1]), RoundingMode::HalfUp);

            return $this->bounded(BigRational::of((string) $rounded));
        }

        $evaluated = array_map(fn (array $argument): BigRational => $this->evaluateNode($argument, $values), $arguments);
        if ($name === 'SUM' || $name === 'AVERAGE') {
            $sum = BigRational::zero();
            foreach ($evaluated as $value) {
                $sum = $this->bounded($sum->plus($value));
            }

            return $name === 'AVERAGE' ? $this->bounded($sum->dividedBy((string) count($evaluated))) : $sum;
        }

        $result = $evaluated[0];
        foreach (array_slice($evaluated, 1) as $value) {
            $comparison = $value->compareTo($result);
            if (($name === 'MIN' && $comparison < 0) || ($name === 'MAX' && $comparison > 0)) {
                $result = $value;
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $ranges
     * @return array{BigRational, BigRational}
     */
    private function rangeNode(array $node, array $ranges): array
    {
        if ($node['type'] === 'number') {
            $value = $this->decimal($node['value']);

            return [$value, $value];
        }
        if ($node['type'] === 'variable') {
            $range = $ranges[$node['name']] ?? null;
            if (! is_array($range) || ! is_string($range['min'] ?? null) || ! is_string($range['max'] ?? null)) {
                throw new InvalidArgumentException("Formula requires a valid range for variable '{$node['name']}'.");
            }
            $minimum = $this->decimal($range['min']);
            $maximum = $this->decimal($range['max']);
            if ($minimum->compareTo($maximum) > 0) {
                throw new InvalidArgumentException("Minimum range for '{$node['name']}' exceeds its maximum.");
            }

            return [$minimum, $maximum];
        }
        if ($node['type'] === 'unary') {
            [$minimum, $maximum] = $this->rangeNode($node['operand'], $ranges);

            return $node['operator'] === '-' ? [$this->bounded($maximum->negated()), $this->bounded($minimum->negated())] : [$minimum, $maximum];
        }
        if ($node['type'] === 'binary') {
            [$leftMin, $leftMax] = $this->rangeNode($node['left'], $ranges);
            [$rightMin, $rightMax] = $this->rangeNode($node['right'], $ranges);

            return match ($node['operator']) {
                '+' => [$this->bounded($leftMin->plus($rightMin)), $this->bounded($leftMax->plus($rightMax))],
                '-' => [$this->bounded($leftMin->minus($rightMax)), $this->bounded($leftMax->minus($rightMin))],
                '*' => $this->productRange($leftMin, $leftMax, $rightMin, $rightMax),
                '/' => $this->quotientRange($leftMin, $leftMax, $rightMin, $rightMax),
            };
        }

        return $this->rangeCall($node, $ranges);
    }

    /** @return array{BigRational, BigRational} */
    private function productRange(BigRational $leftMin, BigRational $leftMax, BigRational $rightMin, BigRational $rightMax): array
    {
        $products = [
            $this->bounded($leftMin->multipliedBy($rightMin)),
            $this->bounded($leftMin->multipliedBy($rightMax)),
            $this->bounded($leftMax->multipliedBy($rightMin)),
            $this->bounded($leftMax->multipliedBy($rightMax)),
        ];

        return $this->minimumAndMaximum($products);
    }

    /** @return array{BigRational, BigRational} */
    private function quotientRange(BigRational $leftMin, BigRational $leftMax, BigRational $rightMin, BigRational $rightMax): array
    {
        if ($rightMin->compareTo('0') <= 0 && $rightMax->compareTo('0') >= 0) {
            throw new InvalidArgumentException('Formula division is not allowed when the divisor range includes zero.');
        }
        $quotients = [
            $this->bounded($leftMin->dividedBy($rightMin)),
            $this->bounded($leftMin->dividedBy($rightMax)),
            $this->bounded($leftMax->dividedBy($rightMin)),
            $this->bounded($leftMax->dividedBy($rightMax)),
        ];

        return $this->minimumAndMaximum($quotients);
    }

    /** @param array<string, mixed> $node
     * @param  array<string, mixed>  $ranges
     * @return array{BigRational, BigRational}
     */
    private function rangeCall(array $node, array $ranges): array
    {
        $name = $node['name'];
        if ($name === 'ROUND') {
            [$minimum, $maximum] = $this->rangeNode($node['arguments'][0], $ranges);
            $digits = $this->roundScale($node['arguments'][1]);

            return [
                $this->bounded(BigRational::of((string) $minimum->toScale($digits, RoundingMode::HalfUp))),
                $this->bounded(BigRational::of((string) $maximum->toScale($digits, RoundingMode::HalfUp))),
            ];
        }

        $intervals = array_map(fn (array $argument): array => $this->rangeNode($argument, $ranges), $node['arguments']);
        if ($name === 'SUM' || $name === 'AVERAGE') {
            $minimum = BigRational::zero();
            $maximum = BigRational::zero();
            foreach ($intervals as [$lower, $upper]) {
                $minimum = $this->bounded($minimum->plus($lower));
                $maximum = $this->bounded($maximum->plus($upper));
            }
            if ($name === 'AVERAGE') {
                $count = (string) count($intervals);
                $minimum = $this->bounded($minimum->dividedBy($count));
                $maximum = $this->bounded($maximum->dividedBy($count));
            }

            return [$minimum, $maximum];
        }

        $lowers = array_column($intervals, 0);
        $uppers = array_column($intervals, 1);

        if ($name === 'MIN') {
            return [$this->minimumValue($lowers), $this->minimumValue($uppers)];
        }

        return [$this->maximumValue($lowers), $this->maximumValue($uppers)];
    }

    /** @param list<BigRational> $values
     * @return array{BigRational, BigRational}
     */
    private function minimumAndMaximum(array $values): array
    {
        return [$this->minimumValue($values), $this->maximumValue($values)];
    }

    /** @param list<BigRational> $values */
    private function minimumValue(array $values): BigRational
    {
        $result = $values[0];
        foreach (array_slice($values, 1) as $value) {
            if ($value->compareTo($result) < 0) {
                $result = $value;
            }
        }

        return $result;
    }

    /** @param list<BigRational> $values */
    private function maximumValue(array $values): BigRational
    {
        $result = $values[0];
        foreach (array_slice($values, 1) as $value) {
            if ($value->compareTo($result) > 0) {
                $result = $value;
            }
        }

        return $result;
    }

    private function valueFor(string $code, array $values): BigRational
    {
        if (! array_key_exists($code, $values) || ! is_string($values[$code])) {
            throw new InvalidArgumentException("Formula value for variable '{$code}' is missing or malformed.");
        }

        return $this->decimal($values[$code]);
    }

    private function decimal(string $value): BigRational
    {
        if (strlen($value) > self::MAX_DECIMAL_DIGITS + 2
            || ! preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $value)
            || strlen(str_replace(['-', '.'], '', $value)) > self::MAX_DECIMAL_DIGITS) {
            throw new InvalidArgumentException('Formula values must be bounded, non-exponential decimal strings.');
        }

        return $this->bounded(BigRational::of($value));
    }

    private function bounded(BigRational $value): BigRational
    {
        if (strlen($value->getNumerator()->abs()->toString()) > self::MAX_RATIONAL_DIGITS
            || strlen($value->getDenominator()->toString()) > self::MAX_RATIONAL_DIGITS) {
            throw new InvalidArgumentException('Formula arithmetic exceeds the bounded exact-value limit.');
        }

        return $value;
    }
}

/** @internal */
final class RawMetricFormulaParser
{
    private const MAX_NODES = 256;

    private const MAX_DEPTH = 32;

    /** @var list<array{kind: string, value: string}> */
    private array $tokens = [];

    private int $position = 0;

    private int $nodes = 0;

    /** @param array<string, true> $allowedVariables */
    public function __construct(string $expression, private readonly array $allowedVariables)
    {
        $this->tokenize($expression);
    }

    /** @return array<string, mixed> */
    public function parse(): array
    {
        $ast = $this->parseExpression();
        if ($this->position !== count($this->tokens)) {
            throw new InvalidArgumentException('Formula contains unexpected trailing input.');
        }

        return $ast;
    }

    private function tokenize(string $expression): void
    {
        $length = strlen($expression);
        for ($index = 0; $index < $length;) {
            $character = $expression[$index];
            if (str_contains(" \t\n\r\f\v", $character)) {
                $index++;

                continue;
            }
            if ($character >= '0' && $character <= '9') {
                $start = $index++;
                while ($index < $length && $expression[$index] >= '0' && $expression[$index] <= '9') {
                    $index++;
                }
                if ($index < $length && $expression[$index] === '.') {
                    $index++;
                    $fractionStart = $index;
                    while ($index < $length && $expression[$index] >= '0' && $expression[$index] <= '9') {
                        $index++;
                    }
                    if ($index === $fractionStart) {
                        throw new InvalidArgumentException('Formula decimal literals need digits after the decimal point.');
                    }
                }
                $literal = substr($expression, $start, $index - $start);
                if (strlen(str_replace('.', '', $literal)) > 64) {
                    throw new InvalidArgumentException('Formula decimal literals may contain at most 64 digits.');
                }
                $this->tokens[] = ['kind' => 'number', 'value' => $this->normalizeLiteral($literal)];

                continue;
            }
            if (($character >= 'a' && $character <= 'z') || ($character >= 'A' && $character <= 'Z') || $character === '_') {
                $start = $index++;
                while ($index < $length) {
                    $next = $expression[$index];
                    if (! (($next >= 'a' && $next <= 'z') || ($next >= 'A' && $next <= 'Z') || ($next >= '0' && $next <= '9') || $next === '_')) {
                        break;
                    }
                    $index++;
                }
                $this->tokens[] = ['kind' => 'identifier', 'value' => strtoupper(substr($expression, $start, $index - $start))];

                continue;
            }
            if (str_contains('+-*/(),', $character)) {
                $this->tokens[] = ['kind' => 'symbol', 'value' => $character];
                $index++;

                continue;
            }

            throw new InvalidArgumentException("Unsupported formula character '{$character}'.");
        }
    }

    private function normalizeLiteral(string $literal): string
    {
        [$integer, $fraction] = array_pad(explode('.', $literal, 2), 2, null);
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;

        return $fraction === null ? $integer : $integer.'.'.$fraction;
    }

    /** @return array<string, mixed> */
    private function parseExpression(int $depth = 1): array
    {
        $node = $this->parseTerm($depth);
        while ($this->peek('+') || $this->peek('-')) {
            $operator = $this->consume()['value'];
            $node = $this->make(['type' => 'binary', 'operator' => $operator, 'left' => $node, 'right' => $this->parseTerm($depth)]);
        }

        return $node;
    }

    /** @return array<string, mixed> */
    private function parseTerm(int $depth): array
    {
        $node = $this->parseUnary($depth);
        while ($this->peek('*') || $this->peek('/')) {
            $operator = $this->consume()['value'];
            $node = $this->make(['type' => 'binary', 'operator' => $operator, 'left' => $node, 'right' => $this->parseUnary($depth)]);
        }

        return $node;
    }

    /** @return array<string, mixed> */
    private function parseUnary(int $depth): array
    {
        if ($this->peek('+') || $this->peek('-')) {
            if ($depth + 1 > self::MAX_DEPTH) {
                throw new InvalidArgumentException('Formula exceeds the 32-level nesting limit.');
            }
            $operator = $this->consume()['value'];

            return $this->make(['type' => 'unary', 'operator' => $operator, 'operand' => $this->parseUnary($depth + 1)]);
        }

        return $this->parsePrimary($depth);
    }

    /** @return array<string, mixed> */
    private function parsePrimary(int $depth): array
    {
        $token = $this->tokens[$this->position] ?? null;
        if ($token === null) {
            throw new InvalidArgumentException('Formula ended where a value was expected.');
        }
        if ($token['kind'] === 'number') {
            $this->position++;

            return $this->make(['type' => 'number', 'value' => $token['value']]);
        }
        if ($token['kind'] === 'identifier') {
            $this->position++;
            $name = $token['value'];
            if ($this->peek('(')) {
                if (! in_array($name, ['SUM', 'AVERAGE', 'AVG', 'MIN', 'MAX', 'ROUND'], true)) {
                    throw new InvalidArgumentException("Unknown formula function '{$name}'.");
                }
                if ($depth + 1 > self::MAX_DEPTH) {
                    throw new InvalidArgumentException('Formula exceeds the 32-level nesting limit.');
                }
                $this->position++;
                $arguments = [];
                if (! $this->peek(')')) {
                    do {
                        $arguments[] = $this->parseExpression($depth + 1);
                        if (! $this->peek(',')) {
                            break;
                        }
                        $this->position++;
                    } while (true);
                }
                $this->expect(')');
                $name = $name === 'AVG' ? 'AVERAGE' : $name;
                if (($name === 'ROUND' && count($arguments) !== 2)
                    || ($name !== 'ROUND' && count($arguments) < 1)) {
                    throw new InvalidArgumentException("{$name} has an invalid number of arguments.");
                }
                $call = $this->make(['type' => 'call', 'name' => $name, 'arguments' => $arguments]);
                if ($name === 'ROUND') {
                    $argument = $arguments[1] ?? [];
                    if (($argument['type'] ?? null) !== 'number' || ! preg_match('/^[0-6]$/D', $argument['value'] ?? '')) {
                        throw new InvalidArgumentException('ROUND precision must be an integer literal from 0 to 6.');
                    }
                }

                return $call;
            }
            if (! isset($this->allowedVariables[$name])) {
                throw new InvalidArgumentException("Unknown formula variable '{$name}'.");
            }

            return $this->make(['type' => 'variable', 'name' => $name]);
        }
        if ($this->peek('(')) {
            if ($depth + 1 > self::MAX_DEPTH) {
                throw new InvalidArgumentException('Formula exceeds the 32-level nesting limit.');
            }
            $this->position++;
            $node = $this->parseExpression($depth + 1);
            $this->expect(')');

            return $node;
        }

        throw new InvalidArgumentException("Unexpected '{$token['value']}' where a formula value was expected.");
    }

    /** @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function make(array $node): array
    {
        if (++$this->nodes > self::MAX_NODES) {
            throw new InvalidArgumentException('Formula exceeds the 256-node limit.');
        }

        return $node;
    }

    private function peek(string $value): bool
    {
        return ($this->tokens[$this->position]['value'] ?? null) === $value;
    }

    /** @return array{kind: string, value: string} */
    private function consume(): array
    {
        return $this->tokens[$this->position++];
    }

    private function expect(string $value): void
    {
        if (! $this->peek($value)) {
            throw new InvalidArgumentException("Formula is missing '{$value}'.");
        }
        $this->position++;
    }
}

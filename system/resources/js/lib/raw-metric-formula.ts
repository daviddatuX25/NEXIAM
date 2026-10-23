export type RawMetricFormulaAst =
    | { type: 'number'; value: string }
    | { type: 'variable'; name: string }
    | { type: 'unary'; operator: '+' | '-'; operand: RawMetricFormulaAst }
    | {
          type: 'binary';
          operator: '+' | '-' | '*' | '/';
          left: RawMetricFormulaAst;
          right: RawMetricFormulaAst;
      }
    | {
          type: 'call';
          name: 'SUM' | 'AVERAGE' | 'MIN' | 'MAX' | 'ROUND';
          arguments: RawMetricFormulaAst[];
      };

type Token = { kind: 'number' | 'identifier' | 'symbol'; value: string };
type Rational = { numerator: bigint; denominator: bigint };
type Interval = [Rational, Rational];

const MAX_FORMULA_LENGTH = 2048;
const MAX_NODES = 256;
const MAX_DEPTH = 32;
const MAX_DECIMAL_DIGITS = 64;
const MAX_RATIONAL_DIGITS = 256;

export function compileFormula(
    expression: string,
    allowedVariables: string[],
): RawMetricFormulaAst {
    if (expression.length > MAX_FORMULA_LENGTH) {
        throw new Error('Formula exceeds the 2048 character limit.');
    }

    let source = expression.trim();
    if (source.startsWith('=')) source = source.slice(1).trim();
    if (source.length === 0) throw new Error('Enter a formula.');

    const allowed = new Set<string>();
    const hyphenatedCodes: string[] = [];
    for (const variable of allowedVariables) {
        if (typeof variable !== 'string')
            throw new Error('Allowed formula variables must be metric codes.');
        if (variable.includes('-')) {
            hyphenatedCodes.push(variable);
            continue;
        }
        const variableMatch = /^[A-Za-z_][A-Za-z0-9_]*$/.exec(variable);
        if (!variableMatch || variableMatch[0] !== variable) {
            throw new Error(
                `Metric code '${variable}' cannot be used as a formula variable.`,
            );
        }
        allowed.add(variable.toUpperCase());
    }

    const compactSource = source.replace(/\s+/g, '').toUpperCase();
    for (const code of hyphenatedCodes) {
        if (compactSource.includes(code.toUpperCase())) {
            throw new Error(
                `Metric code '${code}' contains a hyphen and cannot be referenced in formulas; use a code with letters, digits, or underscores only.`,
            );
        }
    }

    const ast = new FormulaParser(source, allowed).parse();
    validateAst(ast);
    return ast;
}

export function evaluateFormula(
    ast: RawMetricFormulaAst,
    values: Record<string, string>,
    scale = 6,
): string {
    assertScale(scale);
    validateAst(ast);
    const normalizedValues = normalizeInputMap(values);
    return fixedDecimal(evaluateNode(ast, normalizedValues), scale, 'half-up');
}

export function formulaBounds(
    ast: RawMetricFormulaAst,
    ranges: Record<string, { min: string; max: string }>,
    scale = 6,
): { min: string; max: string } {
    assertScale(scale);
    validateAst(ast);
    const interval = rangeNode(ast, normalizeInputMap(ranges));
    return {
        min: fixedDecimal(interval[0], scale, 'floor'),
        max: fixedDecimal(interval[1], scale, 'ceil'),
    };
}

export function formulaVariables(ast: RawMetricFormulaAst): string[] {
    const variables = new Set<string>();
    validateAst(ast, variables);
    return [...variables].sort();
}

function assertScale(scale: number): void {
    if (!Number.isInteger(scale) || scale < 0 || scale > 6) {
        throw new Error(
            'Formula precision must be between 0 and 6 decimal places.',
        );
    }
}

function validateAst(
    ast: unknown,
    variables = new Set<string>(),
): asserts ast is RawMetricFormulaAst {
    let nodes = 0;
    const visit = (node: unknown, depth: number): void => {
        if (depth > MAX_DEPTH)
            throw new Error('Formula exceeds the 32-level nesting limit.');
        if (++nodes > MAX_NODES)
            throw new Error('Formula exceeds the 256-node limit.');
        if (!isRecord(node) || typeof node.type !== 'string')
            throw new Error('Formula AST node has no valid type.');

        if (node.type === 'number') {
            assertKeys(node, ['type', 'value']);
            if (
                typeof node.value !== 'string' ||
                canonicalLiteral(node.value) !== node.value
            ) {
                throw new Error(
                    'Formula AST contains a malformed number literal.',
                );
            }
            return;
        }
        if (node.type === 'variable') {
            assertKeys(node, ['type', 'name']);
            if (typeof node.name !== 'string') {
                throw new Error(
                    'Formula AST contains a malformed variable name.',
                );
            }
            const nameMatch = /^[A-Z_][A-Z0-9_]*$/.exec(node.name);
            if (!nameMatch || nameMatch[0] !== node.name) {
                throw new Error(
                    'Formula AST contains a malformed variable name.',
                );
            }
            variables.add(node.name);
            return;
        }
        if (node.type === 'unary') {
            assertKeys(node, ['type', 'operator', 'operand']);
            if (
                (node.operator !== '+' && node.operator !== '-') ||
                !isRecord(node.operand)
            ) {
                throw new Error(
                    'Formula AST contains an invalid unary operation.',
                );
            }
            visit(node.operand, depth + 1);
            return;
        }
        if (node.type === 'binary') {
            assertKeys(node, ['type', 'operator', 'left', 'right']);
            if (
                !['+', '-', '*', '/'].includes(node.operator as string) ||
                !isRecord(node.left) ||
                !isRecord(node.right)
            ) {
                throw new Error(
                    'Formula AST contains an invalid binary operation.',
                );
            }
            visit(node.left, depth + 1);
            visit(node.right, depth + 1);
            return;
        }
        if (node.type === 'call') {
            assertKeys(node, ['type', 'name', 'arguments']);
            if (
                typeof node.name !== 'string' ||
                !['SUM', 'AVERAGE', 'MIN', 'MAX', 'ROUND'].includes(
                    node.name,
                ) ||
                !Array.isArray(node.arguments)
            ) {
                throw new Error(
                    'Formula AST contains an invalid function call.',
                );
            }
            const name = node.name;
            const argumentsList = node.arguments;
            if (
                (name === 'ROUND' && argumentsList.length !== 2) ||
                (name !== 'ROUND' && argumentsList.length < 1)
            ) {
                throw new Error(`${name} has an invalid number of arguments.`);
            }
            for (const argument of argumentsList) {
                if (!isRecord(argument))
                    throw new Error(
                        'Formula AST function arguments must be nodes.',
                    );
                visit(argument, depth + 1);
            }
            if (name === 'ROUND') roundScale(argumentsList[1]);
            return;
        }
        throw new Error('Formula AST contains an unsupported node type.');
    };

    visit(ast, 1);
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function assertKeys(node: Record<string, unknown>, expected: string[]): void {
    const actual = Object.keys(node).sort();
    const canonicalExpected = [...expected].sort();
    if (
        actual.length !== canonicalExpected.length ||
        actual.some((key, index) => key !== canonicalExpected[index])
    ) {
        throw new Error(
            'Formula AST node contains missing or unsupported fields.',
        );
    }
}

function canonicalLiteral(value: string): string | null {
    const match = /^([0-9]+)(?:\.([0-9]+))?$/.exec(value);
    if (
        !match ||
        match[0] !== value ||
        value.replace('.', '').length > MAX_DECIMAL_DIGITS
    )
        return null;
    const integer = match[1].replace(/^0+/, '') || '0';
    return match[2] === undefined ? integer : `${integer}.${match[2]}`;
}

function roundScale(node: unknown): number {
    if (
        !isRecord(node) ||
        node.type !== 'number' ||
        typeof node.value !== 'string' ||
        !['0', '1', '2', '3', '4', '5', '6'].includes(node.value)
    ) {
        throw new Error(
            'ROUND precision must be an integer literal from 0 to 6.',
        );
    }
    return node.value.charCodeAt(0) - 48;
}

function normalizeInputMap(
    values: Record<string, unknown>,
): Map<string, unknown> {
    const normalized = new Map<string, unknown>();
    if (!isRecord(values))
        throw new Error('Formula inputs must be a code-to-decimal map.');
    for (const [code, value] of Object.entries(values)) {
        const key = code.toUpperCase();
        if (normalized.has(key))
            throw new Error(
                `Input code '${code}' is duplicated without regard to case.`,
            );
        normalized.set(key, value);
    }
    return normalized;
}

function evaluateNode(
    node: RawMetricFormulaAst,
    values: Map<string, unknown>,
): Rational {
    switch (node.type) {
        case 'number':
            return decimal(node.value);
        case 'variable': {
            const value = values.get(node.name);
            if (typeof value !== 'string')
                throw new Error(
                    `Formula value for variable '${node.name}' is missing or malformed.`,
                );
            return decimal(value);
        }
        case 'unary': {
            const value = evaluateNode(node.operand, values);
            return node.operator === '-'
                ? bounded(rational(-value.numerator, value.denominator))
                : value;
        }
        case 'binary': {
            const left = evaluateNode(node.left, values);
            const right = evaluateNode(node.right, values);
            if (node.operator === '/' && right.numerator === 0n)
                throw new Error('Formula division by zero is not allowed.');
            if (node.operator === '+')
                return bounded(
                    rational(
                        left.numerator * right.denominator +
                            right.numerator * left.denominator,
                        left.denominator * right.denominator,
                    ),
                );
            if (node.operator === '-')
                return bounded(
                    rational(
                        left.numerator * right.denominator -
                            right.numerator * left.denominator,
                        left.denominator * right.denominator,
                    ),
                );
            if (node.operator === '*')
                return bounded(
                    rational(
                        left.numerator * right.numerator,
                        left.denominator * right.denominator,
                    ),
                );
            return bounded(
                rational(
                    left.numerator * right.denominator,
                    left.denominator * right.numerator,
                ),
            );
        }
        case 'call':
            return evaluateCall(node, values);
    }
}

function evaluateCall(
    node: Extract<RawMetricFormulaAst, { type: 'call' }>,
    values: Map<string, unknown>,
): Rational {
    if (node.name === 'ROUND') {
        return roundedRational(
            evaluateNode(node.arguments[0], values),
            roundScale(node.arguments[1]),
        );
    }
    const evaluated = node.arguments.map((argument) =>
        evaluateNode(argument, values),
    );
    if (node.name === 'SUM' || node.name === 'AVERAGE') {
        let sum = rational(0n);
        for (const value of evaluated)
            sum = bounded(
                rational(
                    sum.numerator * value.denominator +
                        value.numerator * sum.denominator,
                    sum.denominator * value.denominator,
                ),
            );
        return node.name === 'AVERAGE'
            ? bounded(
                  rational(
                      sum.numerator,
                      sum.denominator * BigInt(evaluated.length),
                  ),
              )
            : sum;
    }
    return evaluated.slice(1).reduce((result, value) => {
        const comparison = compare(value, result);
        return (node.name === 'MIN' && comparison < 0) ||
            (node.name === 'MAX' && comparison > 0)
            ? value
            : result;
    }, evaluated[0]);
}

function rangeNode(
    node: RawMetricFormulaAst,
    ranges: Map<string, unknown>,
): Interval {
    if (node.type === 'number') {
        const value = decimal(node.value);
        return [value, value];
    }
    if (node.type === 'variable') {
        const range = ranges.get(node.name);
        if (
            !isRecord(range) ||
            typeof range.min !== 'string' ||
            typeof range.max !== 'string'
        ) {
            throw new Error(
                `Formula requires a valid range for variable '${node.name}'.`,
            );
        }
        const minimum = decimal(range.min);
        const maximum = decimal(range.max);
        if (compare(minimum, maximum) > 0)
            throw new Error(
                `Minimum range for '${node.name}' exceeds its maximum.`,
            );
        return [minimum, maximum];
    }
    if (node.type === 'unary') {
        const [minimum, maximum] = rangeNode(node.operand, ranges);
        return node.operator === '-'
            ? [
                  bounded(rational(-maximum.numerator, maximum.denominator)),
                  bounded(rational(-minimum.numerator, minimum.denominator)),
              ]
            : [minimum, maximum];
    }
    if (node.type === 'binary') {
        const [leftMin, leftMax] = rangeNode(node.left, ranges);
        const [rightMin, rightMax] = rangeNode(node.right, ranges);
        if (node.operator === '+')
            return [add(leftMin, rightMin), add(leftMax, rightMax)];
        if (node.operator === '-')
            return [subtract(leftMin, rightMax), subtract(leftMax, rightMin)];
        if (node.operator === '*')
            return productRange(leftMin, leftMax, rightMin, rightMax);
        return quotientRange(leftMin, leftMax, rightMin, rightMax);
    }
    return rangeCall(node, ranges);
}

function rangeCall(
    node: Extract<RawMetricFormulaAst, { type: 'call' }>,
    ranges: Map<string, unknown>,
): Interval {
    if (node.name === 'ROUND') {
        const [minimum, maximum] = rangeNode(node.arguments[0], ranges);
        const digits = roundScale(node.arguments[1]);
        return [
            roundedRational(minimum, digits),
            roundedRational(maximum, digits),
        ];
    }
    const intervals = node.arguments.map((argument) =>
        rangeNode(argument, ranges),
    );
    if (node.name === 'SUM' || node.name === 'AVERAGE') {
        let minimum = rational(0n);
        let maximum = rational(0n);
        for (const [lower, upper] of intervals) {
            minimum = add(minimum, lower);
            maximum = add(maximum, upper);
        }
        if (node.name === 'AVERAGE') {
            const count = BigInt(intervals.length);
            minimum = bounded(
                rational(minimum.numerator, minimum.denominator * count),
            );
            maximum = bounded(
                rational(maximum.numerator, maximum.denominator * count),
            );
        }
        return [minimum, maximum];
    }
    const lowers = intervals.map(([lower]) => lower);
    const uppers = intervals.map(([, upper]) => upper);
    return node.name === 'MIN'
        ? [minimum(lowers), minimum(uppers)]
        : [maximum(lowers), maximum(uppers)];
}

function productRange(
    leftMin: Rational,
    leftMax: Rational,
    rightMin: Rational,
    rightMax: Rational,
): Interval {
    return extrema([
        multiply(leftMin, rightMin),
        multiply(leftMin, rightMax),
        multiply(leftMax, rightMin),
        multiply(leftMax, rightMax),
    ]);
}

function quotientRange(
    leftMin: Rational,
    leftMax: Rational,
    rightMin: Rational,
    rightMax: Rational,
): Interval {
    if (
        compare(rightMin, rational(0n)) <= 0 &&
        compare(rightMax, rational(0n)) >= 0
    ) {
        throw new Error(
            'Formula division is not allowed when the divisor range includes zero.',
        );
    }
    return extrema([
        divide(leftMin, rightMin),
        divide(leftMin, rightMax),
        divide(leftMax, rightMin),
        divide(leftMax, rightMax),
    ]);
}

function extrema(values: Rational[]): Interval {
    return [minimum(values), maximum(values)];
}

function minimum(values: Rational[]): Rational {
    return values
        .slice(1)
        .reduce(
            (result, value) => (compare(value, result) < 0 ? value : result),
            values[0],
        );
}

function maximum(values: Rational[]): Rational {
    return values
        .slice(1)
        .reduce(
            (result, value) => (compare(value, result) > 0 ? value : result),
            values[0],
        );
}

function add(left: Rational, right: Rational): Rational {
    return bounded(
        rational(
            left.numerator * right.denominator +
                right.numerator * left.denominator,
            left.denominator * right.denominator,
        ),
    );
}

function subtract(left: Rational, right: Rational): Rational {
    return bounded(
        rational(
            left.numerator * right.denominator -
                right.numerator * left.denominator,
            left.denominator * right.denominator,
        ),
    );
}

function multiply(left: Rational, right: Rational): Rational {
    return bounded(
        rational(
            left.numerator * right.numerator,
            left.denominator * right.denominator,
        ),
    );
}

function divide(left: Rational, right: Rational): Rational {
    if (right.numerator === 0n)
        throw new Error('Formula division by zero is not allowed.');
    return bounded(
        rational(
            left.numerator * right.denominator,
            left.denominator * right.numerator,
        ),
    );
}

function compare(left: Rational, right: Rational): -1 | 0 | 1 {
    const difference =
        left.numerator * right.denominator - right.numerator * left.denominator;
    return difference < 0n ? -1 : difference > 0n ? 1 : 0;
}

function roundedRational(value: Rational, scale: number): Rational {
    const rounded = fixedDecimal(value, scale, 'half-up');
    return bounded(
        rational(BigInt(rounded.replace('.', '')), 10n ** BigInt(scale)),
    );
}

function decimal(value: string): Rational {
    const match = /^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/.exec(value);
    if (
        value.length > MAX_DECIMAL_DIGITS + 2 ||
        !match ||
        match[0] !== value ||
        value.replace(/[-.]/g, '').length > MAX_DECIMAL_DIGITS
    ) {
        throw new Error(
            'Formula values must be bounded, non-exponential decimal strings.',
        );
    }
    const negative = value.startsWith('-');
    const unsigned = negative ? value.slice(1) : value;
    const [integer, fraction = ''] = unsigned.split('.');
    const denominator = 10n ** BigInt(fraction.length);
    const numerator = BigInt(`${integer}${fraction}`) * (negative ? -1n : 1n);
    return bounded(rational(numerator, denominator));
}

function rational(numerator: bigint, denominator = 1n): Rational {
    if (denominator === 0n)
        throw new Error('Formula division by zero is not allowed.');
    const sign = denominator < 0n ? -1n : 1n;
    let top = numerator * sign;
    let bottom = denominator * sign;
    const divisor = gcd(top < 0n ? -top : top, bottom);
    top /= divisor;
    bottom /= divisor;
    return { numerator: top, denominator: bottom };
}

function gcd(left: bigint, right: bigint): bigint {
    while (right !== 0n) [left, right] = [right, left % right];
    return left === 0n ? 1n : left;
}

function bounded(value: Rational): Rational {
    if (
        (value.numerator < 0n ? -value.numerator : value.numerator).toString()
            .length > MAX_RATIONAL_DIGITS ||
        value.denominator.toString().length > MAX_RATIONAL_DIGITS
    ) {
        throw new Error(
            'Formula arithmetic exceeds the bounded exact-value limit.',
        );
    }
    return value;
}

type Rounding = 'half-up' | 'floor' | 'ceil';

function fixedDecimal(value: Rational, scale: number, mode: Rounding): string {
    const factor = 10n ** BigInt(scale);
    const scaledNumerator = value.numerator * factor;
    let quotient = scaledNumerator / value.denominator;
    const remainder = scaledNumerator % value.denominator;
    if (
        mode === 'half-up' &&
        (remainder < 0n ? -remainder : remainder) * 2n >= value.denominator
    ) {
        quotient += scaledNumerator < 0n ? -1n : 1n;
    } else if (mode === 'floor' && scaledNumerator < 0n && remainder !== 0n) {
        quotient -= 1n;
    } else if (mode === 'ceil' && scaledNumerator > 0n && remainder !== 0n) {
        quotient += 1n;
    }

    const negative = quotient < 0n;
    const digits = (negative ? -quotient : quotient)
        .toString()
        .padStart(scale + 1, '0');
    if (scale === 0) return `${negative ? '-' : ''}${digits}`;
    return `${negative ? '-' : ''}${digits.slice(0, -scale)}.${digits.slice(-scale)}`;
}

class FormulaParser {
    private readonly tokens: Token[] = [];
    private position = 0;
    private nodes = 0;

    public constructor(
        expression: string,
        private readonly allowedVariables: Set<string>,
    ) {
        this.tokenize(expression);
    }

    public parse(): RawMetricFormulaAst {
        const ast = this.parseExpression();
        if (this.position !== this.tokens.length)
            throw new Error('Formula contains unexpected trailing input.');
        return ast;
    }

    private tokenize(expression: string): void {
        for (let index = 0; index < expression.length;) {
            const character = expression[index];
            if (' \t\n\r\f\v'.includes(character)) {
                index += 1;
                continue;
            }
            if (character >= '0' && character <= '9') {
                const start = index++;
                while (
                    index < expression.length &&
                    expression[index] >= '0' &&
                    expression[index] <= '9'
                )
                    index += 1;
                if (expression[index] === '.') {
                    index += 1;
                    const fractionStart = index;
                    while (
                        index < expression.length &&
                        expression[index] >= '0' &&
                        expression[index] <= '9'
                    )
                        index += 1;
                    if (index === fractionStart)
                        throw new Error(
                            'Formula decimal literals need digits after the decimal point.',
                        );
                }
                const literal = expression.slice(start, index);
                if (literal.replace('.', '').length > MAX_DECIMAL_DIGITS)
                    throw new Error(
                        'Formula decimal literals may contain at most 64 digits.',
                    );
                this.tokens.push({
                    kind: 'number',
                    value: normalizeLiteral(literal),
                });
                continue;
            }
            if (
                (character >= 'a' && character <= 'z') ||
                (character >= 'A' && character <= 'Z') ||
                character === '_'
            ) {
                const start = index++;
                while (index < expression.length) {
                    const next = expression[index];
                    if (!(
                        (next >= 'a' && next <= 'z') ||
                        (next >= 'A' && next <= 'Z') ||
                        (next >= '0' && next <= '9') ||
                        next === '_'
                    ))
                        break;
                    index += 1;
                }
                this.tokens.push({
                    kind: 'identifier',
                    value: expression.slice(start, index).toUpperCase(),
                });
                continue;
            }
            if ('+-*/(),'.includes(character)) {
                this.tokens.push({ kind: 'symbol', value: character });
                index += 1;
                continue;
            }
            throw new Error(`Unsupported formula character '${character}'.`);
        }
    }

    private parseExpression(depth = 1): RawMetricFormulaAst {
        let node = this.parseTerm(depth);
        while (this.peek('+') || this.peek('-')) {
            const operator = this.consume().value as '+' | '-';
            node = this.make({
                type: 'binary',
                operator,
                left: node,
                right: this.parseTerm(depth),
            });
        }
        return node;
    }

    private parseTerm(depth: number): RawMetricFormulaAst {
        let node = this.parseUnary(depth);
        while (this.peek('*') || this.peek('/')) {
            const operator = this.consume().value as '*' | '/';
            node = this.make({
                type: 'binary',
                operator,
                left: node,
                right: this.parseUnary(depth),
            });
        }
        return node;
    }

    private parseUnary(depth: number): RawMetricFormulaAst {
        if (this.peek('+') || this.peek('-')) {
            if (depth + 1 > MAX_DEPTH)
                throw new Error('Formula exceeds the 32-level nesting limit.');
            const operator = this.consume().value as '+' | '-';
            return this.make({
                type: 'unary',
                operator,
                operand: this.parseUnary(depth + 1),
            });
        }
        return this.parsePrimary(depth);
    }

    private parsePrimary(depth: number): RawMetricFormulaAst {
        const token = this.tokens[this.position];
        if (!token)
            throw new Error('Formula ended where a value was expected.');
        if (token.kind === 'number') {
            this.position += 1;
            return this.make({ type: 'number', value: token.value });
        }
        if (token.kind === 'identifier') {
            this.position += 1;
            const name = token.value;
            if (this.peek('(')) return this.parseCall(name, depth);
            if (!this.allowedVariables.has(name))
                throw new Error(`Unknown formula variable '${name}'.`);
            return this.make({ type: 'variable', name });
        }
        if (this.peek('(')) {
            if (depth + 1 > MAX_DEPTH)
                throw new Error('Formula exceeds the 32-level nesting limit.');
            this.position += 1;
            const node = this.parseExpression(depth + 1);
            this.expect(')');
            return node;
        }
        throw new Error(
            `Unexpected '${token.value}' where a formula value was expected.`,
        );
    }

    private parseCall(name: string, depth: number): RawMetricFormulaAst {
        const canonicalName = name === 'AVG' ? 'AVERAGE' : name;
        if (
            canonicalName !== 'SUM' &&
            canonicalName !== 'AVERAGE' &&
            canonicalName !== 'MIN' &&
            canonicalName !== 'MAX' &&
            canonicalName !== 'ROUND'
        ) {
            throw new Error(`Unknown formula function '${name}'.`);
        }
        if (depth + 1 > MAX_DEPTH)
            throw new Error('Formula exceeds the 32-level nesting limit.');
        this.position += 1;
        const argumentsList: RawMetricFormulaAst[] = [];
        if (!this.peek(')')) {
            do {
                argumentsList.push(this.parseExpression(depth + 1));
                if (!this.peek(',')) break;
                this.position += 1;
            } while (true);
        }
        this.expect(')');
        if (
            (canonicalName === 'ROUND' && argumentsList.length !== 2) ||
            (canonicalName !== 'ROUND' && argumentsList.length < 1)
        ) {
            throw new Error(
                `${canonicalName} has an invalid number of arguments.`,
            );
        }
        const call = this.make({
            type: 'call',
            name: canonicalName,
            arguments: argumentsList,
        });
        if (canonicalName === 'ROUND') roundScale(argumentsList[1]);
        return call;
    }

    private make(node: RawMetricFormulaAst): RawMetricFormulaAst {
        if (++this.nodes > MAX_NODES)
            throw new Error('Formula exceeds the 256-node limit.');
        return node;
    }

    private peek(value: string): boolean {
        return this.tokens[this.position]?.value === value;
    }

    private consume(): Token {
        return this.tokens[this.position++];
    }

    private expect(value: string): void {
        if (!this.peek(value))
            throw new Error(`Formula is missing '${value}'.`);
        this.position += 1;
    }
}

function normalizeLiteral(literal: string): string {
    const [integer, fraction] = literal.split('.');
    const normalizedInteger = integer.replace(/^0+/, '') || '0';
    return fraction === undefined
        ? normalizedInteger
        : `${normalizedInteger}.${fraction}`;
}

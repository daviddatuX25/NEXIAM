<?php

namespace App\Services;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class FormulaEvaluator
{
    private ExpressionLanguage $expressionLanguage;

    public function __construct()
    {
        $this->expressionLanguage = new ExpressionLanguage;
    }

    public function evaluate(string $formula, array $variables): ?float
    {
        try {
            $allowed = ['x', 'max_items', 'pi'];
            if (array_diff(array_keys($variables), $allowed)) {
                return null;
            }

            if (! isset($variables['pi'])) {
                $variables['pi'] = pi();
            }

            $result = $this->expressionLanguage->evaluate($formula, $variables);

            if (! is_numeric($result)) {
                return null;
            }

            return round((float) $result, 2);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function validate(string $formula): bool
    {
        if (trim($formula) === '') {
            return false;
        }

        // Replace multi-char tokens first, then check remaining chars
        $working = str_replace('**', ' ', $formula);
        $working = str_replace(['max_items', 'pi', 'x'], ' ', $working);
        $remaining = preg_replace('/[0-9\s\+\-\*\/\(\)\.]+/', '', $working);

        if ($remaining !== '') {
            return false;
        }

        return $this->evaluate($formula, ['x' => 1.0, 'max_items' => 100, 'pi' => pi()]) !== null;
    }
}

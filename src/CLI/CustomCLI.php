<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Auth\CLI;

use CodeIgniter\CLI\CLI;
use Config\Services;
use InvalidArgumentException;

class CustomCLI extends CLI
{
    /**
     * Asks the user for input.
     *
     * Usage:
     *
     * // Takes any input
     * $color = CLI::prompt('What is your favorite color?');
     *
     * // Takes any input, but offers default
     * $color = CLI::prompt('What is your favourite color?', 'white');
     *
     * // Will validate options with the in_list rule and accept only if one of the list
     * $color = CLI::prompt('What is your favourite color?', array('red','blue'));
     *
     * // Do not provide options but requires a valid email
     * $email = CLI::prompt('What is your email?', null, 'required|valid_email');
     *
     * @param string            $field      Output "field" question
     * @param array|string      $options    String to a default value, array to a list of options (the first option will be the default value)
     * @param array|string|null $validation Validation rules
     *
     * @return string The user input
     *
     * @codeCoverageIgnore
     */
    public static function prompt(string $field, $options = null, $validation = null, ?string $DBGroup = null): string
    {
        $extraOutput = '';
        $default     = '';

        if ($validation && ! is_array($validation) && ! is_string($validation)) {
            throw new InvalidArgumentException('$rules can only be of type string|array');
        }

        if (! is_array($validation)) {
            $validation = $validation ? explode('|', $validation) : [];
        }

        if (is_string($options)) {
            $extraOutput = ' [' . static::color($options, 'green') . ']';
            $default     = $options;
        }

        if (is_array($options) && $options) {
            $opts               = $options;
            $extraOutputDefault = static::color($opts[0], 'green');

            unset($opts[0]);

            if ($opts === []) {
                $extraOutput = $extraOutputDefault;
            } else {
                $extraOutput  = '[' . $extraOutputDefault . ', ' . implode(', ', $opts) . ']';
                $validation[] = 'in_list[' . implode(', ', $options) . ']';
            }

            $default = $options[0];
        }

        static::fwrite(STDOUT, $field . (trim($field) ? ' ' : '') . $extraOutput . ': ');

        // Read the input from keyboard.
        $input = trim(static::input()) ?: $default;

        if ($validation !== []) {
            while (! static::validate('"' . trim($field) . '"', $input, $validation, $DBGroup)) {
                $input = static::prompt($field, $options, $validation, $DBGroup);
            }
        }

        return $input;
    }

    /**
     * Validate one prompt "field" at a time
     *
     * @param string       $field Prompt "field" output
     * @param string       $value Input value
     * @param array|string $rules Validation rules
     *
     * @codeCoverageIgnore
     */
    protected static function validate(string $field, string $value, $rules, ?string $DBGroup = null): bool
    {
        $label      = $field;
        $field      = 'temp';
        $validation = Services::validation(null, false);
        $validation->setRules([
            $field => [
                'label' => $label,
                'rules' => $rules,
            ],
        ]);
        $validation->run([$field => $value], null, $DBGroup);

        if ($validation->hasError($field)) {
            static::error($validation->getError($field));

            return false;
        }

        return true;
    }
}

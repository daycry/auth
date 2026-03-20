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

namespace Daycry\Auth\Authentication\Passwords;

use Daycry\Auth\Entities\User;
use Daycry\Auth\Interfaces\PasswordValidatorInterface;
use Daycry\Auth\Result;

/**
 * Class DictionaryValidator
 *
 * Checks passwords against a list of 65k commonly used passwords
 * that was compiled by InfoSec.
 */
class DictionaryValidator extends BaseValidator implements PasswordValidatorInterface
{
    /**
     * In-memory cache of dictionary words for O(1) lookups.
     * Keys are the dictionary words, values are irrelevant (array_flip).
     *
     * @var array<string, int>|null
     */
    private static ?array $dictionary = null;

    /**
     * Checks the password against the words in the file and returns false
     * if a match is found. Returns true if no match is found.
     * If true is returned the password will be passed to next validator.
     * If false is returned the validation process will be immediately stopped.
     */
    public function check(string $password, ?User $user = null): Result
    {
        if (self::$dictionary === null) {
            self::$dictionary = $this->loadDictionary();
        }

        if (isset(self::$dictionary[$password])) {
            return new Result([
                'success'   => false,
                'reason'    => lang('Auth.errorPasswordCommon'),
                'extraInfo' => lang('Auth.suggestPasswordCommon'),
            ]);
        }

        return new Result([
            'success' => true,
        ]);
    }

    /**
     * Loads the dictionary file into an associative array for O(1) lookups.
     * Returns an empty array if the file cannot be opened.
     *
     * @return array<string, int>
     */
    private function loadDictionary(): array
    {
        $fp = fopen(__DIR__ . '/_dictionary.txt', 'rb');

        if ($fp === false) {
            return [];
        }

        try {
            $words = [];

            while (($line = fgets($fp, 4096)) !== false) {
                $word = trim($line);

                if ($word !== '') {
                    $words[] = $word;
                }
            }

            return array_flip($words);
        } finally {
            fclose($fp);
        }
    }
}

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

namespace Tests\Support;

use CodeIgniter\Email\Email;
use Config\Services;
use RuntimeException;

/**
 * Email transport double: records every message instead of sending it.
 *
 * Install with CapturingEmail::install() in setUp(); inspect CapturingEmail::$sent.
 * Set $failNext / $throwNext to simulate a transport failure.
 */
final class CapturingEmail extends Email
{
    /**
     * @var list<array{to: string, subject: string, body: string}>
     */
    public static array $sent = [];

    public static bool $failNext    = false;
    public static bool $throwNext   = false;
    private string $capturedTo      = '';
    private string $capturedSubject = '';
    private string $capturedBody    = '';

    public static function install(): void
    {
        self::$sent      = [];
        self::$failNext  = false;
        self::$throwNext = false;

        Services::injectMock('email', new self());
    }

    /**
     * @return list<array{to: string, subject: string, body: string}>
     */
    public static function sentTo(string $address): array
    {
        return array_values(array_filter(self::$sent, static fn (array $m): bool => $m['to'] === $address));
    }

    public function setTo($to)
    {
        $this->capturedTo = is_array($to) ? implode(',', $to) : (string) $to;

        return parent::setTo($to);
    }

    public function setSubject($subject)
    {
        $this->capturedSubject = (string) $subject;

        return parent::setSubject($subject);
    }

    public function setMessage($body)
    {
        $this->capturedBody = (string) $body;

        return parent::setMessage($body);
    }

    public function send($autoClear = true)
    {
        if (self::$throwNext) {
            self::$throwNext = false;

            throw new RuntimeException('SMTP exploded');
        }

        if (self::$failNext) {
            self::$failNext = false;

            return false;
        }

        self::$sent[] = ['to' => $this->capturedTo, 'subject' => $this->capturedSubject, 'body' => $this->capturedBody];

        return true;
    }
}

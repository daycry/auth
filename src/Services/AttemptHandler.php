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

namespace Daycry\Auth\Services;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Auth\Models\AttemptModel;
use Daycry\Auth\Validators\AttemptValidator;

/**
 * Service for handling invalid login attempts
 *
 * Extracts attempt handling logic from BaseControllerTrait
 */
class AttemptHandler
{
    protected AttemptModel $attemptModel;
    protected bool $isEnabled;

    public function __construct()
    {
        $this->attemptModel = new AttemptModel();
        $this->isEnabled    = service('settings')->get('Auth.enableInvalidAttempts') === true;
    }

    /**
     * Check if attempts validation is enabled and validate if needed
     */
    public function validateAttempts(ResponseInterface $response): void
    {
        if (! $this->isEnabled) {
            return;
        }

        AttemptValidator::check($response);
    }

    /**
     * Handle invalid attempts by updating the attempts counter
     */
    public function handleInvalidAttempt(RequestInterface $request): void
    {
        if (! $this->isEnabled) {
            return;
        }

        $ipAddress = $request->getIPAddress();
        $attempt   = $this->attemptModel->where('ip_address', $ipAddress)->first();

        if ($attempt === null) {
            $this->createNewAttempt($ipAddress);
        } elseif ($attempt->attempts < service('settings')->get('Auth.maxAttempts')) {
            $this->incrementAttempt($attempt);
        }
    }

    /**
     * Create a new attempt record
     */
    private function createNewAttempt(string $ipAddress): void
    {
        $attempt = [
            'user_id'      => auth()->user()?->id,
            'ip_address'   => $ipAddress,
            'attempts'     => 1,
            'hour_started' => time(),
        ];

        $this->attemptModel->save($attempt);
    }

    /**
     * Increment existing attempt counter
     */
    private function incrementAttempt(object $attempt): void
    {
        $attempt->attempts++;
        $this->attemptModel->save($attempt);
    }

    /**
     * Check if attempts handling is enabled
     */
    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    /**
     * Static method to get a new instance
     */
    public static function getInstance(): self
    {
        return new self();
    }
}

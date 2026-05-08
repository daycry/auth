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

namespace Daycry\Auth\Authorization;

/**
 * Wraps the result of a Gate / Policy check with an optional error
 * message that callers can surface to the user.
 *
 * Inspired by Laravel's `Illuminate\Auth\Access\Response` —
 * keeping the API similar so users coming from Laravel feel at home.
 *
 *     return PolicyResponse::deny('Solo el autor puede editar este post.');
 *     return PolicyResponse::allow();
 */
final class PolicyResponse
{
    private function __construct(
        private readonly bool $allowed,
        private readonly ?string $message = null,
    ) {
    }

    public static function allow(?string $message = null): self
    {
        return new self(true, $message);
    }

    public static function deny(?string $message = null): self
    {
        return new self(false, $message);
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    /**
     * Throws AuthorizationException when the response is a deny.
     *
     * @throws AuthorizationException
     */
    public function authorize(): self
    {
        if ($this->denied()) {
            throw new AuthorizationException(
                $this->message ?? 'This action is unauthorized.',
                $this,
            );
        }

        return $this;
    }
}

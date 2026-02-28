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

namespace Daycry\Auth\Entities;

use CodeIgniter\I18n\Time;

/**
 * Represents a single authenticated device session for a user.
 *
 * @property Time        $created_at
 * @property string|null $device_name
 * @property int         $id
 * @property string      $ip_address
 * @property Time        $last_active
 * @property Time|null   $logged_out_at
 * @property string      $session_id
 * @property Time|null   $updated_at
 * @property string|null $user_agent
 * @property int         $user_id
 */
class DeviceSession extends Entity
{
    /**
     * @var list<string>
     */
    protected $dates = [
        'last_active',
        'logged_out_at',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id'      => '?integer',
        'user_id' => 'integer',
    ];

    /**
     * Returns true when the session has not been terminated.
     */
    public function isActive(): bool
    {
        $loggedOutAt = $this->attributes['logged_out_at'] ?? null;

        return $loggedOutAt === null || $loggedOutAt === '';
    }

    /**
     * Generates a human-readable device name from the user agent string.
     * Falls back to the stored device_name if present.
     */
    public function getDeviceLabel(): string
    {
        if ($this->device_name !== null && $this->device_name !== '') {
            return $this->device_name;
        }

        return self::parseUserAgent($this->attributes['user_agent'] ?? '');
    }

    /**
     * Parses a user agent string into a readable browser + OS label.
     */
    public static function parseUserAgent(string $userAgent): string
    {
        if ($userAgent === '') {
            return 'Unknown Device';
        }

        $browser = 'Unknown Browser';
        $os      = 'Unknown OS';

        // Detect browser (order matters — Edge/Opera must come before Chrome)
        if (str_contains($userAgent, 'Edg/') || str_contains($userAgent, 'Edge/')) {
            $browser = 'Microsoft Edge';
        } elseif (str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera/')) {
            $browser = 'Opera';
        } elseif (str_contains($userAgent, 'Firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($userAgent, 'Chrome/')) {
            $browser = 'Chrome';
        } elseif (str_contains($userAgent, 'Safari/')) {
            $browser = 'Safari';
        } elseif (str_contains($userAgent, 'MSIE') || str_contains($userAgent, 'Trident/')) {
            $browser = 'Internet Explorer';
        } elseif (str_contains($userAgent, 'curl/')) {
            $browser = 'cURL';
        } elseif (str_contains($userAgent, 'Postman')) {
            $browser = 'Postman';
        }

        // Detect OS
        if (str_contains($userAgent, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'Mac OS X')) {
            $os = 'macOS';
        } elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            $os = 'iOS';
        } elseif (str_contains($userAgent, 'Android')) {
            $os = 'Android';
        } elseif (str_contains($userAgent, 'Linux')) {
            $os = 'Linux';
        }

        return $browser . ' on ' . $os;
    }
}

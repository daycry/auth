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

use CodeIgniter\Entity\Entity;

/**
 * @property string|null $aaguid
 * @property string      $credential
 * @property string      $credential_id
 * @property int|string  $id
 * @property string|null $name
 * @property int         $sign_count
 * @property string|null $transports
 * @property string|null $user_handle
 * @property int         $user_id
 * @property string|null $uuid
 */
class WebAuthnCredential extends Entity
{
    protected $casts = [
        'id'         => 'integer',
        'user_id'    => 'integer',
        'sign_count' => 'integer',
    ];
    protected $dates = ['last_used_at', 'created_at', 'updated_at', 'revoked_at'];
}

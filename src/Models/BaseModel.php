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

namespace Daycry\Auth\Models;

use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;
use Config\Database;
use Daycry\Auth\Traits\CheckQueryReturnTrait;

abstract class BaseModel extends Model
{
    use CheckQueryReturnTrait;

    /**
     * Table names
     */
    protected array $tables;

    public function __construct(?ConnectionInterface &$db = null, ?ValidationInterface $validation = null)
    {
        if ($db === null) {
            $db            = Database::connect(service('settings')->get('Auth.DBGroup'));
            $this->DBGroup = service('settings')->get('Auth.DBGroup');
        }

        $this->tables = service('settings')->get('Auth.tables');

        parent::__construct($db, $validation);
    }
}

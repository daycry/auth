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
use CodeIgniter\Validation\ValidationInterface;
use Daycry\Auth\Entities\Attempt;

class AttemptModel extends BaseModel
{
    protected $primaryKey     = 'id';
    protected $returnType     = Attempt::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'user_id',
        'ip_address',
        'attempts',
        'hour_started_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    public function __construct(?ConnectionInterface &$db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);

        $this->table = $this->tables['attempts'];
    }
}

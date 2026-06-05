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

namespace Tests\Database;

use CodeIgniter\Exceptions\RuntimeException;
use Daycry\Auth\Authentication\Authenticators\Session;
use Daycry\Auth\Database\Migrations\NormalizeLoginIdentifiers;
use Daycry\Auth\Enums\IdentityType;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class NormalizeLoginIdentifiersMigrationTest extends DatabaseTestCase
{
    /**
     * Inserts a user row directly via the query builder, bypassing the User
     * entity setters that would otherwise lowercase the username at write.
     */
    private function insertRawUser(?string $username): int
    {
        $this->db->table($this->tables['users'])->insert([
            'username' => $username,
            'active'   => 1,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * Inserts an identity row directly via the query builder, bypassing
     * UserIdentityModel which would lowercase email_password secrets at write.
     */
    private function insertRawIdentity(int $userId, string $type, string $secret): int
    {
        $this->db->table($this->tables['identities'])->insert([
            'user_id' => $userId,
            'type'    => $type,
            'name'    => null,
            'secret'  => $secret,
        ]);

        return (int) $this->db->insertID();
    }

    public function testNormalizesMixedCaseRows(): void
    {
        $userId = $this->insertRawUser('MixedCase');
        $this->insertRawIdentity($userId, Session::ID_TYPE_EMAIL_PASSWORD, 'Mixed@Example.COM');

        $migration = new NormalizeLoginIdentifiers();
        $migration->up();

        $this->seeInDatabase($this->tables['users'], ['id' => $userId, 'username' => 'mixedcase']);
        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $userId,
            'type'    => Session::ID_TYPE_EMAIL_PASSWORD,
            'secret'  => 'mixed@example.com',
        ]);

        // Idempotent: running again must not throw or change anything.
        $migration->up();

        $this->seeInDatabase($this->tables['users'], ['id' => $userId, 'username' => 'mixedcase']);
        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $userId,
            'type'    => Session::ID_TYPE_EMAIL_PASSWORD,
            'secret'  => 'mixed@example.com',
        ]);
    }

    public function testAbortsOnUsernameCollision(): void
    {
        $this->insertRawUser('John');
        $this->insertRawUser('john');

        $migration = new NormalizeLoginIdentifiers();

        try {
            $migration->up();
            $this->fail('Expected the migration to abort on a username case-collision.');
        } catch (RuntimeException) {
            // expected
        }

        // No partial write: both original values must still be present.
        $this->seeInDatabase($this->tables['users'], ['username' => 'John']);
        $this->seeInDatabase($this->tables['users'], ['username' => 'john']);
    }

    public function testAbortsOnEmailSecretCollision(): void
    {
        $userA = $this->insertRawUser('userA');
        $userB = $this->insertRawUser('userB');
        $this->insertRawIdentity($userA, Session::ID_TYPE_EMAIL_PASSWORD, 'A@x.com');
        $this->insertRawIdentity($userB, Session::ID_TYPE_EMAIL_PASSWORD, 'a@x.com');

        $migration = new NormalizeLoginIdentifiers();

        try {
            $migration->up();
            $this->fail('Expected the migration to abort on an email secret case-collision.');
        } catch (RuntimeException) {
            // expected
        }

        // No partial write: both original secrets must still be present.
        $this->seeInDatabase($this->tables['identities'], [
            'type'   => Session::ID_TYPE_EMAIL_PASSWORD,
            'secret' => 'A@x.com',
        ]);
        $this->seeInDatabase($this->tables['identities'], [
            'type'   => Session::ID_TYPE_EMAIL_PASSWORD,
            'secret' => 'a@x.com',
        ]);
    }

    public function testLeavesOtherSecretsUntouched(): void
    {
        $userId = $this->insertRawUser('plainuser');

        $oauthSecret = 'AbC123SocialID';
        $tokenSecret = 'DeadBeefCAFE0123';

        $this->insertRawIdentity($userId, IdentityType::oauthProvider('google'), $oauthSecret);
        $this->insertRawIdentity($userId, IdentityType::ACCESS_TOKEN->value, $tokenSecret);

        $migration = new NormalizeLoginIdentifiers();
        $migration->up();

        // Byte-for-byte unchanged.
        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $userId,
            'type'    => IdentityType::oauthProvider('google'),
            'secret'  => $oauthSecret,
        ]);
        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $userId,
            'type'    => IdentityType::ACCESS_TOKEN->value,
            'secret'  => $tokenSecret,
        ]);
    }
}

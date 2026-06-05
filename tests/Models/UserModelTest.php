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

namespace Tests\Models;

use CodeIgniter\Database\Exceptions\DataException;
use CodeIgniter\Database\Query;
use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\LogicException;
use Daycry\Auth\Entities\User;
use Daycry\Auth\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class UserModelTest extends DatabaseTestCase
{
    protected $namespace;
    protected $refresh = true;

    private function createUserModel(): UserModel
    {
        return new UserModel();
    }

    public function testSaveInsertUser(): void
    {
        $users = $this->createUserModel();

        $user = $this->createNewUser();

        $users->save($user);

        $user = $users->findByCredentials(['email' => 'foo@bar.com']);
        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $user->id,
            'secret'  => 'foo@bar.com',
        ]);
        $this->seeInDatabase($this->tables['users'], [
            'id'     => $user->id,
            'active' => 0,
        ]);
    }

    public function testGeneratesValidV7UuidOnInsert(): void
    {
        $users = $this->createUserModel();
        $user  = $this->createNewUser();
        $users->save($user);

        $saved = $users->findByCredentials(['email' => 'foo@bar.com']);

        // A canonical RFC 4122 UUID v7 (version nibble 7, variant 8/9/a/b).
        $this->assertNotEmpty($saved->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) $saved->uuid,
        );
    }

    /**
     * @see https://github.com/codeigniter4/shield/issues/546
     */
    public function testFindByCredentialsEmptyEmail(): void
    {
        $users = $this->createUserModel();
        $user  = $this->createNewUser();
        $users->save($user);

        $user = $users->findByCredentials(['email' => '']);
        $this->assertNotInstanceOf(User::class, $user);

        $user = $users->findByCredentials([]);
        $this->assertNotInstanceOf(User::class, $user);
    }

    public function testInsertUserObject(): void
    {
        $users = $this->createUserModel();

        $user = $this->createNewUser();

        $users->insert($user);

        $user = $users->findByCredentials(['email' => 'foo@bar.com']);
        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $user->id,
            'secret'  => 'foo@bar.com',
        ]);
        $this->seeInDatabase($this->tables['users'], [
            'id'     => $user->id,
            'active' => 0,
        ]);
    }

    /**
     * @see https://github.com/codeigniter4/shield/issues/450
     */
    public function testSaveNewUserAndGetEmailIdentity(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"$user->id" is null. You should not use the incomplete User object.');

        $users = $this->createUserModel();
        $user  = $this->createNewUser();

        $users->save($user);

        $user->getEmailIdentity();
    }

    /**
     * This test is not correct.
     *
     * Entity's `toArray()` method returns array with properties and values.
     * The array may have different keys with the DB column names.
     * And the values may be different types with the DB column types.
     * So $userArray is not data to be inserted into the database.
     */
    public function testInsertUserArray(): void
    {
        $users = $this->createUserModel();

        $user = $this->createNewUser();

        $userArray = $user->toArray();
        // Fix value type
        $userArray['active'] = (int) $userArray['active'];

        $id = $users->insert($userArray);

        $this->dontSeeInDatabase($this->tables['identities'], [
            'user_id' => $id,
            'secret'  => 'foo@bar.com',
        ]);
        $this->seeInDatabase($this->tables['users'], [
            'id'     => $id,
            'active' => 0,
        ]);
    }

    public function testSaveUpdateUserObjectWithUserDataToUpdate(): void
    {
        $users = $this->createUserModel();
        $user  = $this->createNewUser();
        $users->save($user);

        $user = $users->findByCredentials(['email' => 'foo@bar.com']);

        $user->username = 'bar';
        $user->email    = 'bar@bar.com';
        $user->active   = true;
        $this->assertInstanceOf(User::class, $user);

        $users->save($user);

        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $user->id,
            'secret'  => 'bar@bar.com',
        ]);
        $this->seeInDatabase($this->tables['users'], [
            'id'     => $user->id,
            'active' => 1,
        ]);
    }

    public function testUpdateUserObjectWithUserDataToUpdate(): void
    {
        $users = $this->createUserModel();
        $user  = $this->createNewUser();
        $users->save($user);

        $user = $users->findByCredentials(['email' => 'foo@bar.com']);

        $user->username = 'bar';
        $user->email    = 'bar@bar.com';
        $user->active   = true;
        $this->assertInstanceOf(User::class, $user);

        $users->update($user->id, $user);

        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $user->id,
            'secret'  => 'bar@bar.com',
        ]);
        $this->seeInDatabase($this->tables['users'], [
            'id'     => $user->id,
            'active' => 1,
        ]);
    }

    /**
     * This test is not correct.
     *
     * Entity's `toArray()` method returns array with properties and values.
     * The array may have different keys with the DB column names.
     * And the values may be different types with the DB column types.
     * So $userArray is not data to be inserted into the database.
     */
    public function testUpdateUserArrayWithUserDataToUpdate(): void
    {
        $users = $this->createUserModel();
        $user  = $this->createNewUser();
        $users->save($user);

        $user = $users->findByCredentials(['email' => 'foo@bar.com']);

        $user->username = 'bar';
        $user->email    = 'bar@bar.com';
        $user->active   = true;
        $this->assertInstanceOf(User::class, $user);

        $userArray = $user->toArray();
        // Fix value type
        $userArray['active'] = (int) $userArray['active'];

        $users->update($user->id, $userArray);

        $this->dontSeeInDatabase($this->tables['identities'], [
            'user_id' => $user->id,
            'secret'  => 'bar@bar.com',
        ]);
        $this->seeInDatabase($this->tables['users'], [
            'id'     => $user->id,
            'active' => 1,
        ]);
    }

    public function testSaveUpdateUserObjectWithoutUserDataToUpdate(): void
    {
        $users = $this->createUserModel();
        $user  = $this->createNewUser();
        $users->save($user);

        $user = $users->findByCredentials(['email' => 'foo@bar.com']);

        $user->email = 'bar@bar.com';
        $this->assertInstanceOf(User::class, $user);

        $users->save($user);

        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $user->id,
            'secret'  => 'bar@bar.com',
        ]);
    }

    public function testUpdateUserObjectWithoutUserDataToUpdate(): void
    {
        $users = $this->createUserModel();
        $user  = $this->createNewUser();
        $users->save($user);

        $user = $users->findByCredentials(['email' => 'foo@bar.com']);

        $user->email = 'bar@bar.com';
        $this->assertInstanceOf(User::class, $user);

        $users->update(null, $user);

        $this->seeInDatabase($this->tables['identities'], [
            'user_id' => $user->id,
            'secret'  => 'bar@bar.com',
        ]);
    }

    /**
     * @see https://github.com/codeigniter4/shield/issues/471
     */
    public function testSaveArrayNoDataToUpdate(): void
    {
        $this->expectException(DataException::class);
        $this->expectExceptionMessage('There is no data to update.');

        $users = $this->createUserModel();
        $user  = fake(UserModel::class);

        $users->save(['id' => $user->id]);
    }

    /**
     * Saving a brand-new user with email + password must persist an identity
     * row whose secret is the email and whose secret2 verifies the REAL
     * password — proving the placeholder password ('') used by
     * createEmailIdentity is correctly overwritten and persisted via the
     * follow-up UPDATE on the reused entity.
     */
    public function testSaveNewUserPersistsRealCredentialsViaReusedIdentity(): void
    {
        $users = $this->createUserModel();
        $user  = $this->createNewUser();
        $users->save($user);

        $saved = $users->findByCredentials(['email' => 'foo@bar.com']);
        $this->assertInstanceOf(User::class, $saved);

        // Exactly one email-password identity row exists (no duplicate INSERT).
        $rows = $this->db->table($this->tables['identities'])
            ->where('user_id', $saved->id)
            ->where('type', 'email_password')
            ->get()
            ->getResultArray();
        $this->assertCount(1, $rows);

        // The stored secret is the email; secret2 verifies the real password,
        // not the placeholder '' that createEmailIdentity inserted.
        $this->assertSame('foo@bar.com', $rows[0]['secret']);
        $passwords = service('passwords');
        $this->assertTrue($passwords->verify('password', $rows[0]['secret2']));
        $this->assertFalse($passwords->verify('', $rows[0]['secret2']));
    }

    /**
     * Guards the query-count contract for saveEmailIdentity on a new user:
     * its `getIdentityByType(EMAIL_PASSWORD)` lookup must run exactly ONCE.
     * Previously the method re-queried the just-created identity, issuing a
     * second identical SELECT; that redundant lookup is now gone.
     *
     * We match only the `type = 'email_password' ... LIMIT 1` shape emitted by
     * getIdentityByType (the unrelated full `getIdentities()` SELECT fired by
     * the User::getPasswordHash() accessor is deliberately excluded).
     */
    public function testNewUserSaveIssuesSingleIdentityByTypeSelect(): void
    {
        $identitiesTable = $this->tables['identities'];

        $byTypeCount = 0;
        Events::on('DBQuery', static function (Query $query) use (&$byTypeCount, $identitiesTable): void {
            $sql = $query->getQuery();
            if (
                str_starts_with(strtolower($sql), strtolower('SELECT'))
                && str_contains($sql, $identitiesTable)
                && str_contains($sql, "`type` = 'email_password'")
            ) {
                $byTypeCount++;
            }
        });

        $users = $this->createUserModel();
        $user  = $this->createNewUser();
        $users->save($user);

        // Snapshot immediately so only save()'s statements are measured.
        $measured = $byTypeCount;

        $this->assertSame(
            1,
            $measured,
            'saveEmailIdentity must issue exactly one getIdentityByType SELECT for a new user (was 2)',
        );
    }

    private function createNewUser(): User
    {
        $user           = new User();
        $user->username = 'foo';
        $user->email    = 'foo@bar.com';
        $user->password = 'password';
        $user->active   = false;

        return $user;
    }
}

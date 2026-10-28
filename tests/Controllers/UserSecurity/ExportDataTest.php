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

namespace Tests\Controllers\UserSecurity;

use Daycry\Auth\Models\AuditLogModel;
use Tests\Support\SelfServiceTestCase;

/**
 * @internal
 */
final class ExportDataTest extends SelfServiceTestCase
{
    public function testOversizedExportFailsExplicitlyInsteadOfExhaustingWorkerOrTruncating(): void
    {
        setting('AuthSecurity.dataExportMaxRecords', 2);
        $user = $this->userWithPassword();

        for ($i = 0; $i < 3; $i++) {
            model(AuditLogModel::class)->insert(['user_id' => $user->id, 'event_type' => 'sample', 'created_at' => date('Y-m-d H:i:s')]);
        }
        $this->signedInAs($user);
        $_SESSION['password_confirmed_at'] = time();
        $_SESSION['auth_step_up_user']     = (string) $user->id;
        $result                            = $this->withSession($_SESSION)->post('account/security/export');
        $result->assertStatus(413);
        $result->assertJSONFragment(['code' => 'export_too_large']);
        $this->assertStringNotContainsString('attachment', $result->response()->getHeaderLine('Content-Disposition'));
    }

    public function testExportRequiresARecentProofBoundToCurrentUser(): void
    {
        $user   = $this->userWithPassword();
        $result = $this->signedInAs($user)->withHeaders(['Accept' => 'application/json'])->post('account/security/export');
        $result->assertStatus(403);
        $result->assertJSONFragment(['code' => 'confirmation_required']);
    }

    public function testExportIgnoresTargetIdsAndContainsOnlyCurrentAccount(): void
    {
        $user  = $this->userWithPassword('first@example.com');
        $other = $this->userWithPassword('second@example.com');
        $this->signedInAs($user);
        $_SESSION['password_confirmed_at'] = time();
        $_SESSION['auth_step_up_user']     = (string) $user->id;
        $result                            = $this->withSession($_SESSION)->post('account/security/export', ['user_id' => $other->id]);
        $result->assertStatus(200);
        $this->assertSame('first@example.com', json_decode($result->getJSON(), true)['user']['email']);
        $this->assertStringNotContainsString('second@example.com', (string) $result->getJSON());
        $this->assertStringContainsString('attachment;', $result->response()->getHeaderLine('Content-Disposition'));
        $this->assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));
    }

    public function testAnotherUsersProofCannotAuthorizeExport(): void
    {
        $user = $this->userWithPassword();
        $this->signedInAs($user);
        $_SESSION['password_confirmed_at'] = time();
        $_SESSION['auth_step_up_user']     = '999999';
        $this->withSession($_SESSION)->withHeaders(['Accept' => 'application/json'])->post('account/security/export')->assertStatus(403);
    }
}

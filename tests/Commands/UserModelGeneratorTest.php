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

namespace Tests\Commands;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Filters\CITestStreamFilter;

/**
 * @internal
 */
final class UserModelGeneratorTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CITestStreamFilter::registration();
        CITestStreamFilter::addOutputFilter();
        CITestStreamFilter::addErrorFilter();

        if (is_file(HOMEPATH . 'src/Models/UserModel.php')) {
            copy(HOMEPATH . 'src/Models/UserModel.php', HOMEPATH . 'src/Models/UserModel.php.bak');
        }

        $this->deleteTestFiles();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        CITestStreamFilter::removeOutputFilter();
        CITestStreamFilter::removeErrorFilter();

        $this->deleteTestFiles();

        if (is_file(HOMEPATH . 'src/Models/UserModel.php.bak')) {
            copy(HOMEPATH . 'src/Models/UserModel.php.bak', HOMEPATH . 'src/Models/UserModel.php');
            unlink(HOMEPATH . 'src/Models/UserModel.php.bak');
        }
    }

    private function deleteTestFiles(): void
    {
        $possibleFiles = [
            APPPATH . 'Models/UserModel.php',
            HOMEPATH . 'src/Models/UserModel.php',
        ];

        foreach ($possibleFiles as $file) {
            clearstatcache(true, $file);

            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function getFileContents(string $filepath): string
    {
        return (string) @file_get_contents($filepath);
    }

    public function testGenerateUserModel(): void
    {
        command('auth:model UserModel');

        $filepath = APPPATH . 'Models/UserModel.php';
        $this->assertStringContainsString('File created: ', CITestStreamFilter::$buffer);
        $this->assertFileExists($filepath);

        $contents = $this->getFileContents($filepath);
        $this->assertStringContainsString('namespace App\Models;', $contents);
        $this->assertStringContainsString('class UserModel extends AuthUserModel', $contents);
        $this->assertStringContainsString('use Daycry\Auth\Models\UserModel as AuthUserModel;', $contents);
        $this->assertStringContainsString('protected function initialize(): void', $contents);
        $this->assertStringContainsString('parent::initialize();', $contents);
    }

    public function testGenerateUserModelCustomNamespace(): void
    {
        command('auth:model UserModel --namespace Daycry\\\\Auth');

        $filepath = HOMEPATH . 'src/Models/UserModel.php';
        $this->assertStringContainsString('File created: ', CITestStreamFilter::$buffer);
        $this->assertFileExists($filepath);

        $contents = $this->getFileContents($filepath);
        $this->assertStringContainsString('namespace Daycry\Auth\Models;', $contents);
        $this->assertStringContainsString('class UserModel extends AuthUserModel', $contents);
        $this->assertStringContainsString('use Daycry\Auth\Models\UserModel as AuthUserModel;', $contents);
        $this->assertStringContainsString('protected function initialize(): void', $contents);
    }

    public function testGenerateUserModelWithForce(): void
    {
        command('auth:model UserModel');
        command('auth:model UserModel --force');

        $this->assertStringContainsString('File overwritten: ', CITestStreamFilter::$buffer);
        $this->assertFileExists(APPPATH . 'Models/UserModel.php');
    }

    public function testGenerateUserModelWithSuffix(): void
    {
        command('auth:model User --suffix');

        $this->assertStringContainsString('File created: ', CITestStreamFilter::$buffer);

        $filepath = APPPATH . 'Models/UserModel.php';
        $this->assertFileExists($filepath);
        $this->assertStringContainsString('class UserModel extends AuthUserModel', $this->getFileContents($filepath));
    }

    public function testGenerateUserModelWithoutClassNameInput(): void
    {
        command('auth:model');

        $this->assertStringContainsString('File created: ', CITestStreamFilter::$buffer);

        $filepath = APPPATH . 'Models/UserModel.php';
        $this->assertFileExists($filepath);
        $this->assertStringContainsString('class UserModel extends AuthUserModel', $this->getFileContents($filepath));
    }

    public function testGenerateUserCannotAcceptShieldUserModelAsInput(): void
    {
        command('auth:model AuthUserModel');

        $this->assertStringContainsString('Cannot use `AuthUserModel` as class name as this conflicts with the parent class.', CITestStreamFilter::$buffer);
        $this->assertFileDoesNotExist(APPPATH . 'Models/UserModel.php');

        CITestStreamFilter::$buffer = '';

        command('auth:model AuthUser --suffix');

        $this->assertStringContainsString('Cannot use `AuthUserModel` as class name as this conflicts with the parent class.', CITestStreamFilter::$buffer);
        $this->assertFileDoesNotExist(APPPATH . 'Models/UserModel.php');
    }
}

<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Tests\Santa;

use JoliCode\SecretSanta\Santa\Rudolph;
use PHPUnit\Framework\TestCase;

class RudolphTest extends TestCase
{
    private Rudolph $SUT;

    protected function setUp(): void
    {
        $this->SUT = new Rudolph();
    }

    public function testItRefusesEmptyList(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected at least 2 users in the list, 0 given.');

        $this->SUT->associateUsers([]);
    }

    public function testItRefusesListWith1User(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected at least 2 users in the list, 1 given.');

        $this->SUT->associateUsers([
            'toto',
        ]);
    }

    public function testItRefusesListWithOneDuplicate(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('This user was more than one time in the list: toto2.');

        $this->SUT->associateUsers([
            'toto1',
            'toto2',
            'toto3',
            'toto2',
        ]);
    }

    public function testItRefusesListWithSeveralDuplicates(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('These users were more than one time in the list: toto2, toto1.');

        $this->SUT->associateUsers([
            'toto1',
            'toto2',
            'toto3',
            'toto2',
            'toto1',
        ]);
    }

    /**
     * @param string[] $users
     *
     * @dataProvider userListDataProvider
     */
    public function testItCreateAssociations(array $users): void
    {
        $associations = $this->SUT->associateUsers($users);

        $this->assertCount(\count($users), $associations);

        foreach ($users as $user) {
            self::assertArrayHasKey($user, $associations);
            self::assertContains($user, $associations);
            self::assertNotSame($user, $associations[$user]);
        }
    }

    /**
     * @return string[][][]
     */
    public function userListDataProvider(): array
    {
        return [
            [[
                'toto1',
                'toto2',
            ]],
            [[
                'toto1',
                'toto2',
                'toto3',
            ]],
            [[
                'toto1',
                'toto2',
                'toto3',
                'toto4',
                'toto5',
                'toto6',
                'toto7',
                'toto8',
                'toto9',
                'toto10',
            ]],
        ];
    }
}

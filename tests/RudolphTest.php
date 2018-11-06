<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Tests;

use JoliCode\SecretSanta\Rudolph;
use PHPUnit\Framework\TestCase;

class RudolphTest extends TestCase
{
    /** @var Rudolph */
    private $SUT;

    protected function setUp()
    {
        $this->SUT = new Rudolph();
    }

    /**
     * @expectedException \LogicException
     * @expectedException "Expected at least 2 users in the list, 0 given."
     */
    public function test_it_refuses_empty_list()
    {
        $this->SUT->associateUsers([]);
    }

    /**
     * @expectedException \LogicException
     * @expectedException "Expected at least 2 users in the list, 1 given."
     */
    public function test_it_refuses_list_with_1_user()
    {
        $this->SUT->associateUsers([
            'toto',
        ]);
    }

    /**
     * @expectedException \LogicException
     * @expectedException "This user was more than one time in the list: toto2."
     */
    public function test_it_refuses_list_with_one_duplicate()
    {
        $this->SUT->associateUsers([
            'toto1',
            'toto2',
            'toto3',
            'toto2',
        ]);
    }

    /**
     * @expectedException \LogicException
     * @expectedException "These users were more than one time in the list: toto2, toto1."
     */
    public function test_it_refuses_list_with_several_duplicates()
    {
        $this->SUT->associateUsers([
            'toto1',
            'toto2',
            'toto3',
            'toto2',
            'toto1',
        ]);
    }

    /**
     * @param $users
     *
     * @dataProvider userListDataProvider
     */
    public function test_it_create_associations(array $users)
    {
        $associations = $this->SUT->associateUsers($users);

        $this->assertCount(\count($users), $associations);

        foreach ($users as $user) {
            self::assertArrayHasKey($user, $associations);
            self::assertContains($user, $associations);
            self::assertNotSame($user, $associations[$user]);
        }
    }

    public function userListDataProvider()
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

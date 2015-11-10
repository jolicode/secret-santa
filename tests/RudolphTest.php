<?php

namespace Joli\SlackSecretSanta\Tests;

use Joli\SlackSecretSanta\Rudolph;

class RudolphTest extends \PHPUnit_Framework_TestCase
{
    /** @var Rudolph */
    private $SUT;

    protected function setUp()
    {
        $this->SUT = new Rudolph();
    }

    /**
     * @expectedException \LogicException
     * @expectedException "Expected at least 2 users in the list, 0 given"
     */
    public function test_it_refuses_empty_list()
    {
        $this->SUT->associateUsers([]);
    }

    /**
     * @expectedException \LogicException
     * @expectedException "Expected at least 2 users in the list, 1 given"
     */
    public function test_it_refuses_list_with_1_user()
    {
        $this->SUT->associateUsers([
            'toto'
        ]);
    }

    /**
     * @expectedException \LogicException
     * @expectedException "This user was more than one time in the list: toto2"
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
     * @expectedException "These users were more than one time in the list: toto2, toto1"
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

        $this->assertCount(count($users), $associations);

        foreach ($users as $user) {
            $this->assertTrue(array_key_exists($user, $associations));
            $this->assertTrue(in_array($user, $associations, true));
            $this->assertNotEquals($user, $associations[$user]);
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
            ]]
        ];
    }
}

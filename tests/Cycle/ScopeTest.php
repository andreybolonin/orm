<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cycle\Tests;

use Spiral\Cycle\Mapper\Mapper;
use Spiral\Cycle\Relation;
use Spiral\Cycle\Schema;
use Spiral\Cycle\Selector;
use Spiral\Cycle\Tests\Fixtures\Comment;
use Spiral\Cycle\Tests\Fixtures\User;
use Spiral\Cycle\Tests\Traits\TableTrait;

abstract class ScopeTest extends BaseTest
{
    use TableTrait;

    public function setUp()
    {
        parent::setUp();

        $this->makeTable('user', [
            'id'      => 'primary',
            'email'   => 'string',
            'balance' => 'float'
        ]);

        $this->getDatabase()->table('user')->insertMultiple(
            ['email', 'balance'],
            [
                ['hello@world.com', 100],
                ['another@world.com', 200],
            ]
        );

        $this->makeTable('comment', [
            'id'      => 'primary',
            'user_id' => 'integer',
            'level'   => 'integer',
            'message' => 'string'
        ]);

        $this->getDatabase()->table('comment')->insertMultiple(
            ['user_id', 'level', 'message'],
            [
                [1, 1, 'msg 1'],
                [1, 2, 'msg 2'],
                [1, 3, 'msg 3'],
                [1, 4, 'msg 4'],
                [2, 1, 'msg 2.1'],
                [2, 2, 'msg 2.2'],
                [2, 3, 'msg 2.3'],
            ]
        );

        $this->orm = $this->withSchema(new Schema([
            User::class    => [
                Schema::ALIAS       => 'user',
                Schema::MAPPER      => Mapper::class,
                Schema::DATABASE    => 'default',
                Schema::TABLE       => 'user',
                Schema::PRIMARY_KEY => 'id',
                Schema::COLUMNS     => ['id', 'email', 'balance'],
                Schema::SCHEMA      => [],
                Schema::RELATIONS   => [
                    'comments' => [
                        Relation::TYPE   => Relation::HAS_MANY,
                        Relation::TARGET => Comment::class,
                        Relation::SCHEMA => [
                            Relation::CASCADE   => true,
                            Relation::INNER_KEY => 'id',
                            Relation::OUTER_KEY => 'user_id',
                        ],
                    ]
                ]
            ],
            Comment::class => [
                Schema::ALIAS       => 'comment',
                Schema::MAPPER      => Mapper::class,
                Schema::DATABASE    => 'default',
                Schema::TABLE       => 'comment',
                Schema::PRIMARY_KEY => 'id',
                Schema::COLUMNS     => ['id', 'user_id', 'level', 'message'],
                Schema::SCHEMA      => [],
                Schema::RELATIONS   => []
            ]
        ]));
    }

    public function testSelectUsers()
    {
        $s = new Selector($this->orm, User::class);
        $res = $s->orderBy('user.id')->fetchAll();

        $this->assertCount(2, $res);
        $this->assertSame('hello@world.com', $res[0]->email);
        $this->assertSame('another@world.com', $res[1]->email);
    }

    public function testSelectUsersWithScope()
    {
        $s = new Selector($this->orm, User::class);
        $s->scope(new Selector\QueryScope(['@.balance' => 100]));

        $res = $s->fetchAll();

        $this->assertCount(1, $res);
        $this->assertSame('hello@world.com', $res[0]->email);
    }

    public function testSelectUserScopeCanNotBeOverwritten()
    {
        $s = new Selector($this->orm, User::class);
        $s->scope(new Selector\QueryScope(['@.balance' => 100]));

        $res = $s->where('user.balance', 200)->fetchAll();

        $this->assertCount(0, $res);
    }

    public function testSelectUserScopeCanNotBeOverwritten2()
    {
        $s = new Selector($this->orm, User::class);
        $s->scope(new Selector\QueryScope(['@.balance' => 100]));

        $res = $s->orWhere('user.balance', 200)->fetchAll();

        $this->assertCount(0, $res);
    }

    public function testScopeWithOrderBy()
    {
        $s = new Selector($this->orm, User::class);
        $s->scope(new Selector\QueryScope([], ['@.balance' => 'DESC']));

        $res = $s->fetchAll();

        $this->assertCount(2, $res);
        $this->assertSame('another@world.com', $res[0]->email);
        $this->assertSame('hello@world.com', $res[1]->email);
    }

    public function testRelated()
    {
        $s = new Selector($this->orm, User::class);
        $s->scope(new Selector\QueryScope([], ['@.balance' => 'DESC']));

        $res = $s->load('comments')->fetchAll();

        list($b, $a) = $res;

        $this->captureReadQueries();
        $this->assertCount(4, $a->comments);
        $this->assertCount(3, $b->comments);
        $this->assertNumReads(0);

        $this->assertSame('msg 4', $a->comments[3]->message);
        $this->assertSame('msg 3', $a->comments[2]->message);
        $this->assertSame('msg 2', $a->comments[1]->message);
        $this->assertSame('msg 1', $a->comments[0]->message);

        $this->assertSame('msg 2.3', $b->comments[2]->message);
        $this->assertSame('msg 2.2', $b->comments[1]->message);
        $this->assertSame('msg 2.1', $b->comments[0]->message);
    }

    public function testRelatedScope()
    {
        $s = new Selector($this->orm, User::class);
        $s->scope(new Selector\QueryScope([], ['@.balance' => 'DESC']));

        $res = $s->load('comments', [
            'scope' => new Selector\QueryScope(['@.level' => 4])
        ])->fetchAll();

        list($b, $a) = $res;

        $this->assertCount(1, $a->comments);
        $this->assertCount(0, $b->comments);

        $this->assertSame('msg 4', $a->comments[0]->message);
    }

    public function testRelatedScopeInload()
    {
        $s = new Selector($this->orm, User::class);
        $s->scope(new Selector\QueryScope([], ['@.balance' => 'DESC']));

        $res = $s->load('comments', [
            'method' => Selector\JoinableLoader::INLOAD,
            'scope'  => new Selector\QueryScope(['@.level' => 4])
        ])->fetchAll();

        list($b, $a) = $res;

        $this->assertCount(1, $a->comments);
        $this->assertCount(0, $b->comments);

        $this->assertSame('msg 4', $a->comments[0]->message);
    }

    public function testRelatedScopeOrdered()
    {
        $s = new Selector($this->orm, User::class);
        $s->scope(new Selector\QueryScope([], ['@.balance' => 'DESC']));

        $res = $s->load('comments', [
            'scope' => new Selector\QueryScope(['@.level' => ['>=' => 3]], ['@.level' => 'DESC'])
        ])->fetchAll();

        list($b, $a) = $res;

        $this->assertCount(2, $a->comments);
        $this->assertCount(1, $b->comments);

        $this->assertSame('msg 4', $a->comments[0]->message);
        $this->assertSame('msg 3', $a->comments[1]->message);

        $this->assertSame('msg 2.3', $b->comments[0]->message);
    }

    public function testRelatedScopeOrderedInload()
    {
        $s = new Selector($this->orm, User::class);
        $s->scope(new Selector\QueryScope([], ['@.balance' => 'DESC']));

        $res = $s->load('comments', [
            'method' => Selector\JoinableLoader::INLOAD,
            'scope'  => new Selector\QueryScope(['@.level' => ['>=' => 3]], ['@.level' => 'DESC'])
        ])->fetchAll();

        list($b, $a) = $res;

        $this->assertCount(2, $a->comments);
        $this->assertCount(1, $b->comments);

        $this->assertSame('msg 4', $a->comments[0]->message);
        $this->assertSame('msg 3', $a->comments[1]->message);

        $this->assertSame('msg 2.3', $b->comments[0]->message);
    }
}
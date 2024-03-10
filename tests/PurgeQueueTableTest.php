<?php

namespace CF\EntCache\Tests;

use CF\EntCache\PurgeQueueTable;

class PurgeQueueTableTest extends \WP_UnitTestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        PurgeQueueTable::createTable();
        PurgeQueueTable::resetTable();
    }

    public function testCreateAndDelete()
    {
        static $values = [
            ['type' => 'file', 'content' => 'https://127.0.0.1/page'],
            ['type' => 'file', 'content' => 'https://127.0.0.1/example'],
            ['type' => 'tag', 'content' => 'site:1'],
            ['type' => 'host', 'content' => 'host1.example.com'],
            ['type' => 'prefix', 'content' => '/images/'],
            ['type' => 'tag', 'content' => 'news'],
            ['type' => 'file', 'content' => 'https://127.0.0.1/contact'],
            ['type' => 'tag', 'content' => 'tag:sports'],
            ['type' => 'host', 'content' => 'host2.example.com'],
            ['type' => 'prefix', 'content' => '/css/']
        ];
        $count = count($values);

        [$results, $errors] = PurgeQueueTable::insertMany($values);
        static::assertTrue((bool) $results);
        static::assertEmpty($errors);

        $items = PurgeQueueTable::selectQueue();

        static::assertCount($count, $items);
        static::assertEquals('file', $items[0]->type);
        static::assertEquals('https://127.0.0.1/page', $items[0]->content);

        $deleted = PurgeQueueTable::deleteManyItems($items);

        static::assertEquals($count, $deleted);
        static::assertEmpty(PurgeQueueTable::selectQueue());
    }
}

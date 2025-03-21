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
            ['type' => 'file', 'content' => 'https://host2.example.com/'],
            ['type' => 'file', 'content' => 'https://host2.example.com/example-page/'],
            ['type' => 'tag', 'content' => 'site:1'],
            ['type' => 'host', 'content' => 'host1.example.com'],
            ['type' => 'prefix', 'content' => 'hostname.tld/contact/wp-content/file.css'],
            ['type' => 'tag', 'content' => 'news'],
            ['type' => 'file', 'content' => 'https://host2.example.com/contact.html'],
            ['type' => 'file', 'content' => 'https://host2.example.com/style.css'],
            ['type' => 'tag', 'content' => 'tag:sports'],
            ['type' => 'host', 'content' => 'host2.example.com'],
            ['type' => 'prefix', 'content' => 'domain.example/path/path/'],
        ];
        $count = count($values);

        [$results, $errors] = PurgeQueueTable::insertMany($values);
        static::assertTrue((bool) $results, 'Insert failed');

        $messages = array_map(fn(\WP_Error $error) => $error->get_error_message(), $errors);

        static::assertEquals([], $messages, 'Insert has errors');

        $items = PurgeQueueTable::selectQueue();

        static::assertCount($count, $items);
        static::assertEquals('file', $items[0]->type);
        static::assertEquals('https://host2.example.com/', $items[0]->content);

        $deleted = PurgeQueueTable::deleteManyItems($items);

        static::assertEquals($count, $deleted);
        static::assertEmpty(PurgeQueueTable::selectQueue(), 'Queue not empty');
    }
}

<?php

namespace CF\EntCache;

class PurgeQueueTable
{
    private const TABLE = 'cloudflare_purge_queue';

    public static function lastDbError(): string
    {
        global $wpdb;

        return $wpdb->last_error;
    }

    /**
     * @return array<object{id: int, type: string, content: string}>
     */
    public static function all(): array
    {
        global $wpdb;

        return (array) $wpdb->get_results(sprintf(
            'SELECT id, type, content FROM %s',
            $wpdb->prefix.self::TABLE
        ));
    }

    /**
     * @param  array<array{type: string, content: string}>  $values
     */
    public static function insertMany(array $values): int|false
    {
        global $wpdb;
        $values = array_map(static fn($i) => $wpdb->prepare('(%s, %s)', $i['type'], $i['content']), $values);

        return $wpdb->query(sprintf(
            'INSERT INTO %s (type, content) VALUES %s',
            $wpdb->prefix.self::TABLE,
            implode(',', $values)
        ));
    }

    public static function insert(string $type, string $content): bool
    {
        global $wpdb;

        return (bool) $wpdb->insert(
            $wpdb->prefix.self::TABLE,
            compact('type', 'content')
        );
    }

    /**
     * @return array<object{id: int, type: string, content: string}>
     */
    public static function selectQueue(): array
    {
        global $wpdb;

        return (array) $wpdb->get_results(sprintf(
            'SELECT * FROM ((%s) UNION ALL (%s) UNION ALL (%s) UNION ALL (%s)) as results',
            self::selectQuery('file'),
            self::selectQuery('host'),
            self::selectQuery('prefix'),
            self::selectQuery('tag')
        ));
    }

    private static function selectQuery(string $type): string
    {
        global $wpdb;

        return $wpdb->prepare(
            'SELECT id, type, content FROM %i WHERE type = %s LIMIT 30',
            $wpdb->prefix.self::TABLE,
            $type
        );
    }

    /**
     * @param  array<object{id: int, type: string, content: string}> $items
     */
    public static function deleteManyItems(array $items): int|bool
    {
        return static::deleteMany(array_map(static fn($i) => $i->id, $items));
    }

    public static function deleteMany(array $ids): int|bool
    {
        global $wpdb;
        $ids = array_map('\intval', $ids);

        return $wpdb->query(sprintf(
            'DELETE FROM %s WHERE id IN (%s)',
            $wpdb->prefix.self::TABLE,
            implode(',', $ids)
        ));
    }

    public static function delete(int $id): int|bool
    {
        global $wpdb;

        return $wpdb->delete(
            $wpdb->prefix.self::TABLE,
            compact('id')
        );
    }
    public static function resetTable(): void
    {
        global $wpdb;
        $wpdb->query(sprintf('TRUNCATE TABLE %s', $wpdb->prefix.self::TABLE));
    }

    /**
     * Create the table for the purge queue.
     *
     * @noinspection PhpIncludeInspection
     * @noinspection UnusedFunctionResultInspection
     *
     * @return array<string>
     */
    public static function createTable(): array
    {
        if (get_current_blog_id() !== 1) {
            return [];
        }

        require_once ABSPATH.'wp-admin/includes/upgrade.php';

        global $wpdb;
        $table_name = $wpdb->prefix.self::TABLE;

        return dbDelta(
            <<<SQL
CREATE TABLE $table_name (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	type ENUM('file', 'host', 'prefix', 'tag' ) NOT NULL,
	content VARCHAR(2048) NOT NULL,
	PRIMARY KEY (id),
	INDEX type (type)
)
SQL
        );
    }

}
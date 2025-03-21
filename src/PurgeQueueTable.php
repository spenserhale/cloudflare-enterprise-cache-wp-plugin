<?php

namespace CF\EntCache;

use WP_Error;
use function sprintf;

class PurgeQueueTable
{
    private const TABLE = 'cloudflare_purge_queue';

    /**
     * Retrieve all items from the purge queue.
     *
     * @return array<object{id: int, type: string, content: string}>
     */
    public static function all(): array
    {
        global $wpdb;

        return (array) $wpdb->get_results(sprintf(
            'SELECT id, type, content FROM %s',
            $wpdb->base_prefix.self::TABLE
        ));
    }

    /**
     * @param  array<array{type: string, content: string}>  $values
     *
     * @return array{0: int|false, 1: array<WP_Error>}
     */
    public static function insertMany(array $values): array
    {
        global $wpdb;
        [$values, $errors] = PurgeInputValidator::validateMany($values);
        if (empty($values)) {
            return [false, $errors];
        }

        $values = array_map(static fn($i) => $wpdb->prepare('(%s, %s)', $i['type'], $i['content']), $values);

        return [
            $wpdb->query(sprintf(
                'INSERT INTO %s (type, content) VALUES %s',
                $wpdb->base_prefix.self::TABLE,
                implode(',', $values)
            )), $errors,
        ];
    }

    public static function insert(string $type, string $content): bool|WP_Error
    {
        global $wpdb;

        if($validationError = PurgeInputValidator::validate($type, $content)) {
            return $validationError;
        }

        return (bool) $wpdb->insert(
            $wpdb->base_prefix.self::TABLE,
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
            $wpdb->base_prefix.self::TABLE,
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
            $wpdb->base_prefix.self::TABLE,
            implode(',', $ids)
        ));
    }

    public static function delete(int $id): int|bool
    {
        global $wpdb;

        return $wpdb->delete(
            $wpdb->base_prefix.self::TABLE,
            compact('id')
        );
    }
    public static function resetTable(): void
    {
        global $wpdb;
        $table = $wpdb->base_prefix.self::TABLE;
        $wpdb->query("TRUNCATE TABLE $table;");
        $wpdb->query("ALTER TABLE $table AUTO_INCREMENT = 1;");
    }

    /**
     * Remove all items from the purge queue that are invalid.
     * Useful if somehow bad data gets into the queue.
     *
     * @return int|bool
     */
    public static function deleteInvalidItems(): int|bool
    {
        return self::deleteManyItems(
            array_filter(
                self::all(),
                static fn ($i) => PurgeInputValidator::validate($i->type, $i->content) instanceof WP_Error
            )
        );
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
        $table_name = $wpdb->base_prefix.self::TABLE;

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

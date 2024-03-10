<?php

namespace CF\EntCache;

use WP_Error;

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
     *
     * @return array{0: int|false, 1: array<WP_Error>}
     */
    public static function insertMany(array $values): array
    {
        global $wpdb;
        [$values, $errors] = self::validateMany($values);
        if (empty($values)) {
            return [false, $errors];
        }

        $values = array_map(static fn($i) => $wpdb->prepare('(%s, %s)', $i['type'], $i['content']), $values);

        return [
            $wpdb->query(sprintf(
                'INSERT INTO %s (type, content) VALUES %s',
                $wpdb->prefix.self::TABLE,
                implode(',', $values)
            )), $errors,
        ];
    }

    public static function insert(string $type, string $content): bool|WP_Error
    {
        global $wpdb;

        if($validationError = self::validate($type, $content)) {
            return $validationError;
        }

        return (bool) $wpdb->insert(
            $wpdb->prefix.self::TABLE,
            compact('type', 'content')
        );
    }

    /**
     * @param  array<array{type: string, content: string}>  $inputs
     *
     * @return array{0: array<array{type: string, content: string}>, 1: array<WP_Error>}
     */
    public static function validateMany(array $inputs): array
    {
        $valid  = [];
        $errors = [];

        foreach ($inputs as $input) {
            $error = self::validate($input['type'] ?? '', $input['content'] ?? '');
            if ($error) {
                $errors[] = $error;
            } else {
                $valid[] = $input;
            }
        }

        return [$valid, $errors];
    }

    public static function validate(string $type, string $content): ?WP_Error
    {
        static $allowedTypes = ['file', 'host', 'prefix', 'tag'];

        if (! in_array($type, $allowedTypes, true)) {
            return new WP_Error('invalid_type', 'Invalid type', compact('type', 'content'));
        }

        return match ($type) {
            'file' => ! filter_var($content, FILTER_VALIDATE_URL) ? new WP_Error('invalid_file', 'Invalid URL', compact( 'content')) : null,
            'tag' => empty($content) ? new WP_Error('invalid_tag', 'Invalid tag', compact( 'content')) : null,
            'host' => ! filter_var($content, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) ? new WP_Error('invalid_host', 'Invalid hostname', compact( 'content')) : null,
            'prefix' => self::validatePrefix($content),
        };
    }

    private static function validatePrefix(string $content): ?WP_Error
    {
        if(str_contains($content, '://')) {
            return new WP_Error('invalid_prefix', 'Invalid prefix, must not include URI schemes');
        }

        /** @var array<string, string> $components */
        $components = parse_url('https://'.$content); // Prepend scheme to fit parse_url expectations
        if(!$components) {
            return new WP_Error('invalid_prefix', 'Invalid prefix, unable to parse', compact('content'));
        }
        if (!filter_var($components['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return new WP_Error('invalid_prefix', 'Invalid prefix, invalid hostname', compact('content'));
        }
        if (isset($components['query']) || isset($components['fragment'])) {
            return new WP_Error('invalid_prefix', 'Invalid prefix, must not include query or fragment', compact('content'));
        }

        return null;
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
        $table = $wpdb->prefix.self::TABLE;
        $wpdb->query(sprintf('TRUNCATE TABLE %s; ALTER TABLE %s AUTO_INCREMENT = 1', $table, $table));
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
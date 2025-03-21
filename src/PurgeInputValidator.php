<?php

namespace CF\EntCache;

use WP_Error;

class PurgeInputValidator
{
    /**
     * @param  array<array{type: string, content: string}>|array  $inputs
     *
     * @return array{0: array<array{type: string, content: string}>, 1: array<WP_Error>}
     */
    public static function validateMany(array $inputs): array
    {
        $valid = [];
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
        return match ($type) {
            'file' => ! filter_var($content, FILTER_VALIDATE_URL)
                ? new WP_Error('invalid_file', 'Invalid URL', compact('content'))
                : null,
            'tag' => empty($content) ? new WP_Error('invalid_tag', 'Invalid tag', compact('content')) : null,
            'host' => self::validateHost($content),
            'prefix' => self::validatePrefix($content),
            default => new WP_Error('invalid_type', 'Invalid type', compact('type', 'content'))
        };
    }

    private static function validatePrefix(string $content): ?WP_Error
    {
        if (str_contains($content, '://')) {
            return new WP_Error('invalid_prefix', 'Invalid prefix, must not include URI schemes');
        }

        /** @var array<string, string> $components */
        $components = parse_url('https://'.$content); // Prepend scheme to fit parse_url expectations

        $message = match(true) {
            ! $components => 'Invalid prefix, unable to parse',
            ! filter_var($components['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) => 'Invalid prefix, invalid hostname',
            isset($components['query']) || isset($components['fragment']) => 'Invalid prefix, must not include query or fragment',
            substr_count($components['path'], '/') > 30 => 'Invalid prefix, path contains too many segments',
            default => null
        };

        return $message ? new WP_Error('invalid_prefix', $message, compact('content')) : null;
    }

    private static function validateHost(string $content): ?WP_Error
    {
        return ! filter_var($content, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) ? new WP_Error('invalid_host', 'Invalid hostname', compact('content')) : null;
    }
}

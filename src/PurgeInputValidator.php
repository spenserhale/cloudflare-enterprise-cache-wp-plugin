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
            'file' => self::validateFile($content),
            'tag' => empty($content) ? new WP_Error('invalid_tag', 'Invalid tag', compact('content')) : null,
            'host' => self::validateHost($content),
            'prefix' => self::validatePrefix($content),
            default => new WP_Error('invalid_type', 'Invalid type', compact('type', 'content'))
        };
    }

    private static function validateFile(string $content): ?WP_Error
    {
        $message = match (true) {
            ! filter_var($content, FILTER_VALIDATE_URL) => 'Invalid URL',
            default => self::validateHost(parse_url($content, PHP_URL_HOST))?->get_error_message()
        };

        return $message ? new WP_Error('invalid_file', $message, compact('content')) : null;
    }

    private static function validateHost(string $content): ?WP_Error
    {
        $message = match (true) {
            ! filter_var($content, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) => 'Invalid hostname',
            ! preg_match('#\.[a-z]{2,13}$#', $content) => 'Invalid hostname, missing TLD',
            default => null
        };

        return $message ? new WP_Error('invalid_host', $message, compact('content')) : null;
    }

    private static function validatePrefix(string $content): ?WP_Error
    {
        if (str_contains($content, '://')) {
            return new WP_Error('invalid_prefix', 'Invalid prefix, must not include URI schemes');
        }

        /** @var array<string, string> $components */
        $components = parse_url('https://'.$content); // Prepend scheme to fit parse_url expectations

        $message = match (true) {
            ! $components => 'Invalid prefix, unable to parse',
            isset($components['query']) || isset($components['fragment']) => 'Invalid prefix, must not include query or fragment',
            substr_count($components['path'], '/') > 30 => 'Invalid prefix, path contains too many segments',
            default => self::validateHost($components['host'])?->get_error_message()
        };

        return $message ? new WP_Error('invalid_prefix', $message, compact('content')) : null;
    }
}

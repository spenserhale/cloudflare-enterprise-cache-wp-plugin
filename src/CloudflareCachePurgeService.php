<?php

namespace CF\EntCache;

use WP_Error;

class CloudflareCachePurgeService
{

    /**
     * @param array<array{type:string, content: string}> $queue
     */
    public function __construct(
        private array $queue = []
    ) {
        add_action('shutdown', [$this, 'onShutdown']);
    }

    public function addFile(string $url): true|WP_Error {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid URL', compact('url'));
        }
        $this->queue[] = ['type' => 'file', 'content' => $url];
        return true;
    }

    public function addTag(string $tag): true|WP_Error {
        if (empty($tag)) {
            return new WP_Error('invalid_tag', 'Tag cannot be empty');
        }
        $this->queue[] = ['type' => 'tag', 'content' => $tag];
        return true;
    }

    public function addHost(string $host): true|WP_Error {
        if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return new WP_Error('invalid_host', 'Invalid host', compact('host'));
        }
        $this->queue[] = ['type' => 'host', 'content' => $host];
        return true;
    }

    public function addPrefix(string $prefix): true|WP_Error {
        if(str_contains($prefix, '://')) {
            return new WP_Error('invalid_prefix', 'Invalid prefix, must not include URI schemes');
        }

        /** @var array<string, string> $components */
        $components = parse_url('https://' . $prefix); // Prepend scheme to fit parse_url expectations
        if(!$components) {
            return new WP_Error('invalid_prefix', 'Invalid prefix, unable to parse', compact('prefix'));
        }
        if (!filter_var($components['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return new WP_Error('invalid_prefix', 'Invalid prefix, invalid hostname', compact('prefix'));
        }
        if (isset($components['query']) || isset($components['fragment'])) {
            return new WP_Error('invalid_prefix', 'Invalid prefix, must not include query or fragment', compact('prefix'));
        }
        $this->queue[] = ['type' => 'prefix', 'content' => $prefix];
        return true;
    }

    public function onShutdown(): void
    {
        if (empty($this->queue)) {
            return;
        }

        $queued = $this->queue();
        if($queued instanceof WP_Error) {
            Logger::error('Failed to queue items for purge on shutdown', ['error' => $queued]);

            $response = $this->purge();
            if($response?->isSuccess()) {
                Logger::info('Successfully purged cache on shutdown', ['response' => $response]);
            } else {
                Logger::error('Failed to purge cache on shutdown', ['response' => $response]);
            }

            return;
        }

        if($queued > 0) {
            Logger::info('Queued items for purge on shutdown', ['count' => $queued]);
        }
    }

    public function queue(): int|WP_Error
    {
        if (empty($this->queue)) {
            return 0;
        }
        $inserted = PurgeQueueTable::insertMany($this->queue);
        if(!$inserted) {
            $error = new WP_Error('insert_error', 'Failed to insert items into purge queue', ['queue' => $this->queue]);
            Logger::logWpError($error);
            return $error;
        }
        $this->queue = [];

        return $inserted;
    }

    public function purge(): null|ApiResponse {
        if (empty($this->queue)) {
            return null;
        }
        $response = ApiClient::purgeCacheStatic(ApiClient::makePurgeRequest($this->queue));
        if(!$response->isSuccess()) {
            Logger::error('Failed to purge queue', [
                'queue' => $this->queue,
                'response' => $response,
            ]);
            return $response;
        }
        $this->queue = [];

        return $response;
    }
}
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

    public function addFile(string $url): null|WP_Error {
        return $this->addItem('file', $url);
    }

    public function addTag(string $tag): null|WP_Error {
        return $this->addItem('tag', $tag);
    }

    public function addHost(string $host): null|WP_Error {
        return $this->addItem('host', $host);
    }

    public function addPrefix(string $prefix): null|WP_Error {
        return $this->addItem('prefix', $prefix);
    }

    public function addItem(string $type, string $content): ?WP_Error
    {
        if($validationError = PurgeInputValidator::validate($type, $content)) {
            return $validationError;
        }

        $this->queue[] = ['type' => $type, 'content' => $content];
        return null;
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
        [$inserted, $errors] = PurgeQueueTable::insertMany($this->queue);
        if(!$inserted) {
            $error = new WP_Error('insert_error', 'Failed to insert items into purge queue', ['queue' => $this->queue, 'errors' => $errors]);
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

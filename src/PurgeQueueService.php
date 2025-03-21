<?php

namespace CF\EntCache;

use Throwable;
use WP_Error;

class PurgeQueueService {

	/**
     * @return array<object{id: int, type: string, content: string}>|WP_Error The items that were purged, or a WP_Error.
     */
	public static function processQueue(): array|WP_Error {
        $items = PurgeQueueTable::selectQueue();

        if (empty($items)) {
            Logger::debug('No items in purge queue');

            return $items;
        }

        try {
            $response = ApiClient::getInstance()->purgeCache(ApiClient::makePurgeRequest($items));
        } catch (Throwable $e) {
            $error = new WP_Error(
                'purge_error',
                'Failed to purge cache',
                ['exception' => $e]
            );
            Logger::logWpError($error);
            return $error;
        }

        if ( ! $response->isSuccess()) {
            $error = new WP_Error(
                'purge_error',
                'Failed to purge cache',
                ['response' => $response]
            );
            Logger::logWpError($error);
            return $error;
        }

        $result = PurgeQueueTable::deleteManyItems($items);
        if ($result === false) {
            $error = new WP_Error(
                'purge_queue_error',
                'Failed to delete items from purge queue',
                [
                    'items' => $items,
                    'error' => $GLOBALS['wpdb']->last_error,
                ]
            );
            Logger::logWpError($error);
            return $error;
        }

        Logger::debug('Successfully purged cache', [
            'items' => $items,
        ]);

        return $items;
	}

}

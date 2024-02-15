<?php

namespace CF\EntCache;

use WP_Error;

class PurgeQueueService {

	/**
     * @return array<object{id: int, type: string, content: string}>|WP_Error The items that were purged, or a WP_Error.
     */
	public static function processQueue(): array|WP_Error {
		$items = PurgeQueueTable::selectQueue();

		if ( empty( $items ) ) {
			Logger::debug( 'No items in purge queue');
			return $items;
		}

		$response = ApiClient::getInstance()->purgeCache( self::makePurgeRequest( $items ) );

		if ( ! $response->isSuccess() ) {
            $message = 'Failed to purge cache';
            $context = [
                'response' => $response,
            ];
            Logger::error( $message, $context);
			return new WP_Error( 'purge_error', $message, $context );
		}

		$result = PurgeQueueTable::deleteManyItems( $items );
		if ( $result === false ) {
            $message = 'Failed to delete items from purge queue';
            $context = [
                'items' => $items,
                'error' => PurgeQueueTable::lastDbError(),
            ];
            Logger::error( $message, $context);
			return new WP_Error( 'purge_queue_error', $message, $context );
		}

		Logger::debug( 'Successfully purged cache', [
			'items' => $items,
		]);

		return $items;
	}

    /**
     * Group the items by type to format for making a purge request.
     *
     * @param  array<object{type: string, content: string}>  $items
     *
     * @return array{
     *       files?: string[],
     *       hosts?: string[],
     *       prefixes?: string[],
     *       tags?: string[]
     *   }
     */
	protected static function makePurgeRequest( array $items ): array {
		$requestData = [];
		foreach ( $items as $item ) {
			if ( ! isset( $requestData[ $item->type ] ) ) {
				$requestData[ $item->type ] = [];
			}

			$requestData[ $item->type ][] = $item->content;
		}

		return $requestData;
	}
}

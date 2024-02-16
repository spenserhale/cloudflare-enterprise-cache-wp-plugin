<?php
/**
 * Plugin Name: Cloudflare Enterprise Cache
 * Description: Automation for managing Cloudflare enterprise cache for WordPress sites
 * Version: 1.0.0
 * Requires PHP: 8.2
 * Author: Spenser Hale
 * Author URI: https://www.spenserhale.com/
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: cf-ent-cache
 * Domain Path: /languages
 * Network: true
 *
 * Cloudflare CacheWise WordPress Plugin
 * Copyright (C) 2024 Spenser Hale
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * Additional Permission Under GNU GPL version 3 section 7:
 *
 * The name "Spenser Hale" and its associated
 * branding elements are protected by copyright/trademark law and may not
 * be used in association with derivative works that are not owned by
 * Spenser Hale without express written permission.
 *
 * Disclaimer: This plugin is not affiliated with, endorsed by, or in any
 * way officially connected to Cloudflare, Inc. The name “Cloudflare” and
 * related trademarks and logos are the property of Cloudflare, Inc.
 * This plugin is independently developed by Spenser Hale and is intended
 * for use with Cloudflare services by users under their own responsibility.
 */

namespace CF\EntCache;

use function add_filter;
use function register_activation_hook;
use function spl_autoload_register;

if ( PHP_VERSION_ID < 80200 ) {
	_doing_it_wrong( __FILE__, 'Cloudflare Enterprise Cache requires PHP 8.2 or higher.', '1.0.0' );

	return;
}

function class_autoloader(string $class): void
{
	if ( str_starts_with( $class, __NAMESPACE__ ) ) {
		static $classMap = [
			ApiClient::class => __DIR__ . '/src/ApiClient.php',
			ApiResponse::class => __DIR__ . '/src/ApiResponse.php',
			Logger::class => __DIR__ . '/src/Logger.php',
			LogLevel::class => __DIR__ . '/src/LogLevel.php',
			PurgeQueueService::class => __DIR__ . '/src/PurgeQueueService.php',
			PurgeQueueTable::class => __DIR__ . '/src/PurgeQueueTable.php',
		];

		if(isset($classMap[$class])) {
			require_once $classMap[$class];
		}
	}
}

spl_autoload_register( '\CF\EntCache\class_autoloader' );

register_activation_hook( __FILE__, '\CF\EntCache\PurgeQueueTable::createTable' );

add_filter( 'cf/ent_cache/create_table', '\CF\EntCache\PurgeQueueTable::createTable' );
add_filter( 'cf/ent_cache/queue_many', '\CF\EntCache\PurgeQueueTable::insertMany' );
add_filter( 'cf/ent_cache/process_queue', '\CF\EntCache\PurgeQueueService::processQueue' );
add_filter( 'cf/ent_cache/purge_cache', '\CF\EntCache\ApiClient::purgeCacheStatic' );

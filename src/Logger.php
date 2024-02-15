<?php

namespace CF\EntCache;

/**
 * @method static void debug( string $message, array $context = [] )
 * @method static void info( string $message, array $context = [] )
 * @method static void notice( string $message, array $context = [] )
 * @method static void warning( string $message, array $context = [] )
 * @method static void error( string $message, array $context = [] )
 * @method static void critical( string $message, array $context = [] )
 * @method static void alert( string $message, array $context = [] )
 * @method static void emergency( string $message, array $context = [] )
 */
class Logger {

	public static function __callStatic( string $name, array $arguments ): void {
		self::log($name, ... $arguments );
	}

	public static function log( string $level, string $message, array $context = [] ): void {
		static $logLevel;

		if ( ! isset( $logLevel ) ) {
			$logLevel = LogLevel::from( apply_filters( 'cf/ent_cache/log_level', 5 ) );
		}

		if ( LogLevel::fromString($level)->value >= $logLevel->value ) {
			do_action( 'cf/ent_cache/log', $level, $message, $context );
		}
	}

}
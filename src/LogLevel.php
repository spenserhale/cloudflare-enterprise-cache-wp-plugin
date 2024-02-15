<?php

namespace CF\EntCache;

enum LogLevel: int {
	case DEBUG = 1;
	case INFO = 2;
	case NOTICE = 3;
	case WARNING = 4;
	case ERROR = 5;
	case CRITICAL = 6;
	case ALERT = 7;
	case EMERGENCY = 8;

	public static function fromString( string $level ): self {
		return match ( $level ) {
			'debug' => self::DEBUG,
			'info' => self::INFO,
			'notice' => self::NOTICE,
			'warning' => self::WARNING,
			'error' => self::ERROR,
			'critical' => self::CRITICAL,
			'alert' => self::ALERT,
			default => self::EMERGENCY,
		};
	}
}
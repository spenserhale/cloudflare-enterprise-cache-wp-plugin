<?php

namespace CF\EntCache;

use JsonSerializable;
use stdClass;
use Throwable;
use WP_Error;

class ApiResponse implements JsonSerializable {

	public static function fromStdClass( stdClass $stdClass ): self {
		return new self(
			$stdClass->success,
			$stdClass->errors,
			$stdClass->messages,
			$stdClass->result
		);
	}

	public static function fromException( Throwable $exception ): self {
		$errors = [];
		do {
			$errors[] = (object) [
				'code' => $exception->getCode(),
				'message' => $exception->getMessage(),
			];
		} while ( $exception = $exception->getPrevious() );

		return new self( false, $errors, [], null );
	}

	public static function fromWpError( WP_Error $error ): self {
		$errors = [];
		foreach ( $error->get_error_codes() as $code ) {
			$errors[] = (object) [
				'code' => $code,
				'message' => $error->get_error_message( $code ),
			];
		}

		return new self( false, $errors, [], null );
	}

	public static function asFailure(): self {
		return new self( false, [], [], null );
	}

	/**
	 * @param bool $success
	 * @param array<array{ code: int|string, message: string }> $errors
	 * @param array<array{ code: int|string, message: string }> $messages
	 * @param mixed $result
	 */
	public function __construct(
		public readonly bool $success,
        public array $errors,
        public array $messages,
        public mixed $result,
	) {
	}

	public function isSuccess(): bool {
		return $this->success;
	}

    /**
     * @return array<array{ code: int|string, message: string }>
     */
	public function getErrors(): array {
		return $this->errors;
	}

    /**
     * @return array<array{ code: int|string, message: string }>
     */
	public function getMessages(): array {
		return $this->messages;
	}

	public function getResult(): mixed {
		return $this->result;
	}

	public function addMessage(string|int $code, string $message): self {
		$this->messages[] = (object) [
			'code' => $code,
			'message' => $message,
		];

		return $this;
	}

	public function addError(string|int $code, string $message): self {
		$this->errors[] = (object) [
			'code' => $code,
			'message' => $message,
		];

		return $this;
	}

	public function setResult(mixed $result): self {
		$this->result = $result;

		return $this;
	}

    public function jsonSerialize(): array
    {
        return [
            'success' => $this->success,
            'errors' => $this->errors,
            'messages' => $this->messages,
            'result' => $this->result,
        ];
    }


}
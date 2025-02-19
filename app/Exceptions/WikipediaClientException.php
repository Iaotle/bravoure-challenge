<?php
declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class WikipediaClientException extends Exception {
	public function __construct(string $message = 'Wikipedia client error', int $code = 0, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}

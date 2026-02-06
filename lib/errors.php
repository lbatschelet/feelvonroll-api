<?php
/**
 * API error helpers for exception-based flow.
 * Exports: ApiError, handle_api_exception.
 */

/**
 * Exception carrying an HTTP status code.
 */
class ApiError extends Exception
{
    private $statusCode;

    /**
     * @param string $message
     * @param int $statusCode
     */
    public function __construct(string $message, int $statusCode = 400)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

/**
 * Handles API exceptions and outputs JSON error.
 *
 * @param Throwable $error
 * @return void
 */
function handle_api_exception(Throwable $error): void
{
    error_log($error);
    if ($error instanceof ApiError) {
        http_response_code($error->getStatusCode());
        echo json_encode(['error' => $error->getMessage()]);
        exit;
    }
    http_response_code(500);
    $debug = defined('API_DEBUG') ? API_DEBUG : (getenv('API_DEBUG') === '1');
    if ($debug) {
        echo json_encode(['error' => $error->getMessage()]);
        exit;
    }
    echo json_encode(['error' => 'Server error']);
    exit;
}

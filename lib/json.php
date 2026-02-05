<?php
/**
 * JSON response/request helpers for API endpoints.
 * Exports: json_response, json_error, json_request.
 */

/**
 * Sends a JSON response and exits.
 *
 * @param array $data
 * @param int $status
 * @return void
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/**
 * Throws an API error for JSON responses.
 *
 * @param string $message
 * @param int $status
 * @return void
 * @throws ApiError
 */
function json_error(string $message, int $status = 400): void
{
    throw new ApiError($message, $status);
}

/**
 * Parses JSON request body.
 *
 * @return array
 * @throws ApiError
 */
function json_request(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data) {
        throw new ApiError('Invalid JSON', 400);
    }
    return $data;
}

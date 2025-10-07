<?php

declare(strict_types=1);

namespace JsonApi\Symfony\StressTest;

/**
 * Simple HTTP Client for Stress Tests
 * 
 * Uses cURL for making HTTP requests to the stress test server.
 */
final class HttpClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct(string $baseUrl = 'http://127.0.0.1:8765', int $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function get(string $path, array $query = [], array $headers = []): array
    {
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $this->request('GET', $url, null, $headers);
    }

    /**
     * @param array<string, mixed>|string $body
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function post(string $path, $body = null, array $headers = []): array
    {
        $url = $this->baseUrl . $path;
        
        if (is_array($body)) {
            $body = json_encode($body);
            $headers['Content-Type'] = 'application/vnd.api+json';
        }

        return $this->request('POST', $url, $body, $headers);
    }

    /**
     * @param array<string, mixed>|string $body
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function patch(string $path, $body = null, array $headers = []): array
    {
        $url = $this->baseUrl . $path;

        if (is_array($body)) {
            $body = json_encode($body);
        }

        if ($body !== null && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/vnd.api+json';
        }

        return $this->request('PATCH', $url, $body, $headers);
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function delete(string $path, array $headers = []): array
    {
        $url = $this->baseUrl . $path;
        return $this->request('DELETE', $url, null, $headers);
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function request(string $method, string $url, ?string $body, array $headers): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = "$name: $value";
        }
        if ($curlHeaders !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        }

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("HTTP request failed: $error");
        }

        $headerText = substr($response, 0, $headerSize);
        $bodyText = substr($response, $headerSize);

        $responseHeaders = $this->parseHeaders($headerText);

        return [
            'status' => $statusCode,
            'headers' => $responseHeaders,
            'body' => $bodyText,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $headerText): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerText);

        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * Check if server is running
     */
    public function isServerRunning(): bool
    {
        try {
            $ch = curl_init($this->baseUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}


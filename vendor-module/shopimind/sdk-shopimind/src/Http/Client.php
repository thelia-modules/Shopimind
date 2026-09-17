<?php
namespace Shopimind\SdkShopimind\Http;

use Shopimind\SdkShopimind\Exception\ClientException;

class Client
{
    private $baseUri;
    private $defaultHeaders;
    private $timeout;

    public function __construct(array $config = array())
    {
        $this->baseUri = isset($config['base_uri']) ? rtrim($config['base_uri'], '/') . '/' : '';
        $this->defaultHeaders = isset($config['headers']) ? $config['headers'] : array();
        $this->timeout = isset($config['timeout']) ? (int) $config['timeout'] : 30;
    }

    public function get($endpoint, array $options = array())
    {
        return $this->request('GET', $endpoint, $options);
    }

    public function post($endpoint, array $options = array())
    {
        return $this->request('POST', $endpoint, $options);
    }

    public function put($endpoint, array $options = array())
    {
        return $this->request('PUT', $endpoint, $options);
    }

    public function delete($endpoint, array $options = array())
    {
        return $this->request('DELETE', $endpoint, $options);
    }

    public function request($method, $endpoint, array $options = array())
    {
        $url = $this->buildUrl($endpoint, isset($options['query']) ? $options['query'] : null);

        $headers = array_merge(
            $this->defaultHeaders,
            isset($options['headers']) ? $options['headers'] : array()
        );

        $body = null;
        if (array_key_exists('json', $options)) {
            $body = json_encode($options['json']);
            $headers['Content-Type'] = 'application/json';
        } elseif (isset($options['body'])) {
            $body = $options['body'];
        } elseif (isset($options['form_params']) && is_array($options['form_params'])) {
            $body = http_build_query($options['form_params']);
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt(
            $ch,
            CURLOPT_TIMEOUT,
            isset($options['timeout']) ? (int) $options['timeout'] : $this->timeout
        );
        curl_setopt(
            $ch,
            CURLOPT_CONNECTTIMEOUT,
            isset($options['connect_timeout']) ? (int) $options['connect_timeout'] : 10
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            throw new \RuntimeException('cURL error (' . $errno . '): ' . $err);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($raw, 0, $headerSize);
        $responseBody = substr($raw, $headerSize);
        $responseHeaders = $this->parseHeaders($rawHeaders);

        $response = new Response($statusCode, $responseBody, $responseHeaders);

        if ($statusCode >= 400 && $statusCode < 500) {
            throw new ClientException(
                'Client error: HTTP ' . $statusCode . ' for ' . $url,
                $response,
                $statusCode
            );
        }

        if ($statusCode >= 500) {
            throw new \RuntimeException(
                'Server error: HTTP ' . $statusCode . ' for ' . $url,
                $statusCode
            );
        }

        return $response;
    }

    private function buildUrl($endpoint, $query)
    {
        $url = $this->baseUri . ltrim((string) $endpoint, '/');
        if (!empty($query) && is_array($query)) {
            $sep = strpos($url, '?') === false ? '?' : '&';
            $url .= $sep . http_build_query($query);
        }
        return $url;
    }

    private function formatHeaders(array $headers)
    {
        $out = array();
        foreach ($headers as $name => $value) {
            $out[] = $name . ': ' . $value;
        }
        return $out;
    }

    private function parseHeaders($raw)
    {
        $headers = array();
        // Handle multiple header blocks (redirects): keep only the last one
        $blocks = preg_split("/\r\n\r\n/", trim((string) $raw));
        $lastBlock = is_array($blocks) && count($blocks) > 0 ? end($blocks) : '';

        foreach (explode("\r\n", $lastBlock) as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }
            list($k, $v) = explode(':', $line, 2);
            $headers[trim($k)] = trim($v);
        }
        return $headers;
    }
}

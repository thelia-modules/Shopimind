<?php
namespace Shopimind\SdkShopimind\Http;

class Response
{
    private $statusCode;
    private $body;
    private $headers;

    public function __construct($statusCode, $body, array $headers = array())
    {
        $this->statusCode = (int) $statusCode;
        $this->body = new Stream($body);
        $this->headers = $headers;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getHeader($name)
    {
        $name = strtolower($name);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $name) {
                return $v;
            }
        }
        return null;
    }
}

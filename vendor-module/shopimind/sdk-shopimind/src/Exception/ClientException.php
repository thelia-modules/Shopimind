<?php
namespace Shopimind\SdkShopimind\Exception;

use Shopimind\SdkShopimind\Http\Response;

class ClientException extends \RuntimeException
{
    private $response;

    public function __construct($message, Response $response = null, $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    public function hasResponse()
    {
        return $this->response !== null;
    }

    public function getResponse()
    {
        return $this->response;
    }
}

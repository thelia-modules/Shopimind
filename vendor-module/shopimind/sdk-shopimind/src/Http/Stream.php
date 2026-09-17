<?php
namespace Shopimind\SdkShopimind\Http;

class Stream
{
    private $content;

    public function __construct($content)
    {
        $this->content = (string) $content;
    }

    public function __toString()
    {
        return $this->content;
    }

    public function getContents()
    {
        return $this->content;
    }
}

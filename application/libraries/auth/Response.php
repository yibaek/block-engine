<?php
namespace libraries\auth;

class Response extends \OAuth2\Response
{
    private $contents;

    /**
     * @return string
     */
    public function getContents(): string
    {
        return $this->contents;
    }

    /**
     * @param string $contents
     */
    public function setContents(string $contents)
    {
        $this->contents = $contents;
    }
}
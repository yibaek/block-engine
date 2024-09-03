<?php
namespace libraries\auth;

class Request extends \OAuth2\Request
{
    public function addRequest(array $request)
    {
        $this->request = array_merge($this->request, $request);
    }
}
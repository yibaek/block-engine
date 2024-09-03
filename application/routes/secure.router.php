<?php
use middleware\Common;
use Slim\App;

/** @var App $app */
$app->group('/secure', function () {
    $this->post('/getCommand', 'controllers\SecureProtocol:getCommand')->add(new Common($this->getContainer()));
});
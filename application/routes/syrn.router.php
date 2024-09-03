<?php
use middleware\Common;
use Slim\App;

/** @var App $app */
$app->group('/syrn', function () {
    $this->post('/batch', 'controllers\SyrnCommand:runBatch')->add(new Common($this->getContainer()));
});
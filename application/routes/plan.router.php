<?php
use middleware\Common;
use middleware\SetPlanData;
use Slim\App;

/** @var App $app */
$app->group('/plan', function () {
    $this->post('/entrance', 'controllers\Entrance:index')->add(new SetPlanData($this->getContainer()))->add(new Common($this->getContainer()))
        ->setName('plan/entrance');
    $this->map(['GET', 'POST'], '/resource/{environment}/{version}/{id}[/{revision}]', 'controllers\Entrance:index')->add(new SetPlanData($this->getContainer()))->add(new Common($this->getContainer()))
        ->setName('plan/resource');
});

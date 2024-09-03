<?php
use middleware\Common;
use middleware\Proxy;
use middleware\RateLimitMiddlware;
use middleware\SetPlanData;

/** @var Slim\App $app */
$app->group('/proxy', function () {
    $this->map(['*'], '', 'controllers\Entrance:index')
        ->add(new SetPlanData($this->getContainer()))
        ->add(new Common($this->getContainer()))
        ->add(new Proxy($this->getContainer()->get('studio_rdb')))
        ->add(new RateLimitMiddlware($this->getContainer()->get('studio_rdb')))
        ->setName('plan/entrance');
});

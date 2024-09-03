<?php

use Slim\App;
use Psr\Container\ContainerInterface;

use models\redis\RedisMgr;
use models\rdb\RDbManager;
use models\rdb\IRdbMgr;
use domains\proxy\log\implementations\ReadProxyLogShardDBConnInfo;
use domains\proxy\log\implementations\SaveProxyLog;
use domains\proxy\log\repositories\IReadProxyLogShardDBConnInfo;
use domains\proxy\log\repositories\ISaveProxyLog;

/** @var App $app */
$container = $app->getContainer();

$container[RDbManager::class] = static function () {
    return new RDbManager();
};

$container['studio_rdb'] = static function (ContainerInterface $container) {
    return $container->get(RDbManager::class)->getRdbMgr('studio');
};

$container[IReadProxyLogShardDBConnInfo::class] = static function (ContainerInterface $container) {
    /** @var RedisMgr $redisMgr */
    $redisMgr = $container->get('redis');

    /** @var RDbManager $rdbMgr */
    $rdbMgr = $container->get(RDbManager::class);
    $portalDB = $rdbMgr->getRdbMgr('portal');

    /** @var IRdbMgr $studioDB */
    $studioDB = $container->get('studio_rdb');
    return new ReadProxyLogShardDBConnInfo($redisMgr, $portalDB, $studioDB);
};

$container[ISaveProxyLog::class] = static function (ContainerInterface $container) {
    /** @var RDbManager $rdbMgr */
    $rdbMgr = $container->get(RDbManager::class);
    $rdb = $rdbMgr->getProxyShardMgr('log');
    /** @var IReadProxyLogShardDBConnInfo $repo */
    $repo = $container->get(IReadProxyLogShardDBConnInfo::class);
    return new SaveProxyLog($rdb, $repo);
};

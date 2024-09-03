<?php
use middleware\Common;
use Slim\App;

/** @var App $app */
$app->group('/auth', function () {
    $this->group('/credential', function () {
        $this->post('/join', 'controllers\Authorization:joinCredential')->add(new Common($this->getContainer()));
        $this->put('/join', 'controllers\Authorization:reJoinCredential')->add(new Common($this->getContainer()));
        $this->delete('/join', 'controllers\Authorization:deleteJoinCredential')->add(new Common($this->getContainer()));
    });
    $this->group('/oauth2', function () {
        $this->post('/key', 'controllers\Authorization:generateOauth2Key')->add(new Common($this->getContainer()));
        $this->delete('/key', 'controllers\Authorization:deleteOauth2Key')->add(new Common($this->getContainer()));
        $this->post('/token', 'controllers\Authorization:generateOauth2Token')->add(new Common($this->getContainer()));
        $this->post('/validate', 'controllers\Authorization:validateOauth2Token')->add(new Common($this->getContainer()));
        $this->post('/authorize', 'controllers\Authorization:authorizeOauth2')->add(new Common($this->getContainer()));
        $this->post('/revoke', 'controllers\Authorization:revokeOauth2Token')->add(new Common($this->getContainer()));
    });
    $this->group('/simplekey', function () {
        $this->post('/key', 'controllers\Authorization:generateSimpleKey')->add(new Common($this->getContainer()));
        $this->delete('/key', 'controllers\Authorization:deleteSimpleKey')->add(new Common($this->getContainer()));
        $this->post('/validate', 'controllers\Authorization:validateSimpleKey')->add(new Common($this->getContainer()));
    });
    $this->group('/saml2', function () {
        $this->post('/authn', 'controllers\Authorization:generateSaml2AuthnRequest')->add(new Common($this->getContainer()));
        $this->post('/assertion', 'controllers\Authorization:generateSaml2Assertion')->add(new Common($this->getContainer()));
        $this->post('/assertion/validate', 'controllers\Authorization:validateSaml2Assertion')->add(new Common($this->getContainer()));
    });
});

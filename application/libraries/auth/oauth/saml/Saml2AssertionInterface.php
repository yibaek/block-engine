<?php
namespace libraries\auth\oauth\saml;

interface Saml2AssertionInterface
{
    public function validate();
}
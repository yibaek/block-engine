<?php
namespace libraries\auth;

interface IAuthorization
{
    public function generateKey(): array;
    public function deleteKey(): bool;
    public function authorize(): IAuthorization;
    public function generateToken(): IAuthorization;
    public function revokeToken(): IAuthorization;
    public function validation() :IAuthorization;
    public function isValid(): bool;
    public function isRequiredValid(): bool;
    public function getStatusCode(): int;
    public function getHeaders(): array;
    public function getBodys(): array;
    public function getTokenData(): ?array;
    public function isAuthorizeValidateOnly(): bool;
    public function getValidateData(): array;
}
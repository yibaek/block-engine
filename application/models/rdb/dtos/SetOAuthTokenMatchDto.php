<?php
namespace models\rdb\dtos;

use Exception;

class SetOAuthTokenMatchDto
{
    private $access_token_id;
    private $refresh_token_id;
    private $access_token;
    private $refresh_token;

    /**
     * @param array $data
     * @throws Exception
     */
    public function __construct(array $data = [])
    {
        $this->access_token_id  = $data['access_token_id'] ?? null;
        $this->refresh_token_id = $data['refresh_token_id'] ?? null;
        $this->access_token     = $data['access_token'] ?? null;
        $this->refresh_token    = $data['refresh_token'] ?? null;

        if (!$this->isValid()) {
            throw new Exception("Error Processing Request", 1);
        }
    }

    private function isValid(): bool
    {
        $has_auth = $this->access_token_id || $this->access_token;
        $has_refresh = $this->refresh_token_id || $this->refresh_token;

        return $has_auth && $has_refresh;
    }

    public function getData(): array
    {
        return [
            'access_token_id'  => $this->access_token_id,
            'refresh_token_id' => $this->refresh_token_id,
            'access_token'     => $this->access_token,
            'refresh_token'    => $this->refresh_token,
        ];
    }
}
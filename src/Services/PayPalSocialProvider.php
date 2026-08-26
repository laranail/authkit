<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Services;

use RuntimeException;
use GuzzleHttp\RequestOptions;
use SocialiteProviders\Manager\OAuth2\User;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;

class PayPalSocialProvider extends AbstractProvider
{
    /** @var array<int, string> */
    protected $scopes = ['openid', 'profile', 'email'];

    protected $scopeSeparator = ' ';

    /** @return array<string, mixed> */
    public function getAccessTokenResponse($code): array
    {
        $response = $this->getHttpClient()->post(
            uri: $this->getTokenUrl(),
            options: [
                RequestOptions::HEADERS => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(string: "{$this->clientId}:{$this->clientSecret}"),
                ],
                RequestOptions::FORM_PARAMS => $this->getTokenFields(code: $code),
            ],
        );

        $data = json_decode(json: (string) $response->getBody(), associative: true);

        if (! is_array($data)) {
            throw new RuntimeException('Unable to decode PayPal access token response.');
        }

        return $data;
    }

    protected function useSandbox(): bool
    {
        return config(key: 'laranail.authkit.social.paypal.sandbox_mode', default: true);
    }

    protected function getWebBaseUrl(): string
    {
        return $this->useSandbox()
            ? 'https://sandbox.paypal.com'
            : 'https://www.paypal.com';
    }

    protected function getApiBaseUrl(): string
    {
        return $this->useSandbox()
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(
            url: $this->getWebBaseUrl() . '/signin/authorize',
            state: $state,
        );
    }

    protected function getTokenUrl(): string
    {
        return $this->getApiBaseUrl() . '/v1/oauth2/token';
    }

    /** @return array<string, mixed> */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(
            uri: $this->getApiBaseUrl() . '/v1/identity/openidconnect/userinfo',
            options: [
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                RequestOptions::QUERY => [
                    'schema' => 'openid',
                ],
            ],
        );

        $data = json_decode(json: (string) $response->getBody(), associative: true);

        if (! is_array($data)) {
            throw new RuntimeException('Unable to decode PayPal user info response.');
        }

        return $data;
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User())
            ->setRaw(user: $user)
            ->map(attributes: [
                'id'         => basename(path: $user['user_id']),
                'nickname'   => null,
                'name'       => $user['name'] ?? null,
                'email'      => $user['email'] ?? null,
                'avatar'     => null,
                'attributes' => [
                    'email_verified'   => $user['email_verified'] ?? null,
                    'verified'         => $user['verified'] ?? null,
                    'payer_id'         => $user['payer_id'] ?? null,
                    'verified_account' => $user['verified_account'] ?? null,
                ],
            ]);
    }
}

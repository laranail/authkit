<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class Turnstile
{
    public function __construct(
        protected string $url,
        protected string $secretKey,
    ) {}

    public function validate(string $token): bool
    {
        if ($token === '' || $this->secretKey === '' || $this->url === '') {
            return false;
        }

        try {
            $response = Http::retry(4, 100)
                ->acceptJson()
                ->asForm()
                ->post(
                    url: $this->url,
                    data: [
                        'response' => $token,
                        'secret' => $this->secretKey,
                    ],
                );
        } catch (Throwable) {
            return false;
        }

        return $response->successful() && $response->json(key: 'success') === true;
    }
}

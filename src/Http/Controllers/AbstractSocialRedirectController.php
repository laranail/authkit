<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers;

use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Contracts\SocialRedirectActionInterface;

abstract class AbstractSocialRedirectController extends AbstractAuthController
{
    public function __invoke(Request $request, SocialRedirectActionInterface $action): mixed
    {
        $result = $action->execute(
            request: $request,
        );

        if ($request->expectsJson()) {
            return $this->jsonResponse(status: 'passed', data: ['url' => $result->url]);
        }

        return $this->redirect(request: $request, url: $result->url);
    }

    protected function redirect(Request $request, string $url): mixed
    {
        return redirect()->to(path: $url);
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Support;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;

class UserModelResolver
{
    public static function resolve(?string $guard = null): string
    {
        $model = config('laranail.authkit.user_model');

        if (! is_string($model) || $model === '') {
            $guard ??= config('laranail.authkit.guard', 'web');
            $provider = config("auth.guards.{$guard}.provider", config('auth.defaults.provider'));
            $model = config("auth.providers.{$provider}.model");
        }

        if (! is_string($model) || ! is_a($model, Model::class, allow_string: true) || ! is_a($model, Authenticatable::class, allow_string: true)) {
            throw new LogicException('The configured laranail/authkit user model must be an Eloquent Authenticatable model.');
        }

        return $model;
    }
}

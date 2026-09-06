<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit;

use Laravel\Passkeys\Passkeys;
use Laravel\Fortify\Contracts\PasskeyUser;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Simtabi\Laranail\AuthKit\Relations\PasskeyMorphMany;
use Laravel\Passkeys\PasskeyAuthenticatable as BasePasskeyAuthenticatable;

/**
 * @phpstan-require-implements PasskeyUser
 */
trait PasskeyAuthenticatable
{
    use BasePasskeyAuthenticatable {
        passkeys as protected passkeysWithUserId;
    }

    public function passkeys(): HasMany
    {
        $passkeyModel = Passkeys::passkeyModel();

        return new PasskeyMorphMany(
            query: $passkeyModel::query(),
            parent: $this,
            morphType: 'passkeyable_type',
            foreignKey: 'passkeyable_id',
            localKey: $this->getKeyName(),
        );
    }
}

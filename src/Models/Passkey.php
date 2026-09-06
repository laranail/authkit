<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Models;

use Laravel\Passkeys\Passkey as BasePasskey;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Passkey extends BasePasskey
{
    public function user(): MorphTo
    {
        return $this->morphTo(
            name: 'user',
            type: 'passkeyable_type',
            id: 'passkeyable_id',
        );
    }

    public function passkeyable(): MorphTo
    {
        return $this->morphTo(
            name: 'passkeyable',
            type: 'passkeyable_type',
            id: 'passkeyable_id',
        );
    }

    public function getUserIdAttribute(): mixed
    {
        return $this->getAttribute('passkeyable_id');
    }
}

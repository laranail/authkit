<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Enums;

use Simtabi\Laranail\Enumerator\Attributes\Label;
use Simtabi\Laranail\Enumerator\Contracts\Enumerator;
use Simtabi\Laranail\Enumerator\Concerns\HasEnumerator;

enum SocialProvider: string implements Enumerator
{
    use HasEnumerator;

    #[Label('Google')]
    case GOOGLE = 'google';

    #[Label('Facebook')]
    case FACEBOOK = 'facebook';

    #[Label('X (Twitter)')]
    case TWITTER = 'twitter';

    #[Label('LinkedIn')]
    case LINKEDIN = 'linkedin';

    #[Label('PayPal')]
    case PAYPAL = 'paypal';
}

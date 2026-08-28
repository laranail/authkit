<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;

/**
 * The social-extraction migration set (UPGRADING.md). Run it against an application's own code:
 *
 *   vendor/bin/rector process app/ \
 *       --config vendor/laranail/authkit/rector-migrate-social.php
 *
 * It applies the mechanical break only -- fifteen classes gained a `Social\` segment when social
 * login moved to laranail/authkit-social.
 *
 * The other break, `laranail.authkit.social.*` -> `laranail.authkit-social.*`, is a change to string
 * literals. Rector has no clean rule for that, and a rule rewriting arbitrary strings would be worse
 * than the find-and-replace UPGRADING.md gives you.
 */
$old = 'Simtabi\Laranail\AuthKit\\';
$new = 'Simtabi\Laranail\AuthKit\Social\\';

$moved = [
    'Actions\SocialRedirectAction',
    'Actions\SocialCallbackAction',
    'Actions\CreateSocialAccountAction',
    'Actions\ResolveSocialIdentity',
    'Contracts\SocialRedirectActionInterface',
    'Contracts\SocialCallbackActionInterface',
    'Contracts\CreateSocialAccountActionInterface',
    'Contracts\ResolveSocialIdentityInterface',
    'Enums\SocialProvider',
    'Models\Social',
    'Support\SocialRedirectResult',
    'Services\PayPalSocialProvider',
    'Http\Controllers\AbstractSocialRedirectController',
    'Http\Controllers\AbstractSocialCallbackController',
    'Database\Factories\SocialFactory',
];

$renames = [];
foreach ($moved as $class) {
    $renames[$old.$class] = $new.$class;
}

return RectorConfig::configure()
    // Without this the rename lands as a fully-qualified name inline and leaves the old `use`
    // sitting above it -- correct, but not what anyone wants to read in their own diff.
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withConfiguredRule(RenameClassRector::class, $renames);

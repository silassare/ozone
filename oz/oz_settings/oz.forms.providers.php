<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

// RouteResumableFormProvider is the built-in fallback used by RouteFormResumeInterceptor
// when a route has no explicit provider class. It is not registered here as a standalone
// provider because it requires the real route's RouteInfo to function correctly.
//
// Register custom providers in your app's settings/oz.forms.providers.php:
//
//   return [
//       MyProvider::PROVIDER_NAME => MyProvider::class,
//   ];
return [];

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

// Trusted reverse proxies: IP address or CIDR range => true (trusted) or false (never
// trusted, overriding any matching range).
//
// Forwarding headers (`Forwarded`, `X-Forwarded-For`, `X-Forwarded-Host`) are only read
// when the TCP peer is trusted here; the client IP is then the right-most untrusted hop.
// List every proxy in front of the app (load balancer, CDN), e.g. all Cloudflare ranges
// when behind Cloudflare. Leave empty when clients connect directly.
// To read the client IP from a provider header instead (e.g. `CF-Connecting-IP`), also set
// `OZ_CLIENT_IP_HEADER` in `oz.request`.
return [
	// '10.0.0.0/8'      => true,
	// '173.245.48.0/20' => true,
	// '10.0.0.66'       => false,
];

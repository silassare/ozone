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

namespace __PLH_NAMESPACE__;

use __PLH_NAMESPACE__\Db\Base\Candidates\AccountLocalClientCandidates;
use Gobl\ORM\Candidates\CandidatesProviderInterface;

/**
 * The candidates of an account's `local_client`: the client the request names in its query string,
 * read through the context the ORM generator typed as OZone's.
 */
final class LocalClientCandidates implements CandidatesProviderInterface
{
	public function __invoke(AccountLocalClientCandidates $candidates): void
	{
		$name = $candidates->context()
			->getRequest()
			->getQueryParam('client_name');

		$candidates->selector()
			->where(['client_name', 'eq', \is_string($name) ? $name : '']);
	}
}

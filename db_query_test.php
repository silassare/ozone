<?php

use OZONE\OZ\Db\OZUsersFilters;

if (0) {
	$uq = new OZUsersFilters();
	if (!0) {
		$qb = $uq->whereCc2Is('bj')
				 ->and(
					 function (OZUsersFilters $sub) {
						 return $sub->whereIdIsGte(10)
									->and()
									->whereIdIsLte(20)
									->or()
									->whereValidIsTrue();
					 }
				 )
				 ->getTableQuery()
				 ->select(100);
	} else {
		$qb = $uq->where([
			'cc2',
			'eq',
			'bj',
			'and',
			[['id', 'gte', 10, 'and', 'id', 'lte', 20], 'or', 'valid', 'is_true'],
		])
				 ->getTableQuery()
				 ->select(100);
	}
	oz_logger([
		'query'  => $qb->getSqlQuery(),
		'values' => $qb->getBoundValues(),
		'types'  => $qb->getBoundValuesTypes(),
	]);
}
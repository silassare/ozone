<?php

use OZONE\OZ\Db\OZUsersQuery;

if (0) {
	$uq = new OZUsersQuery();
	if (!0) {
		$qb = $uq->whereCc2Is('bj')
				 ->and(
					 function (OZUsersQuery $sub) {
						 return $sub->whereIdIsGte(10)
									->and()
									->whereIdIsLte(20)
									->or()
									->whereValidIsTrue();
					 }
				 )
				 ->select(100);
	} else {
		$qb = $uq->where([
			'cc2',
			'eq',
			'bj',
			'and',
			[['id', 'gte', 10, 'and', 'id', 'lte', 20], 'or', 'valid', 'is_true'],
		])
				 ->select(100);
	}
	oz_logger([
		'query'  => $qb->getSqlQuery(),
		'values' => $qb->getBoundValues(),
		'types'  => $qb->getBoundValuesTypes(),
	]);
}
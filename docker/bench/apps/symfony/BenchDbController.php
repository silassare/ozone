<?php

namespace App\Controller;

use App\Entity\Country;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

// `make benchmark-http`: one row read by its primary key through the ORM, as OZone and Laravel read
// theirs. A controller of its own, so the ping and JSON routes are resolved as they were before.
#[AsController]
final class BenchDbController
{
	#[Route('/db')]
	public function db(EntityManagerInterface $em): JsonResponse
	{
		$country = $em->find(Country::class, 'BJ') ?? throw new NotFoundHttpException();

		return new JsonResponse([
			'cc2'          => $country->cc2,
			'name'         => $country->name,
			'calling_code' => $country->callingCode,
		]);
	}
}

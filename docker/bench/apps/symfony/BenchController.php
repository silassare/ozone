<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// `make benchmark-http`.
final class BenchController
{
	#[Route('/ping')]
	public function ping(): Response
	{
		return new Response('pong', 200, ['Content-Type' => 'text/plain']);
	}

	#[Route('/json')]
	public function json(): JsonResponse
	{
		return new JsonResponse(['hello' => 'world']);
	}
}

<?php

namespace App\Controller;

use App\Serializer\ApiSerializer;
use App\Service\ItemService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/items')]
class ItemController extends AbstractController
{
    public function __construct(
        private readonly ItemService $itemService,
        private readonly ValidatorInterface $validator,
        private readonly RateLimiterFactory $writeApiLimiter,
    ) {
    }

    #[Route('', name: 'items_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $skip = (int) $request->query->get('skip', 0);
        $limit = (int) $request->query->get('limit', 10);

        $violations = $this->validator->validate([
            'skip' => $skip,
            'limit' => $limit,
            'min_price' => $request->query->get('min_price'),
            'max_price' => $request->query->get('max_price'),
            'category_id' => $request->query->get('category_id'),
            'name_contains' => $request->query->get('name_contains'),
        ], new Assert\Collection([
            'skip' => [new Assert\GreaterThanOrEqual(0)],
            'limit' => [new Assert\Range(min: 1, max: 100)],
            'min_price' => new Assert\Optional([new Assert\Positive()]),
            'max_price' => new Assert\Optional([new Assert\Positive()]),
            'category_id' => new Assert\Optional([new Assert\Positive()]),
            'name_contains' => new Assert\Optional([new Assert\Length(min: 1, max: 255)]),
        ]));

        if (count($violations) > 0) {
            return new JsonResponse(['detail' => (string) $violations->get(0)->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $filters = [];
        if ($request->query->has('min_price')) {
            $filters['min_price'] = $request->query->get('min_price');
        }
        if ($request->query->has('max_price')) {
            $filters['max_price'] = $request->query->get('max_price');
        }
        if ($request->query->has('category_id')) {
            $filters['category_id'] = (int) $request->query->get('category_id');
        }
        if ($request->query->has('name_contains')) {
            $filters['name_contains'] = $request->query->get('name_contains');
        }

        [$rows, $total] = $this->itemService->listItems($skip, $limit, $filters);

        return new JsonResponse([
            'items' => array_map(static fn ($item) => ApiSerializer::item($item), $rows),
            'total' => $total,
            'skip' => $skip,
            'limit' => $limit,
        ]);
    }

    #[Route('/stats/summary', name: 'items_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        return new JsonResponse($this->itemService->getStats());
    }

    #[Route('/{itemId}', name: 'items_show', methods: ['GET'], requirements: ['itemId' => '\d+'])]
    public function show(int $itemId): JsonResponse
    {
        return new JsonResponse(ApiSerializer::item($this->itemService->getById($itemId)));
    }

    #[Route('', name: 'items_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $this->consumeRateLimit($request);

        $payload = $this->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $violations = $this->validator->validate($payload, new Assert\Collection([
            'name' => [new Assert\NotBlank(), new Assert\Length(min: 1, max: 255)],
            'description' => new Assert\Optional([new Assert\Type('string')]),
            'price' => [new Assert\NotBlank(), new Assert\Positive()],
            'category_id' => new Assert\Optional([new Assert\Positive()]),
        ]));

        if (count($violations) > 0) {
            return new JsonResponse(['detail' => (string) $violations->get(0)->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $item = $this->itemService->create(
            (string) $payload['name'],
            $payload['description'] ?? null,
            number_format((float) $payload['price'], 2, '.', ''),
            isset($payload['category_id']) ? (int) $payload['category_id'] : null,
        );

        return new JsonResponse(ApiSerializer::item($item), Response::HTTP_CREATED);
    }

    #[Route('/{itemId}', name: 'items_update', methods: ['PATCH'], requirements: ['itemId' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function update(int $itemId, Request $request): JsonResponse
    {
        $this->consumeRateLimit($request);

        $payload = $this->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if (array_key_exists('price', $payload) && $payload['price'] !== null) {
            $payload['price'] = number_format((float) $payload['price'], 2, '.', '');
        }

        $item = $this->itemService->update($itemId, $payload);

        return new JsonResponse(ApiSerializer::item($item));
    }

    #[Route('/{itemId}', name: 'items_delete', methods: ['DELETE'], requirements: ['itemId' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $itemId, Request $request): Response
    {
        $this->consumeRateLimit($request);
        $this->itemService->delete($itemId);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array<string, mixed>|JsonResponse */
    private function decodeJson(Request $request): array|JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['detail' => 'Invalid JSON body'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $payload;
    }

    private function consumeRateLimit(Request $request): void
    {
        if ($this->getParameter('kernel.environment') === 'test') {
            return;
        }

        $limiter = $this->writeApiLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            throw $this->createAccessDeniedException('Rate limit exceeded');
        }
    }
}

<?php

namespace App\Controller;

use App\Serializer\ApiSerializer;
use App\Service\CategoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/categories')]
class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly ValidatorInterface $validator,
        private readonly RateLimiterFactory $writeApiLimiter,
    ) {
    }

    #[Route('', name: 'categories_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $skip = max(0, (int) $request->query->get('skip', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 10)));

        [$rows, $total] = $this->categoryService->listCategories($skip, $limit);

        return new JsonResponse([
            'items' => array_map([ApiSerializer::class, 'category'], $rows),
            'total' => $total,
            'skip' => $skip,
            'limit' => $limit,
        ]);
    }

    #[Route('/{categoryId}', name: 'categories_show', methods: ['GET'], requirements: ['categoryId' => '\d+'])]
    public function show(int $categoryId): JsonResponse
    {
        return new JsonResponse(ApiSerializer::category($this->categoryService->getById($categoryId)));
    }

    #[Route('', name: 'categories_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $this->consumeRateLimit($request);

        $payload = $this->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $violations = $this->validator->validate($payload, new Assert\Collection([
            'name' => [new Assert\NotBlank(), new Assert\Length(min: 1, max: 100)],
            'description' => new Assert\Optional([new Assert\Type('string')]),
        ]));

        if (count($violations) > 0) {
            return $this->validationError((string) $violations->get(0)->getMessage());
        }

        $category = $this->categoryService->create(
            (string) $payload['name'],
            $payload['description'] ?? null,
        );

        return new JsonResponse(ApiSerializer::category($category), Response::HTTP_CREATED);
    }

    #[Route('/{categoryId}', name: 'categories_update', methods: ['PATCH'], requirements: ['categoryId' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function update(int $categoryId, Request $request): JsonResponse
    {
        $this->consumeRateLimit($request);

        $payload = $this->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $category = $this->categoryService->update($categoryId, $payload);

        return new JsonResponse(ApiSerializer::category($category));
    }

    #[Route('/{categoryId}', name: 'categories_delete', methods: ['DELETE'], requirements: ['categoryId' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $categoryId, Request $request): Response
    {
        $this->consumeRateLimit($request);
        $this->categoryService->delete($categoryId);

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

    private function validationError(string $message): JsonResponse
    {
        return new JsonResponse(['detail' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
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

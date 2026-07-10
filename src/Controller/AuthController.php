<?php

namespace App\Controller;

use App\Serializer\ApiSerializer;
use App\Service\UserService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Entity\User;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ValidatorInterface $validator,
        private readonly RateLimiterFactory $authApiLimiter,
    ) {
    }

    #[Route('/auth/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $this->consumeRateLimit($request);

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->validationError(['detail' => 'Invalid JSON body']);
        }

        $violations = $this->validator->validate($payload, new Assert\Collection([
            'email' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(min: 5, max: 255)],
            'password' => [new Assert\NotBlank(), new Assert\Length(min: 8, max: 128)],
        ]));

        if (count($violations) > 0) {
            return $this->validationError(['detail' => (string) $violations->get(0)->getMessage()]);
        }

        $user = $this->userService->create((string) $payload['email'], (string) $payload['password']);

        return new JsonResponse(ApiSerializer::user($user), Response::HTTP_CREATED);
    }

    #[Route('/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $this->consumeRateLimit($request);

        $email = (string) $request->request->get('username', '');
        $password = (string) $request->request->get('password', '');

        if ($email === '' || $password === '') {
            $payload = json_decode($request->getContent(), true);
            if (is_array($payload)) {
                $email = (string) ($payload['username'] ?? $payload['email'] ?? '');
                $password = (string) ($payload['password'] ?? '');
            }
        }

        $user = $this->userService->authenticate($email, $password);
        if ($user === null) {
            return new JsonResponse(
                ['detail' => 'Incorrect email or password'],
                Response::HTTP_UNAUTHORIZED,
                ['WWW-Authenticate' => 'Bearer'],
            );
        }

        return new JsonResponse([
            'access_token' => $this->jwtManager->create($user),
            'token_type' => 'bearer',
        ]);
    }

    #[Route('/auth/me', name: 'auth_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return new JsonResponse(['detail' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse(ApiSerializer::user($user));
    }

    private function consumeRateLimit(Request $request): void
    {
        if ($this->getParameter('kernel.environment') === 'test') {
            return;
        }

        $limiter = $this->authApiLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            throw $this->createAccessDeniedException('Rate limit exceeded');
        }
    }

    /** @param array<string, mixed> $detail */
    private function validationError(array $detail): JsonResponse
    {
        return new JsonResponse($detail, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

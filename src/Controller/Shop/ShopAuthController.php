<?php

namespace App\Controller\Shop;

use App\Exception\UserEmailExistsException;
use App\Form\RegistrationFormType;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/shop')]
class ShopAuthController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    #[Route('/login', name: 'shop_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('shop_home');
        }

        return $this->render('shop/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'shop_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Symfony security component handles logout.');
    }

    #[Route('/register', name: 'shop_register', methods: ['GET', 'POST'])]
    public function register(Request $request, Security $security): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('shop_home');
        }

        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $user = $this->userService->create($data['email'], $data['plainPassword']);
            } catch (UserEmailExistsException) {
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('An account with this email already exists.'));

                return $this->render('shop/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }

            $security->login($user);
            $this->addFlash('success', 'Account created. You are logged in.');

            return $this->redirectToRoute('shop_home');
        }

        return $this->render('shop/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}

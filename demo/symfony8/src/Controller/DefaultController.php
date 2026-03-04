<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{
    #[Route(path: '/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('default/index.html.twig', [
            'message' => 'Sentry Bundle Demo - Symfony 8',
        ]);
    }
}

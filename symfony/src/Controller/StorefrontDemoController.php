<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request; 
use Symfony\Component\HttpFoundation\JsonResponse;

final class StorefrontDemoController extends AbstractController
{
    #[Route('/storefront/info', name: 'app_storefront_demo')]
    public function info(): Response
    {
        return $this->render('storefront_demo/index.html.twig', [
            'controller_name' => 'StorefrontDemoController',
        ]);
    }

    #[Route('/storefront/demo', name: 'storefront')]
    public function index(): Response
    {
        return $this->render('storefront_demo/products.html.twig', [
            'shop' => $_ENV['SHOP_DOMAIN'],
            'token' => $_ENV['STOREFRONT_TOKEN'],
            'ver'   => $_ENV['API_VERSION'] ?? '2025-01',
        ]);
    }

    #[Route('/storefront/gql', name: 'storefront_gql', methods: ['POST'])]
    public function gql(Request $request): Response
    {
        $shop = $_ENV['SHOP_DOMAIN'] ?? '';
        $ver  = $_ENV['API_VERSION'] ?? '2025-01';

        $private = $_ENV['STOREFRONT_PRIVATE_TOKEN'] ?? null; // shpat_...
        $public  = $_ENV['STOREFRONT_TOKEN'] ?? null;

        if (!$shop || (!$private && !$public)) {
            return new JsonResponse(['errors' => [['message' => 'Missing SHOP_DOMAIN or Storefront token']]], 500);
        }

        $payload = json_decode($request->getContent() ?: '{}', true) ?: [];
        $query = $payload['query'] ?? null;
        $variables = $payload['variables'] ?? [];
        if (!$query) {
            return new JsonResponse(['errors' => [['message' => 'Missing "query"']]], 400);
        }

        $url = sprintf('https://%s/api/%s/graphql.json', $shop, $ver);

        $headers = ['Content-Type' => 'application/json'];
        if ($private) {
            $headers['Shopify-Storefront-Private-Token'] = $private;
        } else {
            $headers['X-Shopify-Storefront-Access-Token'] = $public;
        }

        try {
            $client = \Symfony\Component\HttpClient\HttpClient::create();
            $resp = $client->request('POST', $url, [
                'headers' => $headers,
                'json' => ['query' => $query, 'variables' => $variables],
                'timeout' => 20,
            ]);
            $status = $resp->getStatusCode();
            $json   = json_decode($resp->getContent(false), true);
            return new JsonResponse($json, $status);
        } catch (\Throwable $e) {
            return new JsonResponse(['errors' => [['message' => $e->getMessage()]]], 502);
        }
    }
}

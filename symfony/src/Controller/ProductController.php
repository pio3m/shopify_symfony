<?php

namespace App\Controller;

use App\Repository\ShopRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
	public function __construct(
		private ShopRepository $shopRepository
	) {}

	#[Route('/product', name: 'shopify_product', methods: ['GET'])]
	public function productJson(Request $request): Response
	{
		$shopDomain = $request->query->get('shop');
		if (!$shopDomain || !str_ends_with($shopDomain, '.myshopify.com')) {
			return new Response('Invalid or missing shop parameter', 400);
		}

		$shop = $this->shopRepository->findOneBy(['shopDomain' => $shopDomain]);
		if (!$shop || !$shop->getAccessToken()) {
			return new Response('Shop not found or missing access token', 404);
		}

		$accessToken = $shop->getAccessToken();
		$apiUrl = 'https://' . $shopDomain . '/admin/api/2025-01/products.json';
    
        
        $title = "produkt test";
        $price = "100.00";
        $status = 'active'; 

        $payload = [
            'product' => array_filter([
                'title'  => $title,
                'status' => $status,
                'variants' => $price !== null ? [['price' => $price]] : [['price' => '0.00']],
            ]),
        ];

		$httpClient = \Symfony\Component\HttpClient\HttpClient::create();
		$response = $httpClient->request('POST', $apiUrl, [
			  'headers' => [
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => 20,
		]);

		if ($response->getStatusCode() !== 200) {
            
			return new Response('Failed to fetch products from Shopify', 502);
		}

		$data = $response->getContent();
		return new Response($data, 200, ['Content-Type' => 'application/json']);
	}
}

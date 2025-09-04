<?php

namespace App\Controller;

use App\Entity\Shop;
use App\Repository\ShopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ShopRepository $shops
    ) {}

    #[Route('/auth/install', name: 'shopify_auth_install', methods: ['GET'])]
    public function install(Request $request, SessionInterface $session): Response
    {

        $shop = strtolower(trim((string)$request->query->get('shop', '')));
        if (!$shop || !str_ends_with($shop, '.myshopify.com')) {
            return new Response('Missing or invalid ?shop=*.myshopify.com', 400);
        }

        $apiKey = $_ENV['SHOPIFY_API_KEY'] ?? '';
        $redirectUri = rtrim($_ENV['SHOPIFY_REDIRECT_URI'] ?? '', '/'); // np. https://abcd1234.ngrok.io/auth/callback
        $scopes = $_ENV['SHOPIFY_SCOPES'] ?? 'read_products';  // CSV
        if (!$apiKey || !$redirectUri) {
            return new Response('Missing SHOPIFY_API_KEY / SHOPIFY_REDIRECT_URI', 500);
        }

        // CSRF: losowy state do sesji
        $state = bin2hex(random_bytes(16));
        $session->set('shopify_oauth_state', $state);
        $session->set('shopify_oauth_shop', $shop);

        $authUrl = sprintf(
            'https://%s/admin/oauth/authorize?client_id=%s&scope=%s&redirect_uri=%s&state=%s',
            $shop,
            urlencode($apiKey),
            urlencode($scopes),
            urlencode($redirectUri),
            urlencode($state)
        );

        return new RedirectResponse($authUrl);
    }
    #[Route('/auth/callback', name: 'shopify_auth_callback', methods: ['GET'])]
    public function callback(Request $request, SessionInterface $session): Response
    {
        $apiKey    = $_ENV['SHOPIFY_API_KEY']    ?? '';
        $apiSecret = $_ENV['SHOPIFY_API_SECRET'] ?? '';
        if (!$apiKey || !$apiSecret) {
            return new Response('Missing SHOPIFY_API_KEY / SHOPIFY_API_SECRET', 500);
        }

        // --- 1) Odbierz parametry od Shopify
        $hmac = (string) $request->query->get('hmac', '');
        $code = (string) $request->query->get('code', '');
        $shop = strtolower(trim((string) $request->query->get('shop', '')));
        $state= (string) $request->query->get('state', '');

        if (!$hmac || !$code || !$shop) {
            return new Response('Missing hmac/code/shop', 400);
        }
        if (!str_ends_with($shop, '.myshopify.com')) {
            return new Response('Invalid shop domain', 400);
        }

        // --- 2) Walidacja state (CSRF)
        $expectedState = (string) $session->get('shopify_oauth_state', '');
        $expectedShop  = (string) $session->get('shopify_oauth_shop', '');
        if (!$expectedState || $state !== $expectedState || $shop !== $expectedShop) {
            return new Response('Invalid state or shop mismatch', 400);
        }
        // jednorazowe — czyścimy
        $session->remove('shopify_oauth_state');
        $session->remove('shopify_oauth_shop');

        // --- 3) Weryfikacja HMAC (hex) z wszystkich parametrów poza hmac/signature
        $params = $request->query->all();
        unset($params['hmac'], $params['signature']);
        ksort($params);
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $calculated  = hash_hmac('sha256', $queryString, $apiSecret); // hexdigest
        if (!hash_equals($calculated, $hmac)) {
            return new Response('HMAC verification failed', 401);
        }

        // --- 4) Wymiana code -> access_token
        $tokenUrl = sprintf('https://%s/admin/oauth/access_token', $shop);
        try {
            $client = HttpClient::create();
            $resp = $client->request('POST', $tokenUrl, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'client_id'     => $apiKey,
                    'client_secret' => $apiSecret,
                    'code'          => $code,
                ],
                'timeout' => 15,
            ]);
        } catch (\Throwable $e) {
            return new Response('Token exchange error: '.$e->getMessage(), 502);
        }

        if ($resp->getStatusCode() !== 200) {
            return new Response('Token exchange failed: HTTP '.$resp->getStatusCode().' '.$resp->getContent(false), 502);
        }

        $data = $resp->toArray(false);
        $accessToken = $data['access_token'] ?? null;
        if (!$accessToken) {
            return new Response('No access_token in response', 502);
        }

        // --- 5) Zapis / aktualizacja wpisu sklepu w DB
        $entity = $this->shops->findOneBy(['shopDomain' => $shop]) ?? (new Shop($shop));
        $entity->setAccessToken($accessToken);

        $this->em->persist($entity);
        $this->em->flush();

        return $this->render('auth/success.html.twig', [
            'shop'   => $shop,
            'scopes' => $data['scope'] ?? '',
        ]);
    }

    #[Route('/', name: 'shopify_app_launch', methods: ['GET'])]
    public function launch(Request $request, SessionInterface $session): Response
    {
        $shop      = strtolower(trim((string) $request->query->get('shop', '')));
        return $this->redirectToRoute('shopify_auth_install', ['shop' => $shop]);
    }
}

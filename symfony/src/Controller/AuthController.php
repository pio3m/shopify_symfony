<?php

namespace App\Controller;

use App\Entity\Shop;
use App\Repository\ShopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
// use Symfony\Component\HttpClient\HttpClient; //
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

   // To jest endpoint /auth/install
    #[Route('/', name: 'shopify_auth_install', methods: ['GET'])]
    public function install(Request $request, SessionInterface $session): Response
    {
        /* TODO: Krok 1 — pobierz i zweryfikuj ?shop
           - weź $shop z query (?shop=*.myshopify.com)
           - zwróć 400, jeśli brak/niewłaściwy format
        */
         $shop = $request->query->get('shop');
         if (!$shop || !str_ends_with($shop, '.myshopify.com')) {
             return new Response('Invalid or missing shop parameter', 400);
         }

        /* TODO: Krok 2 — wczytaj z ENV: SHOPIFY_API_KEY, SHOPIFY_REDIRECT_URI, SHOPIFY_SCOPES
           - jeśli brakuje któregoś → 500 z komunikatem
        */
         $apiKey = $_ENV['SHOPIFY_API_KEY'] ?? null;
         $redirectUri = $_ENV['SHOPIFY_REDIRECT_URI'] ?? null;
         $scopes = $_ENV['SHOPIFY_SCOPES'] ?? null;

         if (!$apiKey || !$redirectUri || !$scopes) {
             return new Response('Missing Shopify configuration in environment variables', 500);
         }
         

        /* TODO: Krok 3 — wygeneruj losowy $state (csrf)
           - zapisz w sesji: shopify_oauth_state, shopify_oauth_shop
           - (opcjonalnie) zapisz też w cookie (SameSite=None; Secure) jako fallback
        */
         $state = bin2hex(random_bytes(16));

         $session->set('shopify_oauth_state', $state);
         $session->set('shopify_oauth_shop', $shop);


        /* TODO: Krok 4 — zbuduj $authUrl:
           https://{shop}/admin/oauth/authorize
             ?client_id={API_KEY}
             &scope={CSV_SCOPES}
             &redirect_uri={REDIRECT_URI}
             &state={STATE}
        */
         $authUrl = 'https://' . $shop . '/admin/oauth/authorize' .
                    '?client_id=' . urlencode($apiKey) .
                    '&scope=' . urlencode($scopes) .
                    '&redirect_uri=' . urlencode($redirectUri) .
                    '&state=' . urlencode($state);
                    
        /* TODO: Krok 5 — RedirectResponse($authUrl) */
         return new RedirectResponse(authUrl);
    }

   

    #[Route('/auth/callback', name: 'shopify_auth_callback', methods: ['GET'])]
    public function callback(Request $request, SessionInterface $session): Response
    {
        /* TODO: Krok 1 — wczytaj z ENV: SHOPIFY_API_KEY, SHOPIFY_API_SECRET
           - jeśli brakuje → 500
        */
         $apiKey = $_ENV['SHOPIFY_API_KEY'] ?? null;
         $apiSecret = $_ENV['SHOPIFY_API_SECRET'] ?? null;
         if (!$apiKey || !$apiSecret) {
             return new Response('Missing Shopify configuration in environment variables', 500);
         }

        /* TODO: Krok 2 — pobierz z query: hmac, code, shop, state
           - waliduj obecność i format shop (*.myshopify.com)
        */
         $hmac = $request->query->get('hmac');
         $code = $request->query->get('code');
         $shop = $request->query->get('shop');
         $state = $request->query->get('state');

         if (!$hmac || !$code || !$shop || !$state || !str_ends_with($shop, '.myshopify.com')) {
             return new Response('Invalid or missing parameters from Shopify', 400);
         }

        /* TODO: Krok 3 — zweryfikuj state (CSRF)
           - porównaj z tym z sesji (lub z cookie fallback, jeśli tak zrobisz)
           - niezgodny → 400
           - wyczyść z sesji po użyciu
        */
         $sessionState = $session->get('shopify_oauth_state');
         $sessionShop = $session->get('shopify_oauth_shop');

         if (!$sessionState || !$sessionShop || $state !== $sessionState || $shop !== $sessionShop) {
             return new Response('Invalid state parameter (possible CSRF attack)', 400);
         }

         // Clear state and shop from session after validation
         $session->remove('shopify_oauth_state');
         $session->remove('shopify_oauth_shop');

        /* TODO: Krok 4 — zweryfikuj HMAC (HEX, jak w launch)
           - identyczna procedura: sort, RFC3986, hash_hmac sha256 z API_SECRET
           - niezgodny → 401
        */
         $data = $request->query->all();
         unset($data['hmac'], $data['signature']);

         ksort($data);
         $queryString = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
         $calculatedHmac = hash_hmac('sha256', $queryString, $apiSecret);

         if (!hash_equals($calculatedHmac, $hmac)) {
             return new Response('Invalid HMAC parameter (possible data tampering)', 401);
         }

        /* TODO: Krok 5 — wymień code → access_token
           - endpoint: https://{shop}/admin/oauth/access_token
           - POST JSON: { client_id, client_secret, code }
           - użyj symfony/http-client (HttpClient::create()->request(...))
           - oczekuj HTTP 200 i 'access_token' w JSON
        */
         $httpClient = \Symfony\Component\HttpClient\HttpClient::create();
         $response = $httpClient->request('POST', 'https://' . $shop . '/admin/oauth/access_token', [
             'json' => [
                 'client_id' => $apiKey,
                 'client_secret' => $apiSecret,
                 'code' => $code,
             ],
         ]);

         if ($response->getStatusCode() !== 200) {
             return new Response('Failed to exchange code for access token', 500);
         }

         $responseData = $response->toArray();
         $accessToken = $responseData['access_token'] ?? null;

         if (!$accessToken) {
             return new Response('Access token not found in response', 500);
         }

        /* TODO: Krok 6 — zapisz/aktualizuj rekord w DB
           - znajdź Shop po shopDomain, albo utwórz nowy
           - setAccessToken($accessToken) i flush()
        */
         //   tylko zapis do db TODO dodac repozytorium do srpawdzenia czy sklep juz istnieje
         $shopEntity = new Shop($shop, $accessToken);
         $this->em->persist($shopEntity);
         $this->em->flush();
            


        /* TODO: Krok 7 — (opcjonalnie) pokaż stronę sukcesu
           - render('auth/success.html.twig', ['shop' => $shop, 'scopes' => $data['scope'] ?? '' ])
        */
         return $this->render('auth/success.html.twig', ['shop' => $shop, 'scopes' => $responseData['scope'] ?? '']);

        return new Response('TODO: verify callback & exchange code for token, then persist', 501);
    }
}

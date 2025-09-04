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

    #[Route('/auth/install', name: 'shopify_auth_install', methods: ['GET'])]
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

        /* TODO: Krok 4 — zbuduj $authUrl:
           https://{shop}/admin/oauth/authorize
             ?client_id={API_KEY}
             &scope={CSV_SCOPES}
             &redirect_uri={REDIRECT_URI}
             &state={STATE}
        */

        /* TODO: Krok 5 — RedirectResponse($authUrl) */
        return new Response('Logi ' . $shop, 501);
    }

   
    #[Route('/', name: 'shopify_app_launch', methods: ['GET'])]
    public function launch(Request $request, SessionInterface $session): Response
    {
        /* TODO: Krok 1 — pobierz hmac, host, shop, timestamp z query
           - jeśli brakuje czegokolwiek → 400
           - shop musi kończyć się na ".myshopify.com"
        */

        /* TODO: Krok 2 — anti-replay: sprawdź świeżość timestamp (np. ±300 s)
           - jeśli za stary → 401
        */

        /* TODO: Krok 3 — weryfikacja HMAC (HEX)
           - weź wszystkie paramy query poza 'hmac' i 'signature'
           - ksort($params)
           - $queryString = http_build_query(..., PHP_QUERY_RFC3986)
           - $calc = hash_hmac('sha256', $queryString, $_ENV['SHOPIFY_API_SECRET'])
           - jeśli !hash_equals($calc, $hmac) → 401
        */

        /* TODO: Krok 4 — zapisz w sesji: shopify_host, shopify_shop
           - przyda się np. do App Bridge / powrotu do sklepu
        */

        /* TODO: Krok 5 — sprawdź, czy mamy token w DB dla tego shopa
           - $this->shops->findOneBy(['shopDomain' => $shop])
           - jeśli nie ma lub token pusty → redirect do 'shopify_auth_install'
        */

        /* TODO: Krok 6 — wyrenderuj UI apki (templates/app/index.html.twig)
           - ustaw nagłówek CSP:
             "frame-ancestors https://admin.shopify.com https://{$shop};"
        */

      return $this->render('index.html.twig', ['version' => $_ENV['API_VERSION']]);
      //   return new Response('TODO: verify launch HMAC & render app or redirect to install', 501);
    }

    #[Route('/auth/callback', name: 'shopify_auth_callback', methods: ['GET'])]
    public function callback(Request $request, SessionInterface $session): Response
    {
        /* TODO: Krok 1 — wczytaj z ENV: SHOPIFY_API_KEY, SHOPIFY_API_SECRET
           - jeśli brakuje → 500
        */

        /* TODO: Krok 2 — pobierz z query: hmac, code, shop, state
           - waliduj obecność i format shop (*.myshopify.com)
        */

        /* TODO: Krok 3 — zweryfikuj state (CSRF)
           - porównaj z tym z sesji (lub z cookie fallback, jeśli tak zrobisz)
           - niezgodny → 400
           - wyczyść z sesji po użyciu
        */

        /* TODO: Krok 4 — zweryfikuj HMAC (HEX, jak w launch)
           - identyczna procedura: sort, RFC3986, hash_hmac sha256 z API_SECRET
           - niezgodny → 401
        */

        /* TODO: Krok 5 — wymień code → access_token
           - endpoint: https://{shop}/admin/oauth/access_token
           - POST JSON: { client_id, client_secret, code }
           - użyj symfony/http-client (HttpClient::create()->request(...))
           - oczekuj HTTP 200 i 'access_token' w JSON
        */

        /* TODO: Krok 6 — zapisz/aktualizuj rekord w DB
           - znajdź Shop po shopDomain, albo utwórz nowy
           - setAccessToken($accessToken) i flush()
        */

        /* TODO: Krok 7 — (opcjonalnie) pokaż stronę sukcesu
           - render('auth/success.html.twig', ['shop' => $shop, 'scopes' => $data['scope'] ?? '' ])
        */

        return new Response('TODO: verify callback & exchange code for token, then persist', 501);
    }
}

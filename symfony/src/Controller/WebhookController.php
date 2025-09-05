<?php

namespace App\Controller;

use App\Entity\WebhookLog;
use App\Repository\ShopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/webhooks')]
final class WebhookController extends AbstractController
{
    public function __construct(
        private ShopRepository $shops,
        private EntityManagerInterface $em
    ) {}

    private function api(string $shop, string $path): string
    {
        $ver = $_ENV['API_VERSION'] ?? '2025-01';
        return sprintf('https://%s/admin/api/%s%s', $shop, $ver, $path);
    }

    private function getToken(string $shop): ?string
    {
        return $this->shops->findOneBy(['shopDomain' => $shop])?->getAccessToken();
    }

    /**
     * Rejestracja webhooka products/create (HTTPS JSON) na Twój ngrok URL.
     * Wywołaj GETem: /webhooks/register?shop=teamp-demo.myshopify.com
     */
    #[Route('/register', name: 'webhooks_register', methods: ['GET'])]
    public function register(Request $req): Response
    {
        $shop  = strtolower($req->query->get('shop', $_ENV['SHOP_DOMAIN'] ?? ''));
        $token = $this->getToken($shop);
        if (!$shop || !$token) {
            return new JsonResponse(['error' => 'No shop/token'], 400);
        }

        $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/'); // np. https://2af1a17037dd.ngrok-free.app
        if (!$appUrl) return new JsonResponse(['error' => 'Missing APP_URL'], 500);

        $payload = [
            'webhook' => [
                'topic'   => 'products/create',
                'address' => $appUrl.'/webhooks/products/create',
                'format'  => 'json',
            ]
        ];

        $client = HttpClient::create();
        $resp = $client->request('POST', $this->api($shop, '/webhooks.json'), [
            'headers' => [
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => 20,
        ]);

        return new JsonResponse(json_decode($resp->getContent(false), true), $resp->getStatusCode());
    }

    /**
     * Podgląd zarejestrowanych webhooków w sklepie.
     * GET: /webhooks/list?shop=teamp-demo.myshopify.com
     */
    #[Route('/list', name: 'webhooks_list', methods: ['GET'])]
    public function list(Request $req): Response
    {
        $shop  = strtolower($req->query->get('shop', $_ENV['SHOP_DOMAIN'] ?? ''));
        $token = $this->getToken($shop);
        if (!$shop || !$token) {
            return new JsonResponse(['error' => 'No shop/token'], 400);
        }

        $client = HttpClient::create();
        $resp = $client->request('GET', $this->api($shop, '/webhooks.json'), [
            'headers' => ['X-Shopify-Access-Token' => $token],
            'timeout' => 20,
        ]);

        return new JsonResponse(json_decode($resp->getContent(false), true), $resp->getStatusCode());
    }

    /**
     * Odbiornik webhooka products/create (ważne: surowe body i HMAC base64!)
     * POST: /webhooks/products/create
     */
    #[Route('/products/create', name: 'webhooks_products_create', methods: ['POST'])]
    public function receiveProductsCreate(Request $req): Response
    {
        $secret   = $_ENV['SHOPIFY_API_SECRET'] ?? '';
        if (!$secret) return new Response('Missing SHOPIFY_API_SECRET', 500);

        // Nagłówki od Shopify
        $hmac      = (string) $req->headers->get('X-Shopify-Hmac-Sha256', '');
        $topic     = (string) $req->headers->get('X-Shopify-Topic', 'unknown');
        $shop      = (string) $req->headers->get('X-Shopify-Shop-Domain', 'unknown');
        $webhookId = (string) $req->headers->get('X-Shopify-Webhook-Id', null);

        // RAW BODY – tylko tego użyj do HMAC!
        $body = $req->getContent(); // surowe JSON

        // HMAC dla webhooków jest BASE64 z hash_hmac(..., raw_output=true)
        $calc = base64_encode(hash_hmac('sha256', $body, $secret, true));
        if (!hash_equals($calc, $hmac)) {
            return new Response('Invalid HMAC', 401);
        }

        // Idempotencja: jeśli już logowaliśmy ten webhook_id, zwróć 200
        if ($webhookId) {
            $exists = $this->em->getConnection()->fetchOne(
                'SELECT 1 FROM webhook_logs WHERE webhook_id = :id',
                ['id' => $webhookId]
            );
            if ($exists) {
                return new Response('OK (duplicate)', 200);
            }
        }

        // Zapisz log (szybko, bez ciężkiej pracy)
        $log = new WebhookLog($topic, $shop, $webhookId ?: null, $body);
        $this->em->persist($log);
        $this->em->flush();

        // TODO: tu odpal kolejkę / async job do dalszego przetwarzania

        return new Response('OK', 200);
    }
}

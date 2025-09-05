<?php

namespace App\Controller;

use App\Entity\Review;
use App\Repository\ShopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RevewsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    #[Route('/reviews/', name: 'app_reviews_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        // --- 1) Weryfikacja App Proxy (z querystringu) ---
        $rawQuery = $request->getQueryString() ?? '';
         $secret   = $_ENV['SHOPIFY_API_SECRET'] ?? '';

        if (!$secret) {
            return new Response('Missing SHOPIFY_APP_SECRET', 500);
        }

        $ok = false;

        $params = $_GET;
        $sig = $_GET['signature'];
        $params = array_diff_key($params, array('signature' => '')); // Remove sig from params
        ksort($params); // Sort params lexographically
        $params = str_replace("%2F","/",http_build_query($params));
        $params = str_replace("&","",$params);

        // Compute SHA256 digest
        $computed_hmac = hash_hmac('sha256', $params, $_ENV['SHOPIFY_API_SECRET']);

        // Use sig data to check that the response is from Shopify or not
        if (hash_equals($sig, $computed_hmac)) {
                var_dump("ok!!");
        } else {
            var_dump("not ok!!");
        }

        // --- 2) Identyfikacja klienta z App Proxy ---
        $loggedId = $request->query->get('logged_in_customer_id');
        if (!$loggedId) {
            return new Response('Authentication required', 401);
        }

        // --- 3) Body JSON: productId, rating (1..5), text (opcjonalnie) ---
        $data = json_decode($request->getContent(), true) ?? [];
        $productId = $data['productId'] ?? null;
        $rating    = $data['rating'] ?? $data['value'] ?? null; // dopuszczam "value"
       

        if (!$productId || !is_numeric($productId)) {
            return new Response('Invalid productId', 400);
        }
        if ($rating !== null && (!is_numeric($rating) || $rating < 1 || $rating > 5)) {
            return new Response('Invalid rating', 400);
        }

        // --- 4) Blokada duplikatu (unikat: customer + product) ---

//        $repo = $em->getRepository(Review::class);
//        $existing = $repo->findOneBy([
//            'loggedInCustomerId' => (string)$loggedId,
//            'productId'          => (string)$productId,
//        ]);
//        if ($existing) {
//            // możesz zwrócić 409 lub potraktować jako sukces idempotentny
//            return $this->json(['ok' => true, 'already' => true]);
//        }

        // --- 5) Zapis do encji Review ---

        $review = new Review();

        $review->setProductId((string)$productId);
        $review->setRating($rating !== null ? (int)$rating : 1); // dla "serduszka" ustawiamy 1
       
        $review->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($review);
        $this->em->flush();

        return $this->json(['ok' => true]);
    }
}

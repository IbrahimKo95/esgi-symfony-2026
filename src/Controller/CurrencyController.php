<?php

namespace App\Controller;

use App\Service\ExchangeRateService;
use App\Service\ExchangeRateUnavailableException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Convertisseur de devises, utilise le service ExchangeRateService pour convertir
 * automatiquement les montants en devise etrangere vers la devise de reference de l'utilisateur.
 */
#[Route('/currency-converter')]
#[IsGranted('ROLE_USER')]
class CurrencyController extends AbstractController
{
    private const CURRENCIES = ['EUR', 'USD', 'GBP', 'CHF', 'JPY'];

    #[Route('', name: 'currency_converter', methods: ['GET'])]
    public function index(Request $request, ExchangeRateService $exchangeRateService): Response
    {
        $amount = $request->query->get('amount');
        $from = $request->query->get('from', 'EUR');
        $to = $request->query->get('to', 'USD');
        $result = null;
        $error = null;

        if (null !== $amount && is_numeric($amount)) {
            try {
                $result = $exchangeRateService->convert((float) $amount, $from, $to);
            } catch (ExchangeRateUnavailableException $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('currency/index.html.twig', [
            'currencies' => self::CURRENCIES,
            'amount' => $amount,
            'from' => $from,
            'to' => $to,
            'result' => $result,
            'error' => $error,
        ]);
    }
}

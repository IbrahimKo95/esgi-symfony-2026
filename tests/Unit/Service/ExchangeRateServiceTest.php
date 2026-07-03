<?php

namespace App\Tests\Unit\Service;

use App\Service\ExchangeRateService;
use App\Service\ExchangeRateUnavailableException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ExchangeRateServiceTest extends TestCase
{
    public function testGetRateReturnsOneForSameCurrency(): void
    {
        $service = new ExchangeRateService(new MockHttpClient());

        self::assertSame(1.0, $service->getRate('EUR', 'EUR'));
    }

    public function testGetRateReturnsRateFromApiResponse(): void
    {
        $mockClient = new MockHttpClient(new MockResponse(json_encode([
            'base' => 'USD',
            'rates' => ['EUR' => 0.87],
        ])));

        $service = new ExchangeRateService($mockClient);

        self::assertSame(0.87, $service->getRate('USD', 'EUR'));
    }

    public function testConvertAppliesRateToAmount(): void
    {
        $mockClient = new MockHttpClient(new MockResponse(json_encode([
            'base' => 'USD',
            'rates' => ['EUR' => 0.5],
        ])));

        $service = new ExchangeRateService($mockClient);

        self::assertSame(50.0, $service->convert(100, 'USD', 'EUR'));
    }

    public function testGetRateThrowsWhenCurrencyMissingFromResponse(): void
    {
        $mockClient = new MockHttpClient(new MockResponse(json_encode([
            'base' => 'USD',
            'rates' => [],
        ])));

        $service = new ExchangeRateService($mockClient);

        $this->expectException(ExchangeRateUnavailableException::class);
        $service->getRate('USD', 'EUR');
    }
}

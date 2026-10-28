<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Proves that the application boots and answers a request.
 *
 * M1-05 replaces this test with the access tests for the overview.
 */
final class SmokeTest extends WebTestCase
{
    public function testFrontPageAnswers(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }
}

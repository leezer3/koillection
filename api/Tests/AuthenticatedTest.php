<?php

namespace Api\Tests;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\Client;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;

abstract class AuthenticatedTest extends ApiTestCase
{
    use RefreshDatabaseTrait;

    private ?string $token = null;
    protected ?User $user = null;
    protected ?User $otherUser = null;
    protected ?EntityManagerInterface $em = null;
    protected ?IriConverterInterface $iriConverter = null;

    public function setUp(): void
    {
        self::bootKernel();

        $this->em = $this->getContainer()->get('doctrine')->getManager();
        $this->iriConverter = $this->getContainer()->get('api_platform.iri_converter');

        $this->user = $this->em->getRepository(User::class)->findOneBy([
            'username' => 'User',
        ]);

        $this->otherUser = $this->em->getRepository(User::class)->findOneBy([
            'username' => 'Admin',
        ]);
    }

    protected function createClientWithCredentials($token = null): Client
    {
        $token = $token ?: $this->getToken();

        return static::createClient([], ['headers' => ['Host' => 'localhost', 'Authorization' => 'Bearer '.$token]]);
    }

    protected function getToken($body = []): string
    {
        if ($this->token) {
            return $this->token;
        }

        $response = static::createClient()->request('POST', '/api/authentication_token', ['json' => $body ?: [
            'username' => 'User',
            'password' => 'password',
        ]]);

        $data = json_decode($response->getContent(), true);
        $this->token = $data['token'];

        return $this->token;
    }
}

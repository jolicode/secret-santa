<?php

namespace Joli\SlackSecretSanta\tests;

use Joli\SlackSecretSanta\Kernel;
use Joli\SlackSecretSanta\SecretSanta;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SantaControllerTest extends KernelTestCase
{
    /**
     * {@inheritdoc}
     */
    protected static function getKernelClass()
    {
        return Kernel::class;
    }

    /**
     * {@inheritdoc}
     */
    protected static function createClient(array $options = [], array $server = [])
    {
        static::bootKernel($options);

        $client = new Client(static::$kernel);
        $client->setServerParameters($server);

        return $client;
    }

    public function test_homepage_works()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());

        $this->assertGreaterThan(
            0,
            $crawler->filter('html:contains("Merry Christmas!")')->count()
        );
    }

    public function test_run_page_redirects_to_auth_page()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/run');
        $response = $client->getResponse();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/auth', $response->getTargetUrl());
    }

    public function test_auth_page_redirects_to_slack()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/auth');
        $response = $client->getResponse();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertContains('https://slack.com/oauth', $response->getTargetUrl());
    }

    public function test_finish_page_returns_404_without_hash()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/finish');
        $response = $client->getResponse();

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_finish_page_works_with_invalid_hash()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/finish/13456');
        $response = $client->getResponse();

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_finish_page_works_with_valid_hash_for_successful_secret_santa()
    {
        $secretSanta = new SecretSanta('azerty', [
            'toto1' => 'toto2',
            'toto2' => 'toto3',
        ], null, null);
        $secretSanta->markAssociationAsProceeded('toto1');
        $secretSanta->markAssociationAsProceeded('toto2');

        $client = static::createClient();
        $this->prepareSession($client, 'secret-santa-azerty', $secretSanta);

        $crawler = $client->request('GET', '/finish/azerty');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertGreaterThan(
            0,
            $crawler->filter('html:contains("Well done! All messages were sent")')->count()
        );
    }

    public function test_finish_page_works_with_valid_hash_for_failed_secret_santa()
    {
        $secretSanta = new SecretSanta('azerty', [
            'toto1' => 'toto2',
            'toto2' => 'toto3',
        ], null, null);
        $secretSanta->addError('Knock knock. Who\'s there? A santa error!');

        $client = static::createClient();
        $this->prepareSession($client, 'secret-santa-azerty', $secretSanta);

        $crawler = $client->request('GET', '/finish/azerty');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertGreaterThan(
            0,
            $crawler->filter("html:contains('A technical error occurred when sending messages to users')")->count()
        );
        $this->assertGreaterThan(
            0,
            $crawler->filter('html:contains("Knock knock. Who\'s there? A santa error!")')->count()
        );
        $this->assertGreaterThan(
            0,
            $crawler->filter('html:contains("@toto1 must offer a gift to xxxxx")')->count()
        );
    }

    public function test_spoil_works_with_valid_code()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/spoil');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());

        $form = $crawler->selectButton('Decode!')->form();
        $form['code']->setValue('v1@eyJ0b3RvMSI6InRvdG8yIiwidG90bzIiOiJ0b3RvMyIsInRvdG8zIjoidG90bzEifQ==');

        $crawler = $client->submit($form);
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains('Here is the secret repartition', $response->getContent());
        $this->assertContains('<strong>@toto1</strong> must offer a gift to <strong>@toto2</strong>', $response->getContent());
        $this->assertContains('<strong>@toto2</strong> must offer a gift to <strong>@toto3</strong>', $response->getContent());
        $this->assertContains('<strong>@toto3</strong> must offer a gift to <strong>@toto1</strong>', $response->getContent());
    }

    public function test_spoil_works_with_invalid_code()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/spoil');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());

        $form = $crawler->selectButton('Decode!')->form();
        $form['code']->setValue('v1@yolo');

        $crawler = $client->submit($form);
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains('Content could not be decoded', $response->getContent());
    }

    /**
     * @param Client $client
     * @param string $key
     * @param string $value
     */
    private function prepareSession(Client $client, $key, $value)
    {
        $session = self::$kernel->getContainer()->get('session');
        $session->start();
        $session->set($key, $value);
        $session->save();
    }
}

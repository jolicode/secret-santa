<?php

namespace Joli\SlackSecretSanta\tests;

use Joli\SlackSecretSanta\Result;
use Joli\SlackSecretSanta\SantaKernel;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;

class SantaControllerTest extends KernelTestCase
{
    /**
     * {@inheritdoc}
     */
    protected static function getKernelClass()
    {
        return SantaKernel::class;
    }

    /**
     * {@inheritdoc}
     */
    protected static function createClient(array $options = array(), array $server = array())
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

    public function test_finish_page_works_with_valid_hash_for_success_result()
    {
        $result = new Result('azerty', [], [], null);

        $client = static::createClient();
        $this->prepareSession($client, 'result-azerty', $result);

        $crawler = $client->request('GET', '/finish/azerty');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertGreaterThan(
            0,
            $crawler->filter('html:contains("Well done! All messages were sent")')->count()
        );
    }

    public function test_finish_page_works_with_valid_hash_for_failed_result()
    {
        $result = new Result('azerty', [
            'toto1' => 'toto2',
            'toto2' => 'toto3',
        ], [
            'toto1' => 'toto2',
            'toto2' => 'toto3',
        ], 'Error message');

        $client = static::createClient();
        $this->prepareSession($client, 'result-azerty', $result);

        $crawler = $client->request('GET', '/finish/azerty');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertGreaterThan(
            0,
            $crawler->filter("html:contains('A technical error occurred when sending messages to users')")->count()
        );
        $this->assertGreaterThan(
            0,
            $crawler->filter('html:contains("@toto1 must offer a gift to @toto2")')->count()
        );
    }

    public function test_summary_works_with_invalid_hash()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/summary/13456');
        $response = $client->getResponse();

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_summary_works_with_valid_hash()
    {
        $result = new Result('yolo', [
            'toto1' => 'toto2',
            'toto2' => 'toto3',
            'toto3' => 'toto1',
        ], [], null);

        $client = static::createClient();
        $this->prepareSession($client, 'result-yolo', $result);

        $crawler = $client->request('GET', '/summary/yolo');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains('Spoiler alert', $response->getContent());
        $this->assertContains('@toto1 must offer a gift to @toto2', $response->getContent());
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

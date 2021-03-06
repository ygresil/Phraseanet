<?php

require_once __DIR__ . '/../../../../PhraseanetWebTestCaseAbstract.class.inc';

class ControllerInstallerTest extends \PhraseanetWebTestCaseAbstract
{
    /**
     * As controllers use WebTestCase, it requires a client
     */
    protected $client;

    /**
     * The application loader
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/FakeSetupApplication.inc';
        
        $app['debug'] = true;
        unset($app['exception_handler']);
        
        return $app;
    }

    public function setUp()
    {
        parent::setUp();
        $this->client = $this->createClient();
    }

    /**
     * Default route test
     */
    public function testRouteSlash()
    {
        $this->client->request('GET', '/');

        $response = $this->client->getResponse();
        /* @var $response \Symfony\Component\HttpFoundation\Response */

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/setup/installer/', $response->headers->get('location'));
    }

    public function testRouteInstaller()
    {
        $this->client->request('GET', '/installer/');

        $response = $this->client->getResponse();
        /* @var $response \Symfony\Component\HttpFoundation\Response */

        $this->assertEquals(302, $response->getStatusCode(), "test that response is a redirection " . $this->client->getResponse()->getContent());
        $this->assertEquals('/setup/installer/step2/', $response->headers->get('location'));
    }

    public function testRouteInstallerStep2()
    {
        $this->client->request('GET', '/installer/step2/');

        $response = $this->client->getResponse();
        /* @var $response \Symfony\Component\HttpFoundation\Response */

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->isOk());
    }
}

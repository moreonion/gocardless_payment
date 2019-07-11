<?php

namespace Drupal\gocardless_payment;

use Upal\DrupalUnitTestCase;

/**
 * Test the REST-client wrapper.
 */
class ApiClientTest extends DrupalUnitTestCase {

  /**
   * Create a stub client.
   */
  protected function clientStub() {
    $obj = $this->getMockBuilder(ApiClient::class)
      ->setMethods(['sendRequest'])
      ->setConstructorArgs(['endpoint', 'token'])
      ->getMock();
    $class = \get_class($obj);
    return $class::fromConfig([
      'testmode' => 1,
      'token' => 'test token',
    ]);
  }

  public function testSuccessfulRequest() {
    $client = $this->clientStub();
    $client->expects($this->once())
      ->method('sendRequest')
      ->with('https://api-sandbox.gocardless.com//something', [
        'method' => 'POST',
        'headers' => [
          'Authorization' => 'Bearer test token',
          'GoCardless-Version' => '2015-07-06',
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
        ],
        'data' => '{"foo":"bar"}',
      ])
      ->willReturn((object) ['data' => '{"data":"test"}']);
    $data = $client->post('something', [], ['foo' => 'bar']);
    $this->assertEqual(['data' => 'test'], $data);
  }

}

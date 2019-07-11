<?php

namespace Drupal\gocardless_payment;

use Drupal\gocardless_payment\Errors\ApiError;
use Drupal\little_helpers\Rest\Client;
use Drupal\little_helpers\Rest\HttpError;

/**
 * Client for the gocardless REST-API.
 */
class ApiClient extends Client {

  const API_VERSION = '2015-07-06';
  const LIVE_ENDPOINT = 'https://api.gocardless.com/';
  const SANDBOX_ENDPOINT = 'https://api-sandbox.gocardless.com/';

  protected $token;

  /**
   * Create a new instance based on config usually stored in controller_data.
   */
  public static function fromConfig(array $config) {
    $endpoint = $config['testmode'] ? static::SANDBOX_ENDPOINT : static::LIVE_ENDPOINT;
    return new static($endpoint, $config['token']);
  }

  /**
   * Create a new instance by passing endpoint and API token.
   *
   * @param string $endpoint
   *   URL of the API-endpoint including the trailing slash.
   * @param string $token
   *   API-token used for authentication.
   */
  public function __construct($endpoint, $token) {
    $this->token = $token;
    parent::__construct($endpoint);
  }

  /**
   * Add authorization and version headers to the request.
   */
  protected function send($path, array $query = [], $data = NULL, array $options = []) {
    $options['headers']['Authorization'] = "Bearer {$this->token}";
    $options['headers']['GoCardless-Version'] = static::API_VERSION;
    try {
      return parent::send($path, $query, $data, $options);
    }
    catch (HttpError $e) {
      if ($error = ApiError::fromHttpError($e)) {
        watchdog_exception('gocardless_payment', $e);
        throw $error;
      }
      throw $e;
    }
  }

}

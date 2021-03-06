<?php

namespace Drupal\gocardless_payment\Errors;

use Drupal\little_helpers\Rest\HttpError;

class ApiError extends \RuntimeException {

  protected $error;

  const TYPE_MAP = [
    'gocardless' => Internal::class,
    'invalid_api_usage' => InvalidApiUsage::class,
    'invalid_state' => InvalidState::class,
    'validation_failed' => ValidationFailed::class,
  ];

  /**
   * Create a new error instance from a HttpError.
   *
   * @param \Drupal\little_helpers\Rest\HttpError $e
   *   A HttpError thrown because of a non 2xx HTTP response.
   */
  public static function fromHttpError(HttpError $e) {
    if (($data = drupal_json_decode($e->result->data)) && !empty($data['error']['type'])) {
      $error = $data['error'];
      $types = static::TYPE_MAP;
      if (isset($types[$error['type']])) {
        return new $types[$error['type']]($error, $e);
      }
    }
  }

  /**
   * Create a new API error instance by passing the error array.
   */
  public function __construct(array $error, HttpError $e) {
    parent::__construct($error['message'], $error['code'], $e);
    $this->error = $error;
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\stomp;

use Webmozart\Assert\Assert;

/**
 * The configuration data object.
 */
final class Configuration {

  /**
   * Constructs a new instance.
   *
   * @param string $clientId
   *   The client id.
   * @param string $brokers
   *   The brokers.
   * @param string $destination
   *   The queue destination.
   * @param string|null $user
   *   The username.
   * @param string|null $pass
   *   The password.
   * @param array $heartbeat
   *   The heartbeat configuration.
   */
  public function __construct(
    public readonly string $clientId,
    public readonly string $brokers,
    public readonly string $destination = '/queue/default',
    public readonly ?string $user = NULL,
    public readonly ?string $pass = NULL,
    public readonly array $heartbeat = [
      'send' => 3000,
      'readTimeout' => ['microseconds' => 1500000],
    ],
  ) {
    Assert::regex($this->destination, '/^(\/topic\/|\/queue\/)/');

    if ($this->heartbeat) {
      Assert::keyExists($this->heartbeat, 'send');
      Assert::keyExists($this->heartbeat, 'readTimeout');
      Assert::keyExists($this->heartbeat['readTimeout'], 'microseconds');
    }
  }

}

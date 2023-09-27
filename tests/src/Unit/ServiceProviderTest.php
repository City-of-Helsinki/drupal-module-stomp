<?php

declare(strict_types = 1);

namespace Drupal\Tests\stomp\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Site\Settings;
use Drupal\stomp\StompServiceProvider;
use Drupal\Tests\UnitTestCase;

/**
 * Tests service provider.
 */
class ServiceProviderTest extends UnitTestCase {

  /**
   * Tests dynamic service creation with empty settings.
   */
  public function testEmptySettingsRegister() : void {
    $container = new ContainerBuilder();
    $sut = new StompServiceProvider();
    $sut->register($container);
  }

  /**
   * Tests dynamic service registration with proper settings.
   */
  public function testRegister() : void {
    new Settings([
      'stomp' => [
        'first' => [
          'clientId' => 'client1',
          'brokers' => 'tcp://127.0.0.1:12345',
        ],
        'second' => [
          'clientId' => 'client2',
          'brokers' => 'tcp://127.0.0.1:321',
        ],
      ],
    ]);
    $container = new ContainerBuilder();

    $sut = new StompServiceProvider();
    $sut->register($container);

    $services = [
      'stomp.connection.first',
      'stomp.queue.first',
      'queue.stomp.first',
      'stomp.connection.second',
      'stomp.queue.second',
      'queue.stomp.second',
    ];

    foreach ($services as $service) {
      $this->assertTrue($container->has($service));
    }
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\Tests\stomp\Unit;

use Drupal\stomp\Configuration;
use Drupal\Tests\UnitTestCase;

/**
 * Tests Connection data object.
 */
class ConfigurationTest extends UnitTestCase {

  /**
   * Tests DTO props.
   *
   * @param \Drupal\stomp\Configuration $connection
   *   The connection object.
   */
  private function assertProps(Configuration $connection) : void {
    $props = get_object_vars($connection);

    foreach ($props as $prop => $value) {
      $this->assertEquals($value, $connection->{$prop});
    }
  }

  /**
   * Tests that connection DTO can be constructed.
   */
  public function testProperties() : void {
    $this->assertProps(new Configuration('client', 'tcp://127.0.0.1:61023'));
    $this->assertProps(new Configuration(
      'client',
      'tcp://127.0.0.1:1234',
      '/topic/test',
      user: 'user1',
      pass: 'pass1',
      heartbeat: [],
    ));
    $this->assertProps(new Configuration(
      'client',
      'tcp://127.0.0.1:1234',
      '/queue/test',
      user: 'user1',
      pass: 'pass1',
      heartbeat: ['send' => 1, 'readTimeout' => ['microseconds' => 250]],
    ));
  }

  /**
   * Tests the hearbeat setting.
   *
   * @dataProvider heartbeatData
   */
  public function testHeartbeat(array $heartbeat, string $expectedMessage) : void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($expectedMessage);
    new Configuration('client1', '', heartbeat: $heartbeat);
  }

  /**
   * A data provider.
   *
   * @return array[]
   *   The data.
   */
  public function heartbeatData() : array {
    return [
      [
        ['readTimeout' => []],
        'Expected the key "send" to exist.',
      ],
      [
        ['send' => 0],
        'Expected the key "readTimeout" to exist.',
      ],
      [
        ['send' => 0, 'readTimeout' => []],
        'Expected the key "microseconds" to exist.',
      ],
    ];
  }

  /**
   * Tests destination validation.
   */
  public function testInvalidDestination() : void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/does not match the expected pattern./');
    new Configuration('client', 'tcp://127.0.0.1', destination: 'invalidType');
  }

}

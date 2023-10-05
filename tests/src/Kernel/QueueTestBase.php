<?php

declare(strict_types = 1);

namespace Drupal\Tests\stomp\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\stomp\Queue\Queue;

/**
 * A base class for queue tests.
 */
abstract class QueueTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'stomp',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->setSetting('stomp', $this->getStompConfiguration());

    foreach ($this->getQueueConfiguration() as $key => $value) {
      $this->setSetting($key, $value);
    }
    $this->container->get('kernel')->rebuildContainer();
  }

  /**
   * Defines the queue configurations.
   *
   * Should return the queue name and the corresponding STOMP service, for
   * example:
   *
   * @code
   * return [
   *   'queue_service_queue1' => 'queue.stomp.default',
   *   'queue_reliable_service_queue1' => 'queue.stomp.default',
   * ]
   * @endcode
   *
   * The queue service can then be called with the last part of the key.
   * For example, the queue name for 'queue_service_queue1' is 'queue1'.
   *
   * @return array
   *   The queues.
   */
  abstract protected function getQueueConfiguration() : array;

  /**
   * Defines the STOMP settings.
   *
   * Should contain an array of STOMP queues. See README.md
   * for available settings.
   *
   * @code
   * return [
   *  'first' => [
   *    'clientId' => 'test',
   *    'destination' => '/queue/test',
   *  ],
   * @endcode
   *
   * @return array
   *   The STOMP settings.
   */
  abstract protected function getStompConfiguration() : array;

  /**
   * Gets the queue service.
   *
   * @param string $queue
   *   The queue.
   *
   * @return \Drupal\stomp\Queue\Queue
   *   The stomp service.
   */
  protected function getSut(string $queue) : Queue {
    return $this->container->get('queue')->get($queue);
  }

}

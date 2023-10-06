<?php

declare(strict_types = 1);

namespace Drupal\stomp\Queue;

use Psr\Log\LoggerInterface;
use Stomp\Broker\ActiveMq\Mode\DurableSubscription;
use Stomp\Client;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A factory class to construct STOMP queues.
 */
final class QueueFactory {

  /**
   * Constructs a new instance.
   *
   * @param string $destination
   *   The destination.
   * @param \Stomp\Client $client
   *   The stomp client.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param int $readInterval
   *   The default read interval.
   */
  public function __construct(
    private readonly string $destination,
    private readonly Client $client,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly LoggerInterface $logger,
    private readonly int $readInterval,
  ) {
  }

  /**
   * Gets the queue service.
   *
   * @param string $name
   *   The queue name.
   *
   * @return \Drupal\stomp\Queue\Queue
   *   The STOMP queue service.
   */
  public function get(string $name) : Queue {
    $destination = sprintf('%s/%s', $this->destination, $name);

    $durableSubscription = new DurableSubscription(
      $this->client,
      $destination,
      ack: 'client',
      subscriptionId: $destination,
    );

    return new Queue(
      $this->client,
      $durableSubscription,
      $this->eventDispatcher,
      $this->logger,
      $this->readInterval,
    );
  }

}

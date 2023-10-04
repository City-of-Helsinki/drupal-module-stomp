<?php

declare(strict_types = 1);

namespace Drupal\stomp\Queue;

use Drupal\Core\Queue\ReliableQueueInterface;
use Drupal\stomp\Event\MessageEvent;
use Psr\Log\LoggerInterface;
use Stomp\Broker\ActiveMq\Mode\DurableSubscription;
use Stomp\Client;
use Stomp\Exception\StompException;
use Stomp\Transport\Frame;
use Stomp\Transport\Map;
use Stomp\Transport\Message;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A service to interact with STOMP server.
 */
final class Stomp implements ReliableQueueInterface {

  /**
   * The durable subscription service.
   *
   * @var \Stomp\Broker\ActiveMq\Mode\DurableSubscription
   */
  private readonly DurableSubscription $durableSubscription;

  /**
   * Constructs a new instance.
   *
   * @param \Stomp\Client $stompClient
   *   The STOMP client.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param string $destination
   *   The queue name.
   */
  public function __construct(
    private readonly Client $stompClient,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly LoggerInterface $logger,
    private readonly string $destination,
  ) {
    $this->durableSubscription = new DurableSubscription(
      $this->stompClient,
      $this->destination,
      ack: 'client',
      subscriptionId: $this->destination,
    );
  }

  /**
   * Initializes a durable connection to STOMP service.
   *
   * @return \Stomp\Broker\ActiveMq\Mode\DurableSubscription
   *   The Durable subscription.
   *
   * @throws \Stomp\Exception\StompException
   */
  private function connect() : DurableSubscription {
    $this->durableSubscription->activate();
    return $this->durableSubscription;
  }

  /**
   * {@inheritdoc}
   */
  public function createItem($data) : bool {
    /** @var \Drupal\stomp\Event\MessageEvent $event */
    $event = $this->eventDispatcher->dispatch(MessageEvent::create($data));

    try {
      $this->connect();
      return $this->stompClient->send($this->destination, $event->message);
    }
    catch (StompException $e) {
      $this->logger->error('Failed to send item to queue %queue: @message', [
        '%queue' => $this->destination,
        '@message' => $e->getMessage(),
      ]);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function claimItem($lease_time = 3600) : object|false {
    try {
      while (TRUE) {
        if (!$message = $this->connect()->read()) {
          continue;
        }
        // The 'drush queue:run' command expects an object with
        // item_id and data.
        return (object) [
          'item_id' => $message->getMessageId(),
          'message' => $message,
          'data' => $this->decodeMessage($message),
        ];
      }

    }
    catch (StompException $e) {
      $this->logger->error('Failed to read item from %queue: @message', [
        '%queue' => $this->destination,
        '@message' => $e->getMessage(),
      ]);
    }
    return FALSE;
  }

  /**
   * Decodes the given message object.
   *
   * @param mixed $message
   *   The message to decode.
   *
   * @return mixed
   *   The decoded message.
   */
  private function decodeMessage(mixed $message) : mixed {
    if ($message instanceof Map) {
      return $message->getMap();
    }

    if ($message instanceof Message) {
      return $message->getBody();
    }
    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItem($item) : void {
    if (!$item->message instanceof Frame) {
      return;
    }
    try {
      $this->connect()->ack($item->message);
    }
    catch (StompException) {
    }
  }

  /**
   * {@inheritdoc}
   */
  public function releaseItem($item) : bool {
    // STOMP does not support redelivering items. The item is redelivered
    // if it's not ACK'd.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function numberOfItems() : int {
    // Stomp does not provide a way to count the number of items
    // in a queue.
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function createQueue() : void {
    // STOMP queues are created on demand, so the first time an item is created
    // for a queue which does not exist, then it will be created within the
    // destination broker.
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQueue() : void {
    // STOMP does not provide a mechanism to delete queues from the source
    // broker.
  }

}

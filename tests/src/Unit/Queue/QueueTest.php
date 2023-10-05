<?php

declare(strict_types = 1);

namespace Drupal\Tests\stomp\Unit\Queue;

use Drupal\stomp\Queue\Queue;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use Stomp\Broker\ActiveMq\Mode\DurableSubscription;
use Stomp\Client;
use Stomp\Exception\StompException;
use Stomp\States\Meta\Subscription;
use Stomp\Transport\Map;
use Stomp\Transport\Message;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Unit test for queue.
 *
 * @group stomp
 */
class QueueTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Gets the SUT.
   *
   * @param \Stomp\Client $client
   *   The client.
   * @param \Stomp\Broker\ActiveMq\Mode\DurableSubscription $subscription
   *   The subscription.
   * @param \Psr\Log\LoggerInterface|null $logger
   *   The logger.
   *
   * @return \Drupal\stomp\Queue\Queue
   *   The sut.
   */
  private function getSut(
    Client $client,
    DurableSubscription $subscription,
    LoggerInterface $logger = NULL,
  ) : Queue {
    return new Queue(
      $client,
      $subscription,
      new EventDispatcher(),
      $logger ?: $this->prophesize(LoggerInterface::class)->reveal(),
      1000000,
    );
  }

  /**
   * Gets the durable subscription prophecy.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The object prophecy.
   */
  private function getDurableSubscription() : ObjectProphecy {
    $subscription = $this->prophesize(DurableSubscription::class);
    $subscription->getSubscription()->willReturn(
      new Subscription('/queue/test', NULL, 'client', '/queue/test')
    );
    return $subscription;
  }

  /**
   * Make sure write errors are logged.
   */
  public function testCreateItemStompException() : void {
    $client = $this->prophesize(Client::class);
    $subscription = $this->getDurableSubscription();
    $subscription->activate()
      ->shouldBeCalled()
      ->willThrow(new StompException());
    $logger = $this->prophesize(LoggerInterface::class);
    $logger->error('Failed to send item to %queue: @message', Argument::any())
      ->shouldBeCalled();
    $sut = $this->getSut($client->reveal(), $subscription->reveal(), $logger->reveal());
    $this->assertFalse($sut->createItem('123'));
  }

  /**
   * Tests successful createItem() call.
   */
  public function testCreateItem() : void {
    $client = $this->prophesize(Client::class);
    $client->send(Argument::any(), Argument::any())->willReturn(TRUE);
    $subscription = $this->getDurableSubscription();
    $subscription->activate()->shouldBeCalled();

    $sut = $this->getSut($client->reveal(), $subscription->reveal());
    $this->assertTrue($sut->createItem('123'));
  }

  /**
   * Make sure read errors are logged.
   */
  public function testClaimItemStompException() : void {
    $client = $this->prophesize(Client::class);
    $client->send(Argument::any(), Argument::any())->willReturn(TRUE);
    $subscription = $this->getDurableSubscription();
    $subscription->activate()->shouldBeCalled();
    $subscription->read()->willThrow(
      new StompException()
    );
    $logger = $this->prophesize(LoggerInterface::class);
    $logger->error('Failed to read item from %queue: @message', Argument::any())
      ->shouldBeCalled();
    $sut = $this->getSut($client->reveal(), $subscription->reveal(), $logger->reveal());
    $this->assertFalse($sut->claimItem());
  }

  /**
   * Tests unsuccessful read attempts.
   */
  public function testClaimItemSleep() : void {
    $client = $this->prophesize(Client::class);
    $client->send(Argument::any(), Argument::any())->willReturn(TRUE);
    $subscription = $this->getDurableSubscription();
    $subscription->activate()->shouldBeCalled();

    // Simulate five unsuccessful read attempts and make sure we sleep
    // between each read cycle.
    $subscription->read()->willReturn(
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      new Message('123', ['message-id' => 1]),
    );

    $start = floor(microtime(TRUE) * 1000);
    $sut = $this->getSut($client->reveal(), $subscription->reveal());

    $message = $sut->claimItem();
    $this->assertEquals($message->item_id, 1);
    $end = floor(microtime(TRUE) * 1000);

    // Make sure claimItem() slept for at least 500 milliseconds.
    $this->assertTrue(($end - $start) >= 5);
  }

  /**
   * Tests message decoding.
   *
   * @dataProvider messageDecodeData
   */
  public function testMessageDecoding(mixed $expectedMessage, mixed $messageObject) : void {
    $client = $this->prophesize(Client::class);
    $client->send(Argument::any(), Argument::any())->willReturn(TRUE);
    $subscription = $this->getDurableSubscription();
    $subscription->activate()->shouldBeCalled();

    $subscription->read()->willReturn($messageObject);
    $sut = $this->getSut($client->reveal(), $subscription->reveal());

    $message = $sut->claimItem();
    $this->assertEquals($expectedMessage, $message->data);
  }

  /**
   * A data provider.
   *
   * @return array[]
   *   The data.
   */
  public function messageDecodeData() : array {
    return [
      [
        ['value' => 1],
        new Map(['value' => 1], ['message-id' => 1]),
      ],
      [
        'string',
        new Message('string', ['message-id' => 1]),
      ],
    ];
  }

}

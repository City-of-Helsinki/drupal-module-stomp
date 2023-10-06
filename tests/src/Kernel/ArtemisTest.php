<?php

declare(strict_types = 1);

namespace Drupal\Tests\stomp\Kernel;

/**
 * Tests queue with artemis.
 *
 * @group stomp
 */
class ArtemisTest extends QueueTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getStompConfiguration(): array {
    return [
      'first' => [
        'clientId' => 'test',
        'destination' => '/queue/first',
        'login' => 'artemis',
        'passcode' => 'artemis',
        'brokers' => 'tcp://artemis:61613',
        'timeout' => ['read' => 500],
        'heartbeat' => [
          'send' => 1000,
          'receive' => 0,
          'observers' => [
            [
              'class' => '\Stomp\Network\Observer\HeartbeatEmitter',
            ],
          ],
        ],
      ],
      'second' => [
        'clientId' => 'test',
        'destination' => '/queue/second',
        'login' => 'artemis',
        'passcode' => 'artemis',
        'brokers' => 'tcp://artemis:61613',
        'timeout' => ['read' => 500],
      ],
      'third' => [
        'clientId' => 'test',
        'destination' => '/queue/third',
        'login' => 'artemis',
        'passcode' => 'artemis',
        'brokers' => 'tcp://artemis:61613',
        'timeout' => ['read' => 500],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueueConfiguration(): array {
    return [
      'queue_service_queue1' => 'queue.stomp.first',
      'queue_service_queue2' => 'queue.stomp.second',
      'queue_default' => 'queue.stomp.third',
    ];
  }

  /**
   * Make sure a message is not lost when not ACK'd.
   *
   * @dataProvider queueData
   */
  public function testQueueNoAck(string $expectedQueue, string $queueName) : void {
    $sut = $this->getSut($queueName);
    // Stomp queues are created on demand and has no mechanism to delete
    // queues, so neither method should do anything.
    $sut->createQueue();
    $sut->deleteQueue();
    $data = 'test ' . $queueName;
    $this->assertTrue($sut->createItem('test ' . $queueName));
    // Stomp provides no way to count the number of items. Make sure it
    // returns zero items.
    $this->assertEquals(0, $sut->numberOfItems());

    $message = $sut->claimItem();
    $headers = $message->message->getHeaders();
    $this->assertEquals('false', $headers['redelivered']);
    $this->assertEquals($data, $message->message->getBody());
    $this->assertTrue($sut->releaseItem($message));
  }

  /**
   * Make sure messages are re-delivered when not ACK'd.
   *
   * @dataProvider queueData
   */
  public function testQueueAck(string $expectedQueue, string $queueName) : void {
    $sut = $this->getSut($queueName);
    $message = $sut->claimItem();
    $headers = $message->message->getHeaders();

    $this->assertEquals('true', $headers['redelivered']);
    $this->assertEquals($expectedQueue, $headers['destination']);
    $this->assertEquals($expectedQueue, $headers['subscription']);
    $this->assertEquals('test ' . $queueName, $message->message->getBody());
    $sut->deleteItem($message);
  }

  /**
   * The queue data.
   *
   * @return array[]
   *   The data.
   */
  public function queueData() : array {
    return [
      ['/queue/first/queue1', 'queue1'],
      ['/queue/second/queue2', 'queue2'],
      ['/queue/third/non_specific_queue', 'non_specific_queue'],
    ];
  }

}

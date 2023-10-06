# STOMP

![CI](https://github.com/City-of-Helsinki/drupal-module-stomp/workflows/CI/badge.svg) [![codecov](https://codecov.io/gh/City-of-Helsinki/drupal-module-stomp/graph/badge.svg?token=0B2TNXYU14)](https://codecov.io/gh/City-of-Helsinki/drupal-module-stomp)

This module integrates Drupal with an external STOMP or AMQP message queue, such as ActiveMQ.

## Configuration

You must configure STOMP queues as part of the `$settings` global variable. For example:

```php
# settings.php

$settings['stomp']['default'] = [
  'clientId' => 'artemis',
  'brokers' => 'tcp://artemis:61613',
  // Destination defaults to '/queue/default'.
  'destination' => '/queue/default',
];
```

Then configure STOMP as the queuing system for the queues you want STOMP to maintain, either as the default queue service, default reliable queue service, or specifically for each queue.

If you want to set STOMP as the default queue manager, then add the following to your settings:

```php
$settings['queue_default'] = 'queue.stomp.default';
```

Alternatively, you can also set for each queue to use STOMP using one of these formats:

```php
$settings['queue_service_{queue_name}'] = 'queue.stomp.default';
// Not supported by Drush queue:run at the moment. The service is always called with reliable=FALSE
// argument.
$settings['queue_reliable_service_{queue_name}'] = 'queue.stomp.default';
```

## Customization

### Providing login credentials

```php
$settings['stomp']['default']['login'] = 'username';
$settings['stomp']['default']['passcode'] = 'password';
```

### Timeout

```php
$settings['stomp']['default']['timeout'] = [
  'read' => 1500, // Milliseconds.
  'write' => 1500, // Milliseconds.
];
```

### Heartbeat

Heartbeat is disabled by default. The [stomp-php/stomp-php](https://github.com/stomp-php/stomp-php) library supports client and server side heartbeat.

#### Client side heartbeat

Please note that the items *MUST* be processed faster than the heartbeat defined here.

```php
$settings['stomp']['default']['heartbeat'] = [
  // Signals the server that we're going to send
  // alive signals within an interval of 3000ms.
  'send' => 3000,
  'observers' => [
    [
      'class' => 'Stomp\Network\Observer\HeartbeatEmitter',
    ],
  ],
];
// We must assure that we'll send data within less
// than 3000ms so our read timeout must be lower
// as well (1500ms).
$settings['stomp']['default']['timeout'] = [
  'read' => 1500,
];
```

See [stomp-php/stomp-php-examples](https://github.com/stomp-php/stomp-php-examples/blob/support/version-4/src/heartbeats_client.php) for more information.

#### Server side heartbeat

```php
$settings['stomp']['default']['heartbeat'] = [
  'send' => 0,
  // We want the server to send us signals every 3000ms.
  'receive' => 3000,
  'observers' => [
    [
      'class' => 'Stomp\Network\Observer\ServerAliveObserver',
    ],
  ],
];
```

See [stomp-php/stomp-php-examples](https://github.com/stomp-php/stomp-php-examples/blob/support/version-4/src/heartbeats_server.php) for more information.

#### Custom heartbeat

```php
$settings['stomp']['default']['heartbeat'] = [
  'observers' => [
    [
      'class' => 'YourCustomClass',
      'callback' => function (\Stomp\Client $client, \Drupal\stomp\Configuration $configuration, array $settings) : \Stomp\Network\Observer\ConnectionObserver {
         return new YourCustomClass();
      }
    ],
  ],
];
```

### Persist messages

STOMP messages are non-persistent by default. To use persistent messaging, add the following STOMP header to requests: persistent:true.

This can be done by creating an event subscriber that responds to `\Drupal\stomp\Event\MessageEvent::class` events:

```php
class YourEventSubscriber implements \Symfony\Component\EventDispatcher\EventSubscriberInterface {

  /**
   * Event callback.
   *
   * @param \Drupal\stomp\Event\MessageEvent $event
   *   The event.
   */
  public function processEvent(\Drupal\stomp\Event\MessageEvent $event): void {
    $event->message->addHeaders([
      'persistent' => 'true',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    return [
      \Drupal\stomp\Event\MessageEvent::class => ['processEvent'],
    ];
  }
}
```

See https://www.drupal.org/docs/develop/creating-modules/subscribe-to-and-dispatch-events for more information about events.

## Running tests

Tests can be run just like any other tests. For example:

```bash
vendor/bin/phpunit modules/contrib/stomp/tests
```

### Artemis

The host, username and password should be `artemis`.

Run Artemis using Docker compose:

```yaml
services:
  artemis:
    image: quay.io/artemiscloud/activemq-artemis-broker
    ports:
      - "8161:8161"
      - "61616:61616"
      - "5672:5672"
    environment:
      AMQ_EXTRA_ARGS: "--nio --user admin --password admin"
```

Make sure Artemis queues are empty before running tests:

```bash
foreach item (/queue/first/queue1 /queue/second/queue2 /queue/third/non_specific_queue)
  docker compose exec artemis broker/bin/artemis queue purge \
  --user artemis \
  --password artemis \
  --name test.$item;
end
```



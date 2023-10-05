<?php

declare(strict_types = 1);

namespace Drupal\stomp;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Site\Settings;
use Drupal\stomp\Queue\Queue;
use Drupal\stomp\Queue\QueueFactory;
use Stomp\Broker\ActiveMq\Mode\DurableSubscription;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Webmozart\Assert\Assert;

/**
 * Dynamically register configured STOMP queue services.
 */
final class StompServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) : void {
    $settings = Settings::get('stomp', []);
    foreach ($settings as $key => $value) {
      Assert::alpha($key);
      // Definition's setArguments method doesn't support array
      // spreading, so we have to construct the configuration object and
      // pass the arguments manually to utilize the default values.
      $configuration = new Configuration(...$value);
      $connectionService = new Definition(Configuration::class, [
        $configuration->clientId,
        $configuration->brokers,
        $configuration->destination,
        $configuration->login,
        $configuration->passcode,
        $configuration->heartbeat,
        $configuration->timeout,
      ]);
      $stompClient = new Definition(StompFactory::class, [
        $connectionService,
      ]);
      $stompClient->setFactory([new Reference('stomp.factory'), 'create']);
      $queue = new Definition(Queue::class, [
        $stompClient,
        new Definition(DurableSubscription::class, [
          $stompClient,
          $configuration->destination,
          NULL,
          'client',
          $configuration->destination,
        ]),
        new Reference('event_dispatcher'),
        new Reference('logger.channel.stomp'),
        new Parameter('stomp.read_interval'),
      ]);
      $queue->setPublic(TRUE);
      $container->setDefinition('stomp.queue.' . $key, $queue);

      $queueFactory = new Definition(QueueFactory::class, [
        new Reference('settings'),
      ]);
      $queueFactory->addMethodCall('setContainer', [new Reference('service_container')])
        ->setPublic(TRUE);
      $container->setDefinition('queue.stomp.' . $key, $queueFactory);
    }

  }

}

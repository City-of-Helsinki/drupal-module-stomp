<?php

declare(strict_types = 1);

namespace Drupal\stomp\Queue;

use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * A factory class to construct STOMP queues.
 */
final class QueueFactory implements ContainerAwareInterface {

  use ContainerAwareTrait;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Site\Settings $settings
   *   The site settings.
   */
  public function __construct(
    private readonly Settings $settings,
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
    // Attempt to figure out what service core's queue factory is calling
    // here, so we can use the corresponding STOMP client.
    $serviceName = $this->settings->get('queue_reliable_service_' . $name);

    if (!$serviceName) {
      $serviceName = $this->settings->get('queue_service_' . $name);
    }

    if (!$serviceName) {
      $serviceName = $this->settings->get('queue_default');
    }
    [,, $key] = explode('.', $serviceName);

    return $this->container->get('stomp.queue.' . $key);
  }

}

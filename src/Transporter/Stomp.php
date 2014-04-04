<?php

/**
 * @file
 * Contains \Devour\Transporter\Stomp.
 */

namespace Devour\Transporter;

use Devour\Common\ConfigurableInterface;
use Devour\Source\SourceInterface;
use Devour\Table\HasTableFactoryInterface;
use Devour\Table\HasTableFactoryTrait;
use Devour\Transporter\TransporterInterface;
use Devour\Util\Configuration;
use FuseSource\Stomp\Stomp as StompClient;

/**
 * Returns STOMP messages.
 */
class Stomp implements TransporterInterface, HasTableFactoryInterface, ConfigurableInterface {

  use HasTableFactoryTrait;

  /**
   * The stomp client.
   *
   * @var \FuseSource\Stomp\Stomp
   */
  protected $client;

  /**
   * The number of rows to return at a time.
   *
   * @var int
   */
  protected $batchSize = 50;

  /**
   * Constructs a Stomp object.
   *
   * @param \FuseSource\Stomp\Stomp $client
   *   The stomp client.
   */
  public function __construct(StompClient $client) {
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public function transport(SourceInterface $source) {
    if (!$this->client->isConnected()) {
      $this->client->connect();
      $this->client->subscribe((string) $source);
    }

    $message = $this->client->readFrame();

    $table = $this->getTableFactory()->create();

    if ($message != NULL) {
      $this->client->ack($message);
    }

    if (!$message) {
      return $table;
    }

    $row = $table->getNewRow();

    $row->set('command', $message->command);

    if (!empty($message->headers)) {
      foreach ($message->headers as $key => $value) {
        $row->set($key, $value);
      }
    }

    $row->set('body', $message->body);

    return $table;
  }

  /**
   * {@inheritdoc}
   */
  public static function fromConfiguration(array $configuration) {
    $defaults = ['username' => NULL, 'password' => NULL];
    $configuration = Configuration::validate($configuration, $defaults, ['broker']);
    $client = new StompClient($configuration['broker']);

    return new static($client);
  }

  /**
   * {@inheritdoc}
   */
  public function setProcessLimit($limit) {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function progress(SourceInterface $source) {
    return 0;
  }

  public function __destruct() {
    $this->client->disconnect();
  }

  /**
   * {@inheritdoc}
   */
  public function runInNewProcess() {
    return TRUE;
  }

}

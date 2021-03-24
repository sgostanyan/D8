<?php

/**
 * @file
 * Contains \Drupal\d8_queue\Form\NodePublisherQueueForm.
 */

namespace Drupal\d8_queue\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class NodePublisherQueueForm
 *
 * @package Drupal\d8_queue\Form
 */
class NodePublisherQueueForm extends FormBase {

  /**
   * @var QueueFactory
   */
  protected $queueFactory;

  /**
   * @var QueueWorkerManagerInterface
   */
  protected $queueManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(QueueFactory $queue, QueueWorkerManagerInterface $queue_manager) {
    $this->queueFactory = $queue;
    $this->queueManager = $queue_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('queue'), $container->get('plugin.manager.queue_worker'));
  }

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'd8_queue_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var QueueInterface $queue */
    $queue = $this->queueFactory->get('node_publisher');

    $form['help'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Submitting this form will process the Manual Queue which contains @number items.', ['@number' => $queue->numberOfItems()]),
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process queue'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var QueueInterface $queue */
    $queue = $this->queueFactory->get('manual_node_publisher');
    /** @var QueueWorkerInterface $queue_worker */
    $queue_worker = $this->queueManager->createInstance('manual_node_publisher');

    while ($item = $queue->claimItem()) {
      try {
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
      }
      catch (SuspendQueueException $e) {
        $queue->releaseItem($item);
        break;
      }
      catch (\Exception $e) {
        watchdog_exception('d8_queue', $e);
      }
    }
  }
}

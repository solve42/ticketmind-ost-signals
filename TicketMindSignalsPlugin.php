<?php

require_once dirname(__FILE__) . '/lib/autoload.php';

require_once(INCLUDE_DIR . 'class.plugin.php');

use TicketMind\Plugin\Signals\osTicket\Client\RestApiClient;
use TicketMind\Plugin\Signals\osTicket\Configuration\ConfigValues;
use TicketMind\Plugin\Signals\osTicket\Configuration\TicketMindSignalsPluginConfig;

/**
 * Entry point class to the plugin.
 *
 * @property array $ht
 *   A cached version of the plugin's configuration taken from the database.
 */
class TicketMindSignalsPlugin extends \Plugin {

  /**
   * {@inheritDoc}
   */
  public $config_class = TicketMindSignalsPluginConfig::class;

  /**
   * The plugin directory path.
   *
   * @var string
   */
  const PLUGIN_DIR = __DIR__;

  function __construct() {
      parent::__construct();
  }

  protected function logInfo($message): void {
      $GLOBALS['ost']->logInfo('TicketMind Plugin', $message);
  }

    protected function logDebug($message): void {
        $GLOBALS['ost']->logDebug('TicketMind Plugin', $message);
    }

    protected function logError($message): void {
        $GLOBALS['ost']->logError('TicketMind Plugin', $message);
    }

  /**
   * {@inheritDoc}
   */
  function bootstrap(): void {
      //error_log('TicketMind OST Signals Plugin has been initialized');
      
      \Signal::connect('ticket.created',[$this, 'onTicketCreated']);
      \Signal::connect('threadentry.created', [$this, 'onThreadEntryCreated']);

      error_log('TicketMind OST Signals Connected!');
  }

  public function onTicketCreated(\Ticket $ticket, &$extra): void {
      $this->logDebug('Signal onTicketCreated');

      if (!ConfigValues::isForwardingEnabled()) {
          $this->logDebug('Forwarding disabled, ticket id ' . $ticket->getThreadId());
          return;
      }

      $extra_data = [
          'ticket_number' => $ticket->getNumber(),
          'thread_id' => $ticket->getThreadId(),
          'source' => $ticket->getSource(),
          'priority_id' => $ticket->getPriorityId(),
          'priority_desc' => $ticket->getPriority()->getDesc(),
          'department_id' => $ticket->getDeptId(),
          'department_name' => $ticket->getDeptName(),
          'helptopic_id' => $ticket->getTopicId(),
          'helptopic_label' => $ticket->getHelpTopic(),
          'subject' => $ticket->cdata->subject,
      ];

      $data = [
          'signal' => 'ticket.created',
          'ticket_id' => $ticket->getId(),
          'created_dt' => $ticket->getCreateDate(),
          'extra' => $extra_data
      ];

      $apiClient = new RestApiClient();
      $success = $apiClient->sendPayload($data);
      if ($success) {
          $this->logDebug(
              sprintf('Signal: ticket.created. Ticket send successfully - Number: %s', $ticket->getNumber())
          );
      }else {
          $this->logError(
              sprintf('Signal: ticket.created send failed - Number: %s', $ticket->getNumber())
          );
      }
  }

  public function onThreadEntryCreated(\ThreadEntry $entry): void {
      $this->logDebug('Signal Called onThreadEntryCreated');

      if (!ConfigValues::isForwardingEnabled()) {
          $this->logDebug('Forwarding disabled, ticket id ' . $entry->getThreadId());
          return;
      }

      $ticket = $entry->getThread()->getObject();

      $extra_data = [
          'id' => $entry->getId(),
          'thread_id' => $entry->getThreadId(),
          'updated_dt' => $entry->getUpdateDate(),
          'source' => $entry->getSource(),
          'type' => $entry->getType(),
          'staff_id' => $entry->getStaffId(),
          'is_auto_reply' => $entry->isAutoReply(),
          'user_id' => $entry->getUserId(),
          'status_id' => $ticket->getStatus()->getId(),
          'status' => $ticket->getStatus()->getName(),
          'is_answered' => $ticket->isAnswered(),
      ];

      if (!ConfigValues::includeContent()) {
          $full_data = $extra_data;
      } else {
          $this->logDebug('Signal: threadentry.created Send with full content. Thread Id: ' . $entry->getThreadId());

          $content = [
              'name' => $entry->getName(),
              'title' => $entry->getTitle(),
              'body' => $entry->getBody(),
          ];

          $full_data = array_merge($extra_data, $content);
      }

      $data = [
          'signal' => 'threadentry.created',
          'ticket_id' => $entry->getThread()->getObjectId(),
          'created_dt' => $entry->getCreateDate(),
          'extra' => $full_data,
      ];

      $apiClient = new RestApiClient();

      $success = $apiClient->sendPayload($data);
      if ($success) {
          $this->logDebug(
              sprintf('Signal: threadentry.created. Ticket send successfully - ID: %s', $entry->getThreadId())
          );
      }else {
          $this->logError(
              sprintf('Signal: threadentry.created send failed - ID: %s', $entry->getThreadId())
          );
      }
  }

  public function updateModel($object, $type) {
      $data = [
          'model_type' => $type,
          'model_class' => get_class($object),
          'model_id' => method_exists($object, 'getId') ? $object->getId() : 'N/A',
          'timestamp' => date('Y-m-d H:i:s')
      ];

      $this->logInfo(
          sprintf('Signal model.updated - Type: %s, Class: %s, ID: %s',
              $type,
              get_class($object),
              $data['model_id']
          )
      );
      
      $this->logInfo(
          'Signal: model.updated',
      );
      
      // TODO: Implement model update logic
  }

  public function deleteModel($object, $type) {
      $data = [
          'model_type' => $type,
          'model_class' => get_class($object),
          'model_id' => method_exists($object, 'getId') ? $object->getId() : 'N/A',
          'timestamp' => date('Y-m-d H:i:s')
      ];
      // Use logInfo for deletions as they're more important
      $this->logInfo(
          sprintf('Signal model.deleted - Type: %s, Class: %s, ID: %s',
              $type,
              get_class($object),
              $data['model_id']
          )
      );
      
      $this->logInfo(
          'Signal: model.deleted',
      );
      
      // TODO: Implement model delete logic
  }

  /**
   * {@inheritDoc}
   */
  public function init() {
    if (\method_exists(parent::class, 'init') === TRUE) {
      parent::init();
    }

    $this->{'ht'}['name'] = $this->{'info'}['name'];
  }

  /**
   * {@inheritDoc}
   */
  public function isMultiInstance() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function enable() {
    if (($has_requirements = parent::enable()) !== TRUE) {
      return $has_requirements;
    }

    return \db_query(\sprintf("UPDATE %s SET `name` = CONCAT('__', `name`) WHERE `id` = %d AND `name` NOT LIKE '\_\_%%'",
      \DbEngine::getCompiler()->quote(\PLUGIN_TABLE),
      \db_input($this->getId())
    ));
  }

}

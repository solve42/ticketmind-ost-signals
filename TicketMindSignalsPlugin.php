<?php

require_once dirname(__FILE__) . '/lib/autoload.php';

require_once(INCLUDE_DIR . 'class.plugin.php');

use TicketMind\Data\Signals\osTicket\Configuration\TicketMindSignalsPluginConfig;
use TicketMind\Data\Signals\osTicket\Configuration\Helper;
use TicketMind\Data\Signals\osTicket\Client\RestApiClient;

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
      error_log('TicketMind OST Signals Plugin CTOR');
  }

  /**
   * Get the global osTicket instance for logging
   *
   * @return osTicket|null
   */
  protected function getLogger() {
      global $ost;
      return $ost;
  }

  protected function logInfo($title, $message) {
      $this->getLogger()->logInfo($title, $message);
  }

  /**
   * {@inheritDoc}
   */
  function bootstrap() {
      //error_log('TicketMind OST Signals Plugin has been initialized');
      
      \Signal::connect('ticket.created',[$this, 'onTicketCreated']);
      \Signal::connect('threadentry.created', [$this, 'onThreadEntryCreated']);
      //\Signal::connect('model.updated', [$this, 'updateModel']);
      //\Signal::connect('model.deleted', [$this, 'deleteModel']);

      error_log('TicketMind OST Signals Connected!');
  }

  public function onTicketCreated(\Ticket $ticket, &$extra) {
      error_log("---onTicketCreated 1 ---");
      if (Helper::isDebugLoggingEnabled()) {
          error_log('TicketMind OST onTicketCreated called');
      }
      error_log("---onTicketCreated 2 ---");

      if (!Helper::isForwardingEnabled()) {
          error_log("---onTicketCreated 3 ---");
          if (Helper::isDebugLoggingEnabled()) {
              error_log('TicketMind: Forwarding disabled, skipping ticket creation');
          }
          return;
      }

      error_log("---onTicketCreated 4 ---");

      $msg = [
          'ticket_number' => $ticket->getNumber(),
          'thread_id' => $ticket->getThreadId(),
          'source' => $ticket->getSource(),
      ];

      $data = [
          'signal' => 'ticket.created',
          'ticket_id' => $ticket->getId(),
          'created_dt' => $ticket->getCreateDate(),
          'extra' => $msg
      ];

      error_log("---onTicketCreated 5 ---");
      $apiClient = new RestApiClient();
      error_log("---onTicketCreated 6 ---");
      $success = $apiClient->sendPayload($data);
      error_log("---onTicketCreated 7 ---");
  }

  public function onThreadEntryCreated(\ThreadEntry $entry) {
      error_log("---onThreadEntryCreated 1 ---");
      if (Helper::isDebugLoggingEnabled()) {
          error_log('TicketMind OST onThreadEntryCreated called');
      }
      error_log("---onThreadEntryCreated 2 ---");

      if (!Helper::isForwardingEnabled()) {
          error_log("---onThreadEntryCreated 3 ---");
          if (Helper::isDebugLoggingEnabled()) {
              error_log('TicketMind: Forwarding disabled, skipping thread entry creation');
          }
          return;
      }

      error_log("---onThreadEntryCreated 4 ---");

      $msg = [
          'id' => $entry->getId(),
          'thread_id' => $entry->getThreadId(),
          'updated_dt' => $entry->getUpdateDate(),
          'source' => $entry->getSource(),
          'type' => $entry->getTypes(),
          'staff_id' => $entry->getStaffId(),
      ];

      $data = [
          'signal' => 'threadentry.created',
          'ticket_id' => $entry->getId(),
          'created_dt' => $entry->getCreateDate(),
          'extra' => $msg,
      ];

      error_log("---onThreadEntryCreated 5 ---");
      $apiClient = new RestApiClient();

      error_log("---onThreadEntryCreated 6 ---");
      $success = $apiClient->sendPayload($data);

      error_log("---onThreadEntryCreated 7 ---");
  }

  public function updateModel($object, $type) {
      $data = [
          'model_type' => $type,
          'model_class' => get_class($object),
          'model_id' => method_exists($object, 'getId') ? $object->getId() : 'N/A',
          'timestamp' => date('Y-m-d H:i:s')
      ];
      
//      // Add model-specific data based on type
//      if ($object instanceof Ticket) {
//          $data['ticket_number'] = $object->getNumber();
//          $data['ticket_status'] = $object->getStatus();
//          $data['ticket_subject'] = $object->getSubject();
//          $data['ticket_dept_id'] = $object->getDeptId();
//      } elseif ($object instanceof Task) {
//          $data['task_number'] = $object->getNumber();
//          $data['task_title'] = $object->getTitle();
//          $data['task_status'] = $object->getStatus();
//      } elseif ($object instanceof User) {
//          $data['user_email'] = $object->getEmail();
//          $data['user_name'] = $object->getName();
//      } elseif ($object instanceof Organization) {
//          $data['org_name'] = $object->getName();
//      }
//
      $this->logInfo(
          'TicketMind Signal: model.updated',
          sprintf('Model updated - Type: %s, Class: %s, ID: %s',
              $type,
              get_class($object),
              $data['model_id']
          )
      );
      
      $this->logInfo(
          'TicketMind Signal Data: model.updated',
          json_encode($data, JSON_PRETTY_PRINT)
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
//
//      // Capture as much data as possible before deletion
//      if ($object instanceof Ticket) {
//          $data['ticket_number'] = $object->getNumber();
//          $data['ticket_subject'] = $object->getSubject();
//          $data['ticket_status'] = $object->getStatus();
//      } elseif ($object instanceof Task) {
//          $data['task_number'] = $object->getNumber();
//          $data['task_title'] = $object->getTitle();
//      } elseif ($object instanceof User) {
//          $data['user_email'] = $object->getEmail();
//          $data['user_name'] = $object->getName();
//      }
//
      // Use logInfo for deletions as they're more important
      $this->logInfo(
          'TicketMind Signal: model.deleted',
          sprintf('Model deleted - Type: %s, Class: %s, ID: %s',
              $type,
              get_class($object),
              $data['model_id']
          )
      );
      
      $this->logInfo(
          'TicketMind Signal Data: model.deleted',
          json_encode($data, JSON_PRETTY_PRINT)
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

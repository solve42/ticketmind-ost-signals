<?php

require_once dirname(__FILE__) . '/lib/autoload.php';

require_once(INCLUDE_DIR . 'class.plugin.php');

use TicketMind\Data\Signals\osTicket\Configuration\TicketMindSignalsPluginConfig;

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

  protected function logDebug($title, $message) {
      $this->getLogger()->logDebug($title, $message);
  }

  protected function logInfo($title, $message) {
      $this->getLogger()->logInfo($title, $message);
  }

  /**
   * {@inheritDoc}
   */
  public function bootstrap() {
      error_log('TicketMind OST Signals Plugin has been initialized');
      
      Signal::connect('ticket.created', array($this, 'onTicketCreated'));
      Signal::connect('threadentry.created', array($this, 'onThreadEntryCreated'));
      Signal::connect('model.updated', array($this, 'updateModel'));
      Signal::connect('model.deleted', array($this, 'deleteModel'));
  }

  public function onTicketCreated($ticket) {
      error_log('TicketMind OST onTicketCreated');
      $data = [
          'created_dt' => $ticket->getCreateDate(),
          'ticket_id' => $ticket->getId(),
          'ticket_number' => $ticket->getNumber(),
          'subject' => $ticket->getSubject(),
          'dept_id' => $ticket->getDeptId(),
          'dept_name' => $ticket->getDept() ? $ticket->getDept()->getName() : 'N/A',
          'status' => $ticket->getStatus(),
          'source' => $ticket->getSource(),
          'user_id' => $ticket->getUserId(),
          'user_email' => $ticket->getEmail(),
          'priority' => $ticket->getPriorityId()
      ];
      
      $this->logDebug(
          'TicketMind Signal: ticket.created',
          sprintf('Ticket #%s created - Subject: %s, Dept: %s, Status: %s, Source: %s',
              $ticket->getNumber(),
              $ticket->getSubject(),
              $data['dept_name'],
              $ticket->getStatus(),
              $ticket->getSource()
          )
      );
      
      // Log full data as JSON for detailed debugging
      $this->logDebug(
          'TicketMind Signal Data: ticket.created',
          json_encode($data, JSON_PRETTY_PRINT)
      );
      
      // TODO: Implement queue forwarding logic
  }

  public function onThreadEntryCreated($entry) {
      $data = [
          'entry_id' => $entry->getId(),
          'thread_id' => $entry->getThreadId(),
          'type' => $entry->getType(),
          'poster' => $entry->getPoster(),
          'user_id' => $entry->getUserId(),
          'staff_id' => $entry->getStaffId(),
          'created' => $entry->getCreateDate(),
          'flags' => $entry->getFlags(),
          'body_preview' => substr(strip_tags($entry->getBody()), 0, 100) . '...'
      ];
      
      // Get parent object info (ticket or task)
      $thread = $entry->getThread();
      if ($thread && $thread->getObject()) {
          $parent = $thread->getObject();
          if ($parent instanceof Ticket) {
              $data['parent_type'] = 'ticket';
              $data['parent_number'] = $parent->getNumber();
          } elseif ($parent instanceof Task) {
              $data['parent_type'] = 'task';
              $data['parent_number'] = $parent->getNumber();
          }
      }
      
      $this->logDebug(
          'TicketMind Signal: threadentry.created',
          sprintf('Thread entry #%d created - Type: %s, Poster: %s, Parent: %s #%s',
              $entry->getId(),
              $entry->getType(),
              $entry->getPoster(),
              isset($data['parent_type']) ? $data['parent_type'] : 'unknown',
              isset($data['parent_number']) ? $data['parent_number'] : 'N/A'
          )
      );
      
      $this->logDebug(
          'TicketMind Signal Data: threadentry.created',
          json_encode($data, JSON_PRETTY_PRINT)
      );
      
      // TODO: Implement thread entry created logic
  }

  public function updateModel($object, $type) {
      $data = [
          'model_type' => $type,
          'model_class' => get_class($object),
          'model_id' => method_exists($object, 'getId') ? $object->getId() : 'N/A',
          'timestamp' => date('Y-m-d H:i:s')
      ];
      
      // Add model-specific data based on type
      if ($object instanceof Ticket) {
          $data['ticket_number'] = $object->getNumber();
          $data['ticket_status'] = $object->getStatus();
          $data['ticket_subject'] = $object->getSubject();
          $data['ticket_dept_id'] = $object->getDeptId();
      } elseif ($object instanceof Task) {
          $data['task_number'] = $object->getNumber();
          $data['task_title'] = $object->getTitle();
          $data['task_status'] = $object->getStatus();
      } elseif ($object instanceof User) {
          $data['user_email'] = $object->getEmail();
          $data['user_name'] = $object->getName();
      } elseif ($object instanceof Organization) {
          $data['org_name'] = $object->getName();
      }
      
      $this->logDebug(
          'TicketMind Signal: model.updated',
          sprintf('Model updated - Type: %s, Class: %s, ID: %s',
              $type,
              get_class($object),
              $data['model_id']
          )
      );
      
      $this->logDebug(
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
      
      // Capture as much data as possible before deletion
      if ($object instanceof Ticket) {
          $data['ticket_number'] = $object->getNumber();
          $data['ticket_subject'] = $object->getSubject();
          $data['ticket_status'] = $object->getStatus();
      } elseif ($object instanceof Task) {
          $data['task_number'] = $object->getNumber();
          $data['task_title'] = $object->getTitle();
      } elseif ($object instanceof User) {
          $data['user_email'] = $object->getEmail();
          $data['user_name'] = $object->getName();
      }
      
      // Use logInfo for deletions as they're more important
      $this->logInfo(
          'TicketMind Signal: model.deleted',
          sprintf('Model deleted - Type: %s, Class: %s, ID: %s',
              $type,
              get_class($object),
              $data['model_id']
          )
      );
      
      $this->logDebug(
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

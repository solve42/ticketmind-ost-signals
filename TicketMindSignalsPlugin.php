<?php
/**
 * TicketMind Support AI Agent osTicket Plugin
 * Copyright (C) 2025  Solve42 GmbH
 * Author: Eugen Massini <info@solve42.de>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 *  SPDX-License-Identifier: GPL-2.0-only
 */
//require_once dirname(__FILE__) . '/lib/autoload.php';

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.thread.php');
require_once(__DIR__ . '/include/Signals/osTicket/Configuration/TicketMindSignalsPluginConfig.php');
require_once(__DIR__ . '/include/Signals/osTicket/Configuration/ConfigValues.php');
require_once(__DIR__ . '/include/Signals/osTicket/Client/RestApiClient.php');
require_once(__DIR__ . '/include/Signals/osTicket/Client/RestApiClientPure.php');

use TicketMind\Plugin\Signals\osTicket\Client\RestApiClient;
use TicketMind\Plugin\Signals\osTicket\Client\RestApiClientPure;
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
  public $config_class = \TicketMind\Plugin\Signals\osTicket\Configuration\TicketMindSignalsPluginConfig::class;

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
      //error_log('TicketMind OST Signals Plugin initialize');
      
      \Signal::connect('ticket.created',[$this, 'onTicketCreated']);
      \Signal::connect('threadentry.created', [$this, 'onThreadEntryCreated']);

      \Signal::connect('ajax.scp', function ($dispatcher) {
          $dispatcher->append(
              url_post('^/ticketmind/rag/(?P<id>\d+)/submit$', [$this, 'ajaxAddToRag'])
          );
      });

      \Signal::connect('ticket.view.more', [$this, 'onTicketViewMoreAddToRag'], 'Ticket');

      error_log('TicketMind OST Signals Connected!');
  }

  private static function createRestApiClient(): RestApiClientPure {
      //return new RestApiClient();
      return new RestApiClientPure();
  }

  private function exportThreadEntries($thread): array {
    $entries = $thread->getEntries();

    $types = ['M', 'R', 'N'];
    if ($types)
        $entries->filter(array('type__in' => $types));

    $entries->order_by('id');

    $rows = array();
    foreach ($entries as $entry) {
        $rows[] = $this->thread2data($entry, ConfigValues::includeContent());
    }

    return $rows;
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

      $apiClient = $this->createRestApiClient();
      $success = $apiClient->sendSignal($data);
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

  private static function thread2data(\ThreadEntry $entry, bool $include_content=true): array {
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

      if ($include_content) {
          $content = [
              'name' => $entry->getName(),
              'title' => $entry->getTitle(),
              'body' => $entry->getBody(),
          ];

          $full_data = array_merge($extra_data, $content);
      } else {
          $full_data = $extra_data;
      }

      $data = [
          'ticket_id' => $entry->getThread()->getObjectId(),
          'created_dt' => $entry->getCreateDate(),
          'extra' => $full_data,
      ];
      return $data;
  }

  private function sendTicket2Rag(\Thread $thread, string $msgSource): bool {
      $this->logDebug(sprintf('Command /addtorag from %s - ID: %s', $msgSource, $thread->getId()));
      $content = $this->exportThreadEntries($thread);

      $apiClient = $this->createRestApiClient();
      $success = $apiClient->sendTicket2Rag($content);
      if ($success) {
          $this->logDebug(
              sprintf('Command /addtorag. Thread content send successfully - ID: %s', $thread->getId())
          );
      }else {
          $this->logError(
              sprintf('Command /addtorag send failed - ID: %s', $thread->getId())
          );
      }
      return $success;
  }

  public function onThreadEntryCreated(\ThreadEntry $entry): void {
      $this->logDebug('Signal Called onThreadEntryCreated');

      if (!ConfigValues::isForwardingEnabled()) {
          $this->logDebug('Forwarding disabled, ticket id ' . $entry->getThreadId());
          return;
      }

      $thread_entry_data = $this->thread2data($entry, ConfigValues::includeContent());
      $data = array_merge($thread_entry_data, ['signal' => 'threadentry.created']);


      if ($data['extra']['type'] == 'N' && str_contains(strtolower($data['extra']['body']), '/addtorag')) {
          $this->sendTicket2Rag($entry->getThread(), "signal threadentry.created");
      } else {
          $apiClient = $this->createRestApiClient();
          $success = $apiClient->sendSignal($data);
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

  public function onTicketViewMoreAddToRag(\Ticket $ticket, &$extra): void {
      $this->logDebug('(onTicketViewMoreAddToRag) Setup Add to Rag Button..');

      global $thisstaff;
      if (!$thisstaff || !$thisstaff->isStaff()) { // is not staff
          $this->logDebug('(onTicketViewMoreAddToRag) Setup Add to Rag Button, stopped since not staff user.');
          return;
      }

      echo <<<JS
        <script>
            function tmCsrfToken() {
              var t = $('input[name="__CSRFToken__"]').first().val();
              if (!t) t = $('input[name="csrf_token"]').first().val();
              return t || '';
            }
        
          $(document).off("click.tmAddToRag").on("click.tmAddToRag", "a.tm-addtorag", function(e){
            e.preventDefault();
            var link = $(this);
            if (link.data("tmBusy")) {
                return;
            }
            link.data("tmBusy", true);
            var id = link.data("ticket-id");
            $.ajax({
                url: "ajax.php/ticketmind/rag/" + id + "/submit",
                type: "POST",
                data: { csrf_token: tmCsrfToken() },
                success: function () { window.location.reload(); },
                error: function (xhr) { console.error(xhr.status, xhr.responseText); },
                complete: function () { link.data("tmBusy", false); }
            });
          });
        </script>
      JS;

      echo sprintf(
          '<li><a href="#" class="tm-addtorag" data-ticket-id="%d"><i class="icon-cogs"></i> %s</a></li>',  $ticket->getId(),
          __('Add to RAG')
      );

      $this->logDebug('(onTicketViewMoreAddToRag) Setup Add to Rag Button DONE.');
  }

  public function ajaxAddToRag($ticketId): void {
    $this->logDebug('ajaxAddToRag start..');

    global $thisstaff;
    if (!$thisstaff || !$thisstaff->isStaff()) {
        $this->logDebug('ajaxAddToRag stopped since not staff user.');
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->logDebug('ajaxAddToRag Exit due to Method Not Allowed');
      Http::response(405, 'Method Not Allowed');
      return;
    }

    if (!($ticket = \Ticket::lookup($ticketId))) {
        $this->logDebug('ajaxAddToRag Exit due to Ticket not found');
        Http::response(404, 'Ticket not found');
    }

    if (!$ticket->checkStaffPerm($thisstaff, \Ticket::PERM_EDIT)) {
      $this->logDebug('ajaxAddToRag Exit due to Permission denied. Agent requires EDIT permission.');
      Http::response(403, 'Permission denied');
    }

    $vars = [
      'note' => '/addtorag',
      'title' => __('Add to RAG'),
      'staffId' => $thisstaff->getId(),
      'poster' => $thisstaff->getName(),
      'activity' => __('Add to RAG'),
    ];

    $errors = [];

    $note = $ticket->postNote($vars, $errors, $thisstaff, /* alert= */ false);
    if (!$note) {
      $err_str = implode('; ', $errors);
      $this->logError('ajaxAddToRag failed to post note. Errors: ' . $err_str);
      Http::response(400, 'Failed to add note. Errors: ' . $err_str);
      return;
    }

    $this->logDebug('ajaxAddToRag note posted successfully!');

    Http::response(201, 'OK');
    return;
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

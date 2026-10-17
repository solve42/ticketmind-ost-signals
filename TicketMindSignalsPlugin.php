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

  /**
   * Check the optional agent visibility restriction before exporting a ticket.
   *
   * No configured agent deliberately preserves the previous behaviour.
   */
  private function canForwardTicket(\Ticket $ticket): bool {
      $helpTopicIds = ConfigValues::getSelectedHelpTopicIds();
      if ($helpTopicIds && !in_array((int) $ticket->getTopicId(), $helpTopicIds, true)) {
          $this->logDebug(sprintf(
              'Forwarding skipped: %s; reason=help topic is not selected.',
              $this->getSendingLogContext($ticket)
          ));
          return false;
      }

      $agentId = ConfigValues::getSelectedAgentId();
      if ($agentId === null) {
          return true;
      }

      $agent = \Staff::lookup($agentId);
      if (!$agent) {
          $this->logError(sprintf(
              'Forwarding skipped: %s; reason=submitting agent was not found.',
              $this->getSendingLogContext($ticket)
          ));
          return false;
      }

      // osTicket grants ticket view access when no action-specific permission
      // is requested. There is no Ticket::PERM_VIEW constant.
      if (!$ticket->checkStaffPerm($agent)) {
          $this->logDebug(sprintf(
              'Forwarding skipped: %s; reason=ticket is not visible to submitting agent.',
              $this->getSendingLogContext($ticket)
          ));
          return false;
      }

      return true;
  }

  /**
   * Use one machine-readable context format for every forwarding outcome.
   * The submitting agent is the optional agent selected for forwarding.
   */
  private function getSendingLogContext(\Ticket $ticket): string {
      $submittingAgentId = ConfigValues::getSelectedAgentId();
      $helpTopicId = $ticket->getTopicId();

      return sprintf(
          'ticket_number=%s; submitting_agent_id=%s; help_topic_id=%s',
          $ticket->getNumber(),
          $submittingAgentId === null ? 'none' : $submittingAgentId,
          is_numeric($helpTopicId) && (int) $helpTopicId > 0 ? $helpTopicId : 'none',
      );
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

      if (!$this->canForwardTicket($ticket)) {
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
      $this->logDebug(sprintf('Signal: ticket.created sending; %s',$this->getSendingLogContext($ticket)));
      $success = $apiClient->sendSignal($data);
      if ($success) {
          $this->logDebug(
              sprintf(
                  'Signal: ticket.created sent successfully; %s',
                  $this->getSendingLogContext($ticket)
              )
          );
      }else {
          $this->logError(
              sprintf(
                  'Signal: ticket.created send failed; %s',
                  $this->getSendingLogContext($ticket)
              )
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
      $ticket = $thread->getObject();
      $context = $ticket instanceof \Ticket
          ? $this->getSendingLogContext($ticket)
          : 'ticket_number=unknown; submitting_agent_id=none; help_topic_id=unknown';
      $this->logDebug(sprintf('Command: add_to_rag started; source=%s; %s', $msgSource, $context));
      $content = $this->exportThreadEntries($thread);

      $apiClient = $this->createRestApiClient();
      $success = $apiClient->sendTicket2Rag($content);
      if ($success) {
          $this->logDebug(
              sprintf('Command: add_to_rag sent successfully; %s', $context)
          );
      }else {
          $this->logError(
              sprintf('Command: add_to_rag send failed; %s', $context)
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

      $ticket = $entry->getThread()->getObject();
      if (!$this->canForwardTicket($ticket)) {
          return;
      }

      $thread_entry_data = $this->thread2data($entry, ConfigValues::includeContent());
      $data = array_merge($thread_entry_data, ['signal' => 'threadentry.created']);


      if ($data['extra']['type'] == 'N' && str_contains(strtolower($data['extra']['body']), '/addtorag')) {
          $this->logDebug(sprintf(
              'AddToRag: Signal: threadentry.created sending; %s',
              $this->getSendingLogContext($ticket)
          ));
          $this->sendTicket2Rag($entry->getThread(), "signal threadentry.created");
      } else {
          $apiClient = $this->createRestApiClient();
          $this->logDebug(sprintf(
              'Signal: threadentry.created sending; %s',
              $this->getSendingLogContext($ticket)
          ));
          $success = $apiClient->sendSignal($data);
          if ($success) {
              $this->logDebug(
                  sprintf(
                      'Signal: threadentry.created sent successfully; %s',
                      $this->getSendingLogContext($ticket)
                  )
              );
          }else {
              $this->logError(
                  sprintf(
                      'Signal: threadentry.created send failed; %s',
                      $this->getSendingLogContext($ticket)
                  )
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

      $this->renderResponseAgentNoteCopyAction($ticket);

      $this->logDebug('(onTicketViewMoreAddToRag) Setup Add to Rag Button DONE.');
  }

  /**
   * Make only internal notes authored by the configured response agent
   * available as reply drafts. Entry IDs come from the server so a similarly
   * named agent or an ordinary internal note can never enable the action.
   */
  private function renderResponseAgentNoteCopyAction(\Ticket $ticket): void {
      $responseAgentId = ConfigValues::getResponseAgentId();
      if ($responseAgentId === null) {
          return;
      }

      $noteIds = array();
      $thread = $ticket->getThread();
      if ($thread) {
          // Do not filter $thread->getEntries(): osTicket reuses that query
          // later to render the entire thread.
          $entries = \ThreadEntry::objects();
          $entries->filter(array(
              'thread_id' => $thread->getId(),
              'type' => 'N',
              // osTicket preserves the original author on edits; the last
              // modifier is stored separately in editor/editor_type.
              'staff_id' => $responseAgentId,
          ));
          foreach ($entries as $entry) {
              $noteIds[] = (int) $entry->getId();
          }
      }

      if (!$noteIds) {
          return;
      }

      $noteIds = json_encode($noteIds, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
      echo <<<JS
<style>
[data-tm-response-note] .thread-body > div:first-child { cursor: copy; }
</style>
<script>
$(function () {
    var tmResponseNoteIds = {$noteIds};
    $.each(tmResponseNoteIds, function (_, entryId) {
        var entry = $('#thread-entry-' + entryId);
        // osTicket keeps this wrapper, but changes its ID and replaces its
        // contents after an edit. Delegate from it to retain author eligibility
        // and always read the current body, including after repeated edits.
        entry.attr('data-tm-response-note', '1')
            .attr('title', __('Double-click to copy this response into the reply editor'))
            .off('dblclick.tmResponseNote')
            .on('dblclick.tmResponseNote', '.thread-body > div:first-child', function () {
                var response = $('#response');
                if (!response.length) {
                    return;
                }

                $('#post-reply-tab').trigger('click');
                // Keep the HTML (paragraphs, lists, links and inline styles)
                // when inserting into osTicket's rich-text editor.
                var html = $(this).html();
                if (response.hasClass('richtext')) {
                    response.redactor('source.setCode', html);
                    response.redactor('editor.focus');
                } else {
                    response.val(this.innerText || $(this).text()).focus();
                }
                response.trigger('change').trigger('input');
            });
    });
});
</script>
JS;
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

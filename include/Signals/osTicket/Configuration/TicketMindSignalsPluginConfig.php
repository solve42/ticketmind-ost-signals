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

namespace TicketMind\Plugin\Signals\osTicket\Configuration;

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.translation.php');
require_once(INCLUDE_DIR . 'class.forms.php');
require_once(INCLUDE_DIR . 'class.staff.php');
require_once(INCLUDE_DIR . 'class.topic.php');
require_once(__DIR__ . '/ExtraBooleanField.php');
require_once(__DIR__ . '/DynamicChoiceFields.php');


class TicketMindSignalsPluginConfig extends \PluginConfig implements \PluginCustomConfig {
    public function getOptions() {
        return array(
            'section' => new \SectionBreakField(array(
                'label' => __('TicketMind Settings'),
            )),
            'ticketmind_api_url' => new \TextboxField(array(
                'label' => __('TicketMind API URL'),
                'hint' => __('The TicketMind API URL'),
                'configuration' => [
                    'size' => 60,
                    'length' => 256,
                ],
            )),
            'api_key' => new \PasswordField(array(
                'label' => __('API Key'),
                'configuration' => array(
                    'size' => 60,
                    'length' => 256,
                ),
                'hint' => __('API key for authentication with the queue service'),
            )),
            'tls_ca_file' => new \TextboxField(array(
                'label' => __('TLS CA / Self-Signed Cert Path'),
                'hint' => __('Optional absolute path to a PEM certificate file to trust for HTTPS connections. Use this for private or self-signed certificates.'),
                'configuration' => [
                    'size' => 60,
                    'length' => 512,
                ],
            )),
            'selected_agent_id' => new AgentChoiceField(array(
                'label' => __('Forward only tickets visible to agent'),
                'default' => '',
                'hint' => __('Optionally restrict forwarding to tickets that this agent is allowed to view. Leave this set to All agents to forward tickets as before.'),
            )),
            'response_agent_id' => new ResponseAgentChoiceField(array(
                'label' => __('Response agent'),
                'default' => '',
                'required' => false,
                'hint' => __('Optional. Select an agent to enable double-clicking their internal notes in an open ticket to copy the content into the reply editor. Select No response agent to disable this action. On initial setup, a single agent whose name contains TicketMind, Ticket Agent, or AI Agent is proposed. You can use the same agent as the forwarding filter agent or choose a different one.'),
            )),
            'selected_help_topic_ids' => new HelpTopicChoiceField(array(
                'label' => __('Forward only selected help topics'),
                'default' => array(),
                'configuration' => array(
                    'multiselect' => true,
                ),
                'hint' => __('Optionally select one or more help topics to forward. Select No help topic to include tickets without a help topic, on its own or alongside specific topics. Leave this empty to forward all tickets regardless of help topic.'),
            )),
            'with_content' => new ExtraBooleanField(array(
                'label' => __('Include Content'),
                'default' => NULL,
                'hint' => __('Should the subject and body of the ticket be included in the message, or only the header?'),
            )),
            'forward_enabled' => new ExtraBooleanField(array(
                'label' => __('Enable Forwarding'),
                'default' => NULL,
                'hint' => __('Enable or disable ticket forwarding to the queue'),
            )),
        );
    }

    function renderConfig()
    {
        $options = [];
        $form = $this->getForm();
        include \TicketMindSignalsPlugin::PLUGIN_DIR . '/templates/configuration-form.tmpl.php';
    }

    /**
     * Suggest an AI agent on initial setup, preserving an explicitly saved
     * empty selection. The suggestion is not stored until the form is saved.
     */
    function getForm($vars = array()) {
        $form = parent::getForm($vars);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $this->get('response_agent_id') === null) {
            $suggestedAgentId = $this->getSuggestedResponseAgentId();
            if ($suggestedAgentId !== null) {
                $field = $form->getField('response_agent_id');
                if ($field instanceof \FormField) {
                    $field->setValue($suggestedAgentId);
                }
            }
        }

        return $form;
    }

    /**
     * Return the single agent whose name looks like a TicketMind/AI agent.
     */
    private function getSuggestedResponseAgentId(): ?int {
        global $cfg;
        if (!$cfg) {
            return null;
        }

        $matches = array();
        foreach (\Staff::getStaffMembers() as $agentId => $agentName) {
            if (preg_match('/ticketmind|ticket\\s+agent|ai\\s+agent/i', (string) $agentName)) {
                $matches[] = (int) $agentId;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    function saveConfig() {
        try {
            $form = $this->getForm();

            if (!($form instanceof \Form) || $form->isValid() !== true) {
                return false;
            }

            $errors = [];
            $values = $form->getClean();

            if (! is_array($values)) {
                return false;
            }
            if (! $this->pre_save($values, $errors)) {
                return false;
            }

            // Merge form errors without overwriting existing keys (like `$a + $b`)
            $formErrors = $form->errors();
            if (is_array($formErrors)) {
                $errors = $errors + $formErrors;
            }

            if (count($errors) !== 0) {
                return false;
            }

            $data = [];
            foreach ($values as $name => $value) {
                $field = $form->getField($name);
                if ($field instanceof \FormField) {
                    try {
                        $data[$name] = $field->to_database($value);
                    } catch (\FieldUnchanged $ex) {
                        unset($data[$name]);
                    }
                }
            }

            return $this->updateAll($data);
        } catch (\Throwable $ex) {
            return false;
        }
    }

    function pre_save(&$config, &$errors) {
        $tls_ca_file_path = $config['tls_ca_file'] ?? '';
        if ($tls_ca_file_path !== '' && !is_file($tls_ca_file_path)) {
            $field = $this->getForm()->getField('tls_ca_file');
            $field->addError(__('Certificate file not found at this path.'));
            $errors['tls_ca_file'] = __('Certificate file not found at this path.');
            return false;
        }

        return true;
    }
}

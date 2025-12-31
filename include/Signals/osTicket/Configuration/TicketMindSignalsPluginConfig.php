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


class TicketMindSignalsPluginConfig extends \PluginConfig implements \PluginCustomConfig {
    public function getOptions() {
        return array(
            'section' => new \SectionBreakField(array(
                'label' => __('TicketMind Settings'),
            )),
            'queue_url' => new \TextboxField(array(
                'label' => __('TicketMind Host'),
                'hint' => __('The TicketMind Host URL'),
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
}
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
 * SPDX-License-Identifier: GPL-2.0-only
 */

namespace TicketMind\Plugin\Signals\osTicket\Configuration;

require_once(__DIR__ . '/ExtraBooleanField.php');

class ConfigValues {
    const TICKETMIND_API_URL = 'ticketmind_api_url';  // Example: https://ai.ticketmind.de/api
    const API_KEY = 'api_key';
    const TLS_CA_FILE = 'tls_ca_file';
    const FORWARD_ENABLED = 'forward_enabled';
    const WITH_CONTENT = 'with_content';
    const SELECTED_AGENT_ID = 'selected_agent_id';
    const RESPONSE_AGENT_ID = 'response_agent_id';
    const SELECTED_HELP_TOPIC_IDS = 'selected_help_topic_ids';

    public static function getTicketMindApiURL(): ?string {
        return rtrim(static::getTextboxValue(static::TICKETMIND_API_URL)?->getClean(), '/');
    }

    public static function getApiKey(): ?string {
        return static::getPasswordValue(static::API_KEY)?->getClean();
    }

    public static function getTlsCaFile(): ?string {
        $value = static::getTextboxValue(static::TLS_CA_FILE)?->getClean();
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value !== '' ? $value : null;
    }

    public static function isForwardingEnabled(): bool {
        return (bool) static::getAsBooleanValue(static::FORWARD_ENABLED)?->getClean();
    }

    public static function includeContent(): bool {
        return (bool) static::getAsBooleanValue(static::WITH_CONTENT)?->getClean();
    }

    /**
     * Return the configured agent, or null when forwarding is unrestricted.
     */
    public static function getSelectedAgentId(): ?int {
        return static::getAgentId(static::SELECTED_AGENT_ID);
    }

    /**
     * Return the agent whose internal notes may be copied into a reply.
     */
    public static function getResponseAgentId(): ?int {
        return static::getAgentId(static::RESPONSE_AGENT_ID);
    }

    private static function getAgentId(string $name): ?int {
        $value = static::getTextboxValue($name)?->getClean();
        if (!is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Return selected help-topic IDs (0 means no topic), or an empty array
     * when unrestricted.
     */
    public static function getSelectedHelpTopicIds(): array {
        $value = static::getChoiceValue(static::SELECTED_HELP_TOPIC_IDS)?->getClean();
        if (!is_array($value)) {
            $value = array($value);
        } elseif (!static::isList($value)) {
            // ChoiceField stores selected values as id => display-name pairs.
            $value = array_keys($value);
        }

        $topicIds = array();
        foreach ($value as $topicId) {
            if ($topicId === 'none') {
                $topicIds[] = 0;
            } elseif (is_numeric($topicId) && (int) $topicId > 0) {
                $topicIds[] = (int) $topicId;
            }
        }

        return array_values(array_unique($topicIds));
    }

    /**
     * @template T of \FormField
     * @param T $field
     * @param string $name
     * @return T
     */
    private static function getFormField(\FormField $field, string $name): \FormField {
        try {
            $plugin = \PluginManager::getInstance(
                sprintf('plugins/%s', basename(\TicketMindSignalsPlugin::PLUGIN_DIR))
            );

            if ($plugin instanceof \Plugin) {
                if (method_exists($plugin, 'getActiveInstances') === TRUE) {
                    $plugin = $plugin->getActiveInstances()->one();
                }

                $config = $plugin->getConfig();

                if ($config instanceof \PluginConfig) {
                    $field->setValue($config->get($name));
                }
            }
        } catch (\Throwable $ex) {
            error_log('TicketMind Config Error: ' . $ex->getMessage());
        }

        return $field;
    }

    private static function getTextboxValue(string $name): \TextboxField {
        return static::getFormField(
            new \TextboxField(),
            $name
        );
    }

    private static function getPasswordValue(string $name): \TextboxField {
        return static::getFormField(
            new \TextboxField(),
            $name
        );
    }

    private static function getChoiceValue(string $name): \ChoiceField {
        return static::getFormField(
            new \ChoiceField(array(
                'configuration' => array('multiselect' => true),
            )),
            $name
        );
    }

    private static function getAsBooleanValue(string $name): ExtraBooleanField {
        return static::getFormField(
            new ExtraBooleanField(),
            $name
        );
    }

    private static function isList(array $value): bool {
        $expectedKey = 0;
        foreach ($value as $key => $_) {
            if ($key !== $expectedKey++) {
                return false;
            }
        }

        return true;
    }
}

<?php

namespace TicketMind\Signals\osTicket\Configuration;

class ConfigValues {
    const QUEUE_URL = 'queue_url';
    const API_KEY = 'api_key';
    const FORWARD_ENABLED = 'forward_enabled';
    const WITH_CONTENT = 'with_content';

    public static function getQueueUrl(): ?string {
        return static::getTextboxValue(static::QUEUE_URL)?->getClean();
    }

    public static function getApiKey(): ?string {
        return static::getPasswordValue(static::API_KEY)?->getClean();
    }

    public static function isForwardingEnabled(): bool {
        return (bool) static::getAsBooleanValue(static::FORWARD_ENABLED)?->getClean();
    }

    public static function includeContent(): bool {
        return (bool) static::getAsBooleanValue(static::WITH_CONTENT)?->getClean();
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

    private static function getAsBooleanValue(string $name): ExtraBooleanField {
        return static::getFormField(
            new ExtraBooleanField(),
            $name
        );
    }
}
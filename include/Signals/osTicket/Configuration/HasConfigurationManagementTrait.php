<?php

namespace TicketMind\Data\Signals\osTicket\Configuration;

trait HasConfigurationManagementTrait {

    /**
     * @template T of \FormField
     * @param T $field
     * @param string $name
     * @return T
     */

    protected static function getConfigAsFormField(\FormField $field, string $name): \FormField {
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

    protected static function getConfigAsTextboxField(string $name): \TextboxField {
        return static::getConfigAsFormField(
            new \TextboxField(),
            $name
        );
    }

    protected static function getConfigAsPasswordField(string $name): \TextboxField {
        return static::getConfigAsFormField(
            new \TextboxField(),
            $name
        );
    }

    protected static function getConfigAsBooleanField(string $name): ExtraBooleanField {
        return static::getConfigAsFormField(
            new ExtraBooleanField(),
            $name
        );
    }
}
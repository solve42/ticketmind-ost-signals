<?php

namespace TicketMind\Data\Signals\osTicket\Configuration;

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.translation.php');
require_once(INCLUDE_DIR . 'class.forms.php');


class TicketMindSignalsPluginConfig extends \PluginConfig implements \PluginCustomConfig {
    public function getOptions() {
        return array(
            'section' => new \SectionBreakField(array(
                'label' => __('TicketMind Queue Settings'),
            )),
            'queue_url' => new \TextboxField(array(
                'label' => __('Queue URL'),
                'hint' => __('The URL endpoint where tickets will be forwarded'),
                'configuration' => [
                    'size' => 60,
                    'length' => 256,
                ],
            )),
//            'api_key' => new \TextboxField(array(
//                'label' => __('API Key'),
//                'configuration' => array(
//                    'size' => 60,
//                    'length' => 100,
//                ),
//                'hint' => __('API key for authentication with the queue service'),
//            )),
//            'forward_enabled' => new \BooleanField(array(
//                'label' => __('Enable Forwarding'),
//                'default' => true,
//                'hint' => __('Enable or disable ticket forwarding to the queue'),
//            )),
//            'debug_section' => new \SectionBreakField(array(
//                'label' => __('Debug Settings'),
//            )),
//            'debug_logging' => new \BooleanField(array(
//                'label' => __('Enable Debug Logging'),
//                'default' => false,
//                'hint' => __('Log all signal events to osTicket system log (Admin Panel → Dashboard → System Logs)'),
//            )),
        );
    }

    function renderConfig()
    {
        $options = [];
        $form = $this->getForm();
        include \BitfinexStreamerPlugin::PLUGIN_DIR . '/templates/configuration-form.tmpl.php';
    }

    function saveConfig() {
        // TODO: Change copypasta
        try {
            $form = $this->getForm();

            if ($form instanceof \Form && $form->isValid() === TRUE) {
                $errors = [];
                $values = $form->getClean();

                $has_requirements = \is_array($values) === TRUE
                    && $this->pre_save($values, $errors) === TRUE
                    && \count($errors += $form->errors()) === 0;

                if ($has_requirements) {
                    $data = [];

                    foreach ($values as $name => $value) {
                        $field = $form->getField($name);

                        if ($field instanceof \FormField) {
                            try {
                                $data[$name] = $field->to_database($value);
                            }

                            catch (\FieldUnchanged $ex) {
                                unset($data[$name]);
                            }
                        }
                    }

                    return $this->updateAll($data);
                }
            }
        }

        catch (\Throwable $ex) {
        }

        return FALSE;
    }

}
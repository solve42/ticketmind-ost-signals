<?php

namespace TicketMind\Data\Signals\osTicket\Configuration;

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
            'debug_logging' => new ExtraBooleanField(array(
                'label' => __('Enable Debug Logging'),
                'default' => NULL,
                'hint' => __('Log all signal events to osTicket system log (Admin Panel → Dashboard → System Logs)'),
            )),
        );
    }

    function renderConfig()
    {
        $options = [];
        $form = $this->getForm();
        include \TicketMindSignalsPlugin::PLUGIN_DIR . '/templates/configuration-form.tmpl.php';
    }

    function renderCustomConfig()
    {
        $this->renderConfig();
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
<?php

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
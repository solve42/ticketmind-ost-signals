<?php

namespace TicketMind\Data\Signals\osTicket\Configuration;

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.translation.php');


use Bitfinex\Data\Streamer\osTicket\Fields\BasicTextboxField;
use Bitfinex\Data\Streamer\osTicket\Fields\Validators\HelpdeskCodeFieldValidator;

class TicketMindSignalsPluginConfig extends \PluginConfig implements \PluginCustomConfig {
    
    // Compatibility function for older osTicket versions
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; }
            );
        }
        return Plugin::translate('ticketmind-ost-signals');
    }
    
    public function getOptions() {
        $weight = 0;
        $increment = 100;

        return array(
            'section' => new \SectionBreakField(array(
                'label' => __('TicketMind Queue Settings'),
                '#weight' => ($weight += $increment),
            )),
            'queue_url' => new \BasicTextboxField(array(
                'label' => __('Queue URL'),
                'hint' => __('The URL endpoint where tickets will be forwarded'),
                'default' => NULL,
                'required' => FALSE,
                'configuration' => [
                    'length' => 256,
                    'validator-error' => __('URL is not valid'),
                    'classes' => 'custom-form-field custom-form-field--basictextbox',
                ],
                '#weight' => ($weight += $increment),
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
        // TODO: Implement renderConfig() method.
    }

    function saveConfig()
    {
        // TODO: Implement saveConfig() method.
    }
}
<?php
/**
 * TicketMind Support AI Agent osTicket Plugin
 * Copyright (C) 2026 Solve42 GmbH
 * SPDX-License-Identifier: GPL-2.0-only
 */

namespace TicketMind\Plugin\Signals\osTicket\Configuration;

/**
 * Loads agent choices only when the configuration form needs to render them.
 * Plugin configuration is instantiated during osTicket bootstrap, before the
 * global configuration object required by Staff::getStaffMembers() exists.
 */
class AgentChoiceField extends \ChoiceField {
    public function getChoices($verbose = false, $options = array()) {
        if ($this->_choices === null || $verbose) {
            global $cfg;
            $this->_choices = array('' => __('— All agents —'));

            if ($cfg) {
                $this->_choices += \Staff::getStaffMembers();
            }
        }

        return $this->_choices;
    }
}

/**
 * The agent whose internal notes can be used as reply drafts.
 */
class ResponseAgentChoiceField extends AgentChoiceField {
    public function getChoices($verbose = false, $options = array()) {
        if ($this->_choices === null || $verbose) {
            global $cfg;
            $this->_choices = array('' => __('— No response agent —'));

            if ($cfg) {
                $this->_choices += \Staff::getStaffMembers();
            }
        }

        return $this->_choices;
    }
}

/**
 * Loads help-topic choices lazily for the same bootstrap-safe behaviour.
 */
class HelpTopicChoiceField extends \ChoiceField {
    public function getChoices($verbose = false, $options = array()) {
        if ($this->_choices === null || $verbose) {
            global $cfg;
            // A named key keeps a selection containing only this option from
            // looking like a sequential list of topic labels when read back.
            $this->_choices = array('none' => __('— No help topic —'));
            if ($cfg) {
                $this->_choices += \Topic::getAllHelpTopics();
            }
        }

        return $this->_choices;
    }
}

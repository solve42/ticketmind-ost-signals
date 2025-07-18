<?php

namespace TicketMind\Data\Signals\osTicket\Configuration;

require_once(INCLUDE_DIR . 'class.forms.php');

/**
 * Need extra field, since BooleanField is broken.
 */
class ExtraBooleanField extends \BooleanField {

  /**
   * {@inheritDoc}
   */
  public function getClean($validate = TRUE) {
    if (!isset($this->{'_clean'}) && !isset($this->{'value'})) {
      $this->{'value'} = $this->parse($this->getWidget()->{'value'});
    }

    return parent::getClean($validate);
  }

}

<?php
/**
 * TicketMind Signals Plugin — TicketMind API Client
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

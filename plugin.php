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
 *  SPDX-License-Identifier: GPL-2.0-only
 */

return [
  'id' => 'ticketmind:ost:signals',
  'version' => '1.0.1',
  'ost_version' => '1.10',
  'name' => 'TicketMind Support AI Agent osTicket Plugin',
  'author' => 'Solve42 GmbH',
  'description' => 'Plugin to forward ticket created and thread entry created signals from osTickets to TicketMind Support AI Agent',
  'url' => 'https://github.com/solve42/ticketmind-ost-signals',
  'plugin' => 'TicketMindSignalsPlugin.php:TicketMindSignalsPlugin',
];

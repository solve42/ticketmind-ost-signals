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

namespace TicketMind\Plugin\Signals\osTicket\Client;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TicketMind\Plugin\Signals\osTicket\Configuration\ConfigValues;

class RestApiClient {
    private readonly string $baseUrl;
    private readonly string $queueUrl;
    private readonly string $ragUrl;
    private readonly string $apiKey;
    private HttpClientInterface $httpClient;

    public function __construct() {
        $this->baseUrl = ConfigValues::getTicketMindApiURL();
        $this->queueUrl = $this->baseUrl . '/ticketmind/thread-entries/';
        $this->ragUrl = $this->baseUrl . '/ticketmind/rag/';
        $this->httpClient = HttpClient::create();
    }

    public function sendTicket2Rag(array $payload): bool {
        return $this->sendRequest($this->ragUrl, $payload);
    }

    public function sendSignal(array $payload): bool {
        return $this->sendRequest($this->queueUrl, $payload);
    }

    private function sendRequest(string $url, array $payload): bool {
        if (!$this->isConfigured()) {
            $this->logError('TicketMind Plugin not configured properly');
            return false;
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Token ' . $this->apiKey,
                    'User-Agent' => 'TicketMind-OST-Signals/1.0',
                ],
                'body' => $jsonPayload,
                'timeout' => 30,
                'max_redirects' => 3,
                'verify_peer' => true,
                'verify_host' => true,
            ]);
            
            $httpCode = $response->getStatusCode();
            $responseContent = $response->getContent(false);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                return true;
            } else {
                error_log(
                    'HTTP client response status: ' . $httpCode . " Content: ". $responseContent
                    . ". For payload: " . $jsonPayload);
                $this->logError('HTTP client response status: ' . $httpCode . " Content: ". $responseContent);
                return false;
            }
            
        } catch (TransportExceptionInterface $e) {
            error_log(
                'HTTP client error: ' . $e->getMessage() . ". For payload: " . $jsonPayload);
            $this->logError('HTTP client error: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log('Unexpected error: ' . $e->getMessage() . ". For payload: " . $jsonPayload);
            $this->logError('Unexpected error: ' . $e->getMessage());
            return false;
        }
    }

    private function isConfigured(): bool {
        return !empty($this->baseUrl) && !empty($this->apiKey);
    }

    private function logError(string $message): void {
        $GLOBALS['ost']->logError('TicketMind Plugin RestApiClient', $message);
    }

    private function logDebug(string $message): void {
        $GLOBALS['ost']->logDebug('TicketMind Plugin RestApiClient', $message);
    }
}
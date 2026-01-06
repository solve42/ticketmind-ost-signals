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

use TicketMind\Plugin\Signals\osTicket\Configuration\ConfigValues;

class RestApiClientPure
{
    private readonly string $baseUrl;
    private readonly string $queueUrl;
    private readonly string $ragUrl;
    private readonly string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string)ConfigValues::getTicketMindApiURL(), '/');
        $this->queueUrl = $this->baseUrl . '/ticketmind/thread-entries/';
        $this->ragUrl = $this->baseUrl . '/ticketmind/rag/';
        $this->apiKey = (string)ConfigValues::getApiKey();
    }

    public function sendTicket2Rag(array $payload): bool
    {
        return $this->sendRequest($this->ragUrl, $payload);
    }

    public function sendSignal(array $payload): bool
    {
        return $this->sendRequest($this->queueUrl, $payload);
    }

    private function sendRequest(string $url, array $payload): bool
    {
        if (!$this->isConfigured()) {
            $this->logError('TicketMind Plugin not configured properly');
            return false;
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            $this->logError('JSON encode failed: ' . json_last_error_msg());
            return false;
        }

        $headers = [
            'Content-Type: application/json',
            'Authorization: Token ' . $this->apiKey,
            'User-Agent: TicketMind-OST-Signals/1.0',
        ];

        // Prefer cURL (best control over TLS / status codes / timeouts)
        if (function_exists('curl_init')) {
            return $this->sendWithCurl($url, $jsonPayload, $headers);
        }

        // Fallback without cURL (less control, but works on minimal PHP installs)
        return $this->sendWithStreams($url, $jsonPayload, $headers);
    }

    private function sendWithCurl(string $url, string $jsonPayload, array $headers): bool
    {
        $ch = curl_init($url);
        if (!$ch) {
            $this->logError('Failed to init cURL');
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,   // so we can separate headers/body
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,

            // TLS verification (recommended)
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw = curl_exec($ch);

        if ($raw === false) {
            $err = curl_error($ch);
            $code = curl_errno($ch);
            curl_close($ch);

            error_log('HTTP client error (cURL): ' . $err . " (errno=$code). For payload: " . $jsonPayload);
            $this->logError('HTTP client error (cURL): ' . $err . " (errno=$code)");
            return false;
        }

        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $body = substr($raw, $headerSize);
        if ($status >= 200 && $status < 300) {
            return true;
        }

        error_log('HTTP client response status: ' . $status . ' Content: ' . $body . '. For payload: ' . $jsonPayload);
        $this->logError('HTTP client response status: ' . $status . ' Content: ' . $body);
        return false;
    }

    private function sendWithStreams(string $url, string $jsonPayload, array $headers): bool
    {
        // Note: stream wrapper has limited redirect support and status parsing.
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $jsonPayload,
                'timeout' => 30,
                'ignore_errors' => true, // fetch body even on 4xx/5xx
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            $last = error_get_last();
            $msg = $last ? $last['message'] : 'Unknown stream error';
            error_log('HTTP client error (streams): ' . $msg . '. For payload: ' . $jsonPayload);
            $this->logError('HTTP client error (streams): ' . $msg);
            return false;
        }

        // Parse status code from $http_response_header
        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                    $status = (int)$m[1];
                    break;
                }
            }
        }

        if ($status >= 200 && $status < 300) {
            return true;
        }

        error_log('HTTP client response status: ' . $status . ' Content: ' . $body . '. For payload: ' . $jsonPayload);
        $this->logError('HTTP client response status: ' . $status . ' Content: ' . $body);
        return false;
    }

    private function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->apiKey);
    }

    private function logError(string $message): void
    {
        // In osTicket plugin context, $GLOBALS['ost'] may exist
        if (isset($GLOBALS['ost']) && is_object($GLOBALS['ost']) && method_exists($GLOBALS['ost'], 'logError')) {
            $GLOBALS['ost']->logError('TicketMind Plugin RestApiClient', $message);
            return;
        }
        // Fallback
        error_log('[TicketMind] ' . $message);
    }

    private function logDebug(string $message): void
    {
        if (isset($GLOBALS['ost']) && is_object($GLOBALS['ost']) && method_exists($GLOBALS['ost'], 'logDebug')) {
            $GLOBALS['ost']->logDebug('TicketMind Plugin RestApiClient', $message);
            return;
        }
        // optional fallback:
        // error_log('[TicketMind DEBUG] ' . $message);
    }
}

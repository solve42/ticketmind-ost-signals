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

require_once(__DIR__ . '/../Configuration/ConfigValues.php');

use TicketMind\Plugin\Signals\osTicket\Configuration\ConfigValues;

class RestApiClientPure
{
    private readonly string $baseUrl;
    private readonly string $queueUrl;
    private readonly string $ragUrl;
    private readonly string $apiKey;
    private readonly ?string $tlsCaFile;

    public function __construct()
    {
        $this->baseUrl = rtrim((string)ConfigValues::getTicketMindApiURL(), '/');
        $this->queueUrl = $this->baseUrl . '/ticketmind/thread-entries/';
        $this->ragUrl = $this->baseUrl . '/ticketmind/rag/';
        $this->apiKey = (string)ConfigValues::getApiKey();
        $this->tlsCaFile = ConfigValues::getTlsCaFile();
    }

    public function sendTicket2Rag(array $payload): bool
    {
        $this->logDebug('Try to sendTicket2Rag...');
        $result = $this->sendRequest($this->ragUrl, $payload);
        $this->logDebug('sendTicket2Rag done...');
        return $result;
    }

    public function sendSignal(array $payload): bool
    {
        $this->logDebug('Try to send ticket / thread signal...');
        $result = $this->sendRequest($this->queueUrl, $payload);
        $this->logDebug('ticket / thread signal sending done...');
        return $result;
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

        if (function_exists('curl_init')) {
            // Prefer cURL (best control over TLS / status codes / timeouts)
            $this->logDebug('Send via Curl...');
            $result = $this->sendWithCurl($url, $jsonPayload, $headers);
            return $result;
        } else {
            // Fallback without cURL (less control, but works on minimal PHP installs)
            $this->logDebug('Send via Streams...');
            $result = $this->sendWithStreams($url, $jsonPayload, $headers);
            return $result;
        }
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

        if ($this->tlsCaFile !== null) {
            if (is_readable($this->tlsCaFile)) {
                curl_setopt($ch, CURLOPT_CAINFO, $this->tlsCaFile);
            } else {
                $this->logError('Configured TLS CA file is not readable: ' . $this->tlsCaFile);
                curl_close($ch);
                return false;
            }
        }

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
        $sslOptions = [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ];
        $tlsCaFile = $this->getReadableTlsCaFile();
        if ($tlsCaFile !== null) {
            $sslOptions['cafile'] = $tlsCaFile;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $jsonPayload,
                'timeout' => 30,
                'ignore_errors' => true, // fetch body even on 4xx/5xx
            ],
            'ssl' => $sslOptions,
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
        $this->logDebug('HTTP client response status: ' . $status . ' Content: ' . $body);

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

    private function getReadableTlsCaFile(): ?string
    {
        if ($this->tlsCaFile === null) {
            return null;
        }

        if (is_readable($this->tlsCaFile)) {
            return $this->tlsCaFile;
        }

        $this->logError('Configured TLS CA file is not readable: ' . $this->tlsCaFile);
        return null;
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

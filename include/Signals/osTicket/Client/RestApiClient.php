<?php

namespace TicketMind\Data\Signals\osTicket\Client;

use TicketMind\Data\Signals\osTicket\Configuration\Helper;

class RestApiClient {

    private $queueUrl;
    private $apiKey;
    private $debugLogging;

    public function __construct() {
        $this->queueUrl = Helper::getQueueUrl();
        $this->apiKey = Helper::getApiKey();
        $this->debugLogging = Helper::isDebugLoggingEnabled();
    }

    public function sendPayload(array $payload): bool {
        if (!$this->isConfigured()) {
            $this->logError('TicketMind Plugin not configured properly');
            return false;
        }

        $this->logDebug('Sending REST API request', json_encode($payload, JSON_PRETTY_PRINT));

        return $this->sendRequest($payload);
    }

    private function sendRequest(array $payload): bool {
        $ch = curl_init();

        //$jsonPayload = json_encode($payload);
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->queueUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'User-Agent: TicketMind-OST-Signals/1.0',
                'Content-Length: ' . strlen($jsonPayload)
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logError('cURL error: ' . $error);
            return false;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->logDebug('REST API request successful', "HTTP $httpCode: $response");
            return true;
        } else {
            $this->logError("REST API request failed with HTTP $httpCode: $response");
            return false;
        }
    }

    private function isConfigured(): bool {
        return !empty($this->queueUrl) && !empty($this->apiKey);
    }

    private function logError(string $message): void {
        global $ost;
        if ($ost && method_exists($ost, 'logError')) {
            $ost->logError('TicketMind Plugin', $message);
        } else {
            error_log("ERROR: TicketMind Plugin: $message");
        }
    }

    private function logDebug(string $title, string $message): void {
        if (!$this->debugLogging) {
            return;
        }

        global $ost;
        if ($ost && method_exists($ost, 'logDebug')) {
            $ost->logDebug('TicketMind Plugin', "$title: $message");
        } else {
            error_log("DEBUG: TicketMind Plugin - $title: $message");
        }
    }
}
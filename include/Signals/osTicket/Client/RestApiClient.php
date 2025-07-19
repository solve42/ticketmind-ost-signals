<?php

namespace TicketMind\Data\Signals\osTicket\Client;

use TicketMind\Data\Signals\osTicket\Configuration\Helper;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class RestApiClient {

    private $queueUrl;
    private $apiKey;
    private $debugLogging;
    private HttpClientInterface $httpClient;

    public function __construct() {
        $this->queueUrl = Helper::getQueueUrl();
        $this->apiKey = Helper::getApiKey();
        $this->debugLogging = Helper::isDebugLoggingEnabled();
        $this->httpClient = HttpClient::create();
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
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $response = $this->httpClient->request('POST', $this->queueUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->apiKey,
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
                return false;
            }
            
        } catch (TransportExceptionInterface $e) {
            error_log('HTTP client error: ' . $e->getMessage());
            $this->logError('HTTP client error: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log('Unexpected error: ' . $e->getMessage());
            $this->logError('Unexpected error: ' . $e->getMessage());
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
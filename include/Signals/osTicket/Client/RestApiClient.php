<?php

namespace TicketMind\Data\Signals\osTicket\Client;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TicketMind\Data\Signals\osTicket\Configuration\ConfigValues;

class RestApiClient {

    private $queueUrl;
    private $apiKey;
    private HttpClientInterface $httpClient;

    public function __construct() {
        $this->queueUrl = ConfigValues::getQueueUrl();
        $this->apiKey = ConfigValues::getApiKey();
        $this->httpClient = HttpClient::create();
    }

    public function sendPayload(array $payload): bool {
        if (!$this->isConfigured()) {
            $this->logError('TicketMind Plugin not configured properly');
            return false;
        }

        return $this->sendRequest($payload);
    }

    private function sendRequest(array $payload): bool {
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $response = $this->httpClient->request('POST', $this->queueUrl, [
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
        return !empty($this->queueUrl) && !empty($this->apiKey);
    }

    private function logError(string $message): void {
        $GLOBALS['ost']->logError('TicketMind Plugin RestApiClient', $message);
    }

    private function logDebug(string $message): void {
        $GLOBALS['ost']->logDebug('TicketMind Plugin RestApiClient', $message);
    }
}
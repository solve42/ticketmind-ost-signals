<?php

namespace TicketMind\Data\Signals\osTicket\Client;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TicketMind\Data\Signals\osTicket\Configuration\Helper;

class RestApiClient {

    private $queueUrl;
    private $apiKey;
    private HttpClientInterface $httpClient;

    public function __construct() {
        $this->queueUrl = Helper::getQueueUrl();
        $this->apiKey = Helper::getApiKey();
        $this->httpClient = HttpClient::create();
    }

    public function sendPayload(array $payload): bool {
        if (!$this->isConfigured()) {
            $this->logError('TicketMind Plugin not configured properly');
            return false;
        }

        //$this->logDebug(json_encode($payload, JSON_PRETTY_PRINT));

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
                $this->logError('HTTP client response status: ' . $httpCode . " Content: ". $responseContent);
                return false;
            }
            
        } catch (TransportExceptionInterface $e) {
            $this->logError('HTTP client error: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
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
<?php

declare(strict_types=1);

namespace App\Agent;

use App\Action;
use App\Game;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class GeminiAgent implements Agent
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger('GeminiAgent');
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../../logs/gemini.log', Logger::INFO));
    }
    public function decideDiscard(Game $game): Action
    {
        $prompt = $game->promptDiscard();

        $response = $this->prompt($prompt);

        return new Action($response);
    }

    public function decideCall(Game $game, int $my_player_index): Action
    {
        $prompt = $game->promptCall($my_player_index);

        $response = $this->prompt($prompt);

        return new Action($response);
    }

    private function prompt(string $prompt): array
    {
        // プロンプトの先頭100文字をログ出力
        $promptPreview = mb_substr($prompt, 0, 100);
        $this->logger->info('Prompt', ['preview' => $promptPreview]);

        // Gemini API にプロンプトを送信
        $apiKey = \GEMINI_API_KEY;
        $model = \GEMINI_MODEL;
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $client = new Client([
            'base_uri' => 'https://generativelanguage.googleapis.com',
            'timeout' => 30,
        ]);

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 16384,
            ]
        ];

        try {
            $response = $client->post($url, [
                'query' => ['key' => $apiKey],
                'json' => $data,
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]);
        } catch (RequestException $e) {
            // HTTPエラーまたはネットワークエラー
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : '';

            $this->logger->error('Request failed', [
                'statusCode' => $statusCode,
                'message' => $e->getMessage(),
                'responseBody' => $responseBody
            ]);

            if ($responseBody) {
                $result = json_decode($responseBody, true);
                $errorMessage = $result['error']['message'] ?? $e->getMessage();
            } else {
                $errorMessage = $e->getMessage();
            }

            throw new \RuntimeException("Gemini API request failed with status {$statusCode}: {$errorMessage}");
        } catch (GuzzleException $e) {
            // その他のGuzzle例外（接続エラーなど）
            $this->logger->error('Guzzle exception', [
                'message' => $e->getMessage()
            ]);

            throw new \RuntimeException("Gemini API request failed: {$e->getMessage()}");
        }

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        if ($statusCode !== 200) {
            // エラー時はレスポンス全体をログ出力
            $this->logger->info('Response', ['body' => $body]);
            $result = json_decode($body, true);
            $errorMessage = $result['error']['message'] ?? 'Unknown error';
            throw new \RuntimeException("Gemini API request failed with status {$statusCode}: {$errorMessage}");
        }

        $result = json_decode($body, true);

        // トークン使用量を解析してログ出力
        if (isset($result['usageMetadata'])) {
            $usage = $result['usageMetadata'];
            $promptTokens = $usage['promptTokenCount'] ?? 0;
            $candidatesTokens = $usage['candidatesTokenCount'] ?? 0;
            $thoughtsTokens = $usage['thoughtsTokenCount'] ?? 0;
            $totalTokens = $usage['totalTokenCount'] ?? 0;

            // コスト計算 (Gemini 2.5 Flash-Lite: $0.10/1M input, $0.40/1M output)
            $inputCost = $promptTokens * 0.10 / 1000000;
            $outputCost = ($candidatesTokens + $thoughtsTokens) * 0.40 / 1000000;
            $totalCost = $inputCost + $outputCost;

            $this->logger->info('Token Usage', [
                'input' => $promptTokens,
                'output' => $candidatesTokens,
                'thinking' => $thoughtsTokens,
                'total' => $totalTokens,
                'cost_usd' => sprintf('$%.6f', $totalCost),
                'cost_jpy' => sprintf('%.4f円', $totalCost * 150) // 1ドル=150円で計算
            ]);
        }

        // レスポンスの検証とエラーハンドリング
        if (!isset($result['candidates'][0])) {
            $this->logger->error('No candidates in response', ['result' => $result]);
            throw new \RuntimeException('Invalid Gemini API response: no candidates found');
        }

        $candidate = $result['candidates'][0];
        $finishReason = $candidate['finishReason'] ?? 'UNKNOWN';

        if (!isset($candidate['content']['parts'][0]['text'])) {
            $this->logger->error('No text in response', [
                'finishReason' => $finishReason,
                'candidate' => $candidate
            ]);
            throw new \RuntimeException("Invalid Gemini API response format. Finish reason: {$finishReason}");
        }

        $text = $candidate['content']['parts'][0]['text'];

        // レスポンスからJSON（``` と ``` または ```json と ``` で囲まれた範囲）を抜き出す
        if (preg_match('/```(?:json)?\s*\n(.*?)\n```/s', $text, $matches)) {
            $jsonString = $matches[1];
        } else {
            // コードブロックで囲まれていない場合は全体をJSONとして扱う
            $jsonString = $text;
        }

        // JSONをデコードして連想配列でreturn
        $decoded = json_decode(trim($jsonString), true);

        if ($decoded === null) {
            throw new \RuntimeException('Failed to decode JSON from Gemini response: ' . $jsonString);
        }

        // 正常応答時はJSONのみをログ出力
        $this->logger->info('Response JSON', $decoded);

        return $decoded;
    }
}

<?php
namespace MartinK\ChatGptSiteAssistant;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;

class ChatGptService
{
    protected Client $client;
    protected string $apiKey;
    protected string $model;
    protected int $maxRetries;
    protected int $timeout;
    protected string $baseUri;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => config('chatgpt-site-assistant.timeout', 30)
        ]);

        $this->apiKey = config('chatgpt-site-assistant.api_key');
        $this->model = config('chatgpt-site-assistant.model', 'gpt-3.5-turbo');
        $this->maxRetries = config('chatgpt-site-assistant.max_retries', 3);
        $this->baseUri = config('chatgpt-site-assistant.base_uri', 'https://api.openai.com/v1');

        // Ensure API key is set in config
        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API key is not set in the configuration.');
        }
    }

    public function getChatGptResponse(string $input): string
    {
        $attempts = 0;

        while ($attempts < $this->maxRetries) {
            try {
                $response = $this->client->post("{$this->baseUri}/chat/completions", [
                    'headers' => [
                        'Authorization' => "Bearer {$this->apiKey}",
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => $this->model,
                        'messages' => [
                            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                            ['role' => 'user', 'content' => "Explain the purpose of this web page or route: $input"],
                        ],
                        'temperature' => 0.7,
                        'max_tokens' => 500,
                    ],
                ]);

                $result = json_decode($response->getBody()->getContents(), true);

                return $result['choices'][0]['message']['content'] ?? '[No response]';

            } catch (RequestException $e) {
                $attempts++;

                // Rate limit / quota / transient issues (429 status code)
                if ($e->getResponse() && $e->getResponse()->getStatusCode() === 429) {
                    sleep(2); // Delay before retrying
                    continue;
                }

                // Log and rethrow other client/server errors
                \Log::error('Error with OpenAI API', ['message' => $e->getMessage()]);
                throw $e;
            } catch (\Exception $e) {
                // General error handler
                \Log::error('General error with ChatGPT', ['message' => $e->getMessage()]);
                throw $e;
            }
        }

        // If maximum retries exceeded
        throw new \Exception('Exceeded maximum retries for OpenAI API');
    }

    public function parseSitemap(string $sitemapUrl): array
    {
        // Fetch the sitemap content
        $content = @file_get_contents($sitemapUrl);
        if ($content === false) {
            throw new \Exception("Unable to fetch sitemap.xml from $sitemapUrl");
        }

        // Parse the sitemap XML
        $xml = @simplexml_load_string($content);
        if ($xml === false) {
            throw new \Exception("Invalid sitemap.xml format.");
        }

        $urls = [];
        foreach ($xml->url as $entry) {
            $loc = (string) $entry->loc;
            if ($loc) {
                $urls[] = $loc;
            }
        }

        return $urls;
    }

    public function fetchHtmlContent(string $url): ?string
    {
        try {
            $response = $this->client->get($url);
            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            // Log error and return null
            \Log::error("Failed to fetch content from $url", ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function saveContentToDatabase(string $url, string $content): void
    {
        // Save the URL and its content to the database
        WebsiteContent::create([
            'url' => $url,
            'content' => $content,
        ]);
    }

    public function processSitemapAndSaveContent(string $sitemapUrl): void
    {
        // Parse sitemap to get all URLs
        $urls = $this->parseSitemap($sitemapUrl);

        // For each URL, fetch HTML content and save to database
        foreach ($urls as $url) {
            $content = $this->fetchHtmlContent($url);
            if ($content) {
                $this->saveContentToDatabase($url, $content);
            }
        }
    }

    public function askQuestion(string $question, string $context): string
    {
        $attempts = 0;
        $systemMessage = config('chatgpt-site-assistant.system_message');

        while ($attempts < $this->maxRetries) {
            try {
                $response = $this->client->post("{$this->baseUri}/chat/completions", [
                    'headers' => [
                        'Authorization' => "Bearer {$this->apiKey}",
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => $this->model,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => $systemMessage,
                            ],
                            [
                                'role' => 'user',
                                'content' => "Context:\n" . $context,
                            ],
                            [
                                'role' => 'user',
                                'content' => "Question: " . $question,
                            ],
                        ],
                        'temperature' => 0.7,
                        'max_tokens' => 500,
                    ],
                ]);

                $result = json_decode($response->getBody()->getContents(), true);
                return $result['choices'][0]['message']['content'] ?? '[No response]';

            } catch (RequestException $e) {
                $attempts++;

                if ($e->getResponse() && $e->getResponse()->getStatusCode() === 429) {
                    sleep(2);
                    continue;
                }

                \Log::error('OpenAI API error in askQuestion', ['error' => $e->getMessage()]);
                throw $e;
            } catch (\Exception $e) {
                \Log::error('General error in askQuestion', ['error' => $e->getMessage()]);
                throw $e;
            }
        }

        throw new \Exception('Exceeded maximum retries for OpenAI API in askQuestion');
    }


}


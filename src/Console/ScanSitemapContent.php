<?php
namespace MartinK\ChatGptSiteAssistant\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use MartinK\ChatGptSiteAssistant\ChatGptService;
use MartinK\ChatGptSiteAssistant\WebsiteContent;

class ScanSitemapContent extends Command
{
    protected $signature = 'chatgpt:scan-sitemap {url}';
    protected $description = 'Scan all URLs from a sitemap.xml and summarize them using ChatGPT';

    protected ChatGptService $chatGpt;

    public function __construct(ChatGptService $chatGpt)
    {
        parent::__construct();
        $this->chatGpt = $chatGpt;
    }

    public function handle(): void
    {
        $sitemapUrl = $this->argument('url');
        $this->info("Fetching sitemap from: $sitemapUrl");

        $response = Http::get($sitemapUrl);
        if (!$response->ok()) {
            $this->error("Failed to fetch sitemap: " . $response->status());
            return;
        }

        $xml = simplexml_load_string($response->body());
        if (!$xml || !isset($xml->url)) {
            $this->error("Invalid sitemap structure.");
            return;
        }

        $maxRetries = 3;
        $retryDelay = 2; // Retry delay in seconds

        // Fetch all the existing URLs from the database
        $existingUrls = WebsiteContent::pluck('url')->toArray();

        foreach ($xml->url as $url) {
            $loc = (string)$url->loc;

            // Skip if the URL has already been processed
            if (in_array($loc, $existingUrls)) {
                $this->info("Skipping already processed URL: $loc");
                continue;
            }

            $this->line("Scanning: $loc");

            $attempts = 0;
            $success = false;

            while ($attempts < $maxRetries && !$success) {
                try {
                    $attempts++;
                    $this->info("Attempt #$attempts: Processing $loc");

                    // Fetch page content
                    $pageContent = Http::get($loc)->body();

                    // Remove JavaScript content using regex
                    $pageContent = preg_replace('#<script.*?>.*?</script>#is', '', $pageContent);

                    // Remove CSS styles (inline and in <style> tags)
                    $pageContent = preg_replace('#<style.*?>.*?</style>#is', '', $pageContent); // Remove <style> tags
                    $pageContent = preg_replace('#style=".*?"#is', '', $pageContent); // Remove inline styles

                    // Remove CSS classes (optional)
                    $pageContent = preg_replace('#class=".*?"#is', '', $pageContent); // Remove CSS classes

                    // Strip HTML tags to save only plain text
                    $plainTextContent = strip_tags($pageContent);

                    // Remove extra spaces and check if content is not empty
                    $plainTextContent = trim(preg_replace('/\s+/', ' ', $plainTextContent));

                    if (empty($plainTextContent)) {
                        $this->info("No useful content found in $loc. Skipping.");
                        break; // Skip this URL if content is empty
                    }

                    // Get the summary using ChatGPT
                    $summary = $this->chatGpt->getChatGptResponse($pageContent);

                    if (!$summary) {
                        $this->error("No summary returned for $loc.");
                        break; // Skip this URL if no summary is returned
                    }

                    // Save the content (plain text) and summary to the database
                    WebsiteContent::create([
                        'url' => $loc,
                        'content' => $plainTextContent,  // Save plain text content (without HTML tags)
                        'summary' => $summary,  // Save the summary
                    ]);

                    $this->info("ChatGPT Summary saved for $loc:\n" . wordwrap($summary, 100));

                    $success = true; // Mark as success if no exception
                } catch (\Exception $e) {
                    if ($attempts < $maxRetries) {
                        $backoff = $retryDelay * pow(2, $attempts - 1); // Exponential backoff
                        $this->info("Error processing $loc: " . $e->getMessage() . " - Retrying in $backoff seconds.");
                        sleep($backoff); // Wait before retrying
                    } else {
                        $this->error("Failed to process $loc after $attempts attempts: " . $e->getMessage());
                    }
                }
            }

            usleep(500000); // Slight delay to avoid rate limits (0.5s)
        }
    }
}


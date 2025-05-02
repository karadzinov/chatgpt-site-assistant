<?php
namespace MartinK\ChatGptSiteAssistant\Http\Controllers;

use Illuminate\Http\Request;
use MartinK\ChatGptSiteAssistant\ChatGptService;
use MartinK\ChatGptSiteAssistant\WebsiteContent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class ChatGptController extends Controller
{
    protected $chatGptService;

    public function __construct(ChatGptService $chatGptService)
    {
        $this->chatGptService = $chatGptService;
    }

    // Endpoint to scan sitemap
    public function scanSitemap(Request $request)
    {
        // Validate the sitemap URL
        $request->validate([
            'sitemap_url' => 'required|url',
        ]);

        $sitemapUrl = $request->input('sitemap_url');
        $urls = $this->chatGptService->parseSitemap($sitemapUrl);

        // Loop through each URL and fetch content
        foreach ($urls as $url) {
            try {
                $content = $this->chatGptService->fetchHtmlContent($url);

                if ($content) {
                    // Get ChatGPT summary for the page
                    $summary = $this->chatGptService->getChatGptResponse($content);

                    // Save both raw content and ChatGPT summary to the database
                    WebsiteContent::create([
                        'url' => $url,
                        'content' => $content,    // Raw HTML content
                        'summary' => $summary,    // ChatGPT-generated summary
                    ]);
                }
            } catch (\Exception $e) {
                // Log errors and continue with the next URL
                Log::error("Error processing $url: " . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Sitemap processed and content saved.']);
    }

    // Endpoint to get content of a URL
    public function getContent($url)
    {
        // Try to fetch content from the database
        $content = WebsiteContent::where('url', $url)->first();

        if ($content) {
            // Return the raw HTML content and ChatGPT summary
            return response()->json([
                'url' => $url,
                'content' => $content->content,   // Raw HTML content
                'summary' => $content->summary,   // ChatGPT summary
            ]);
        }

        return response()->json(['message' => 'Content not found.'], 404);
    }

    public function chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $question = $request->input('question');
        $keywords = array_slice(explode(" ", $question), 0, 5); // Limit to 5 keywords

        // Query website contents based on keywords
        $query = WebsiteContent::query();
        foreach ($keywords as $keyword) {
            $query->orWhere('content', 'LIKE', '%' . $keyword . '%')
                ->orWhere('summary', 'LIKE', '%' . $keyword . '%');
        }

        // Fetch matched content with URL
        $matchedContents = $query->limit(5)->get(['url', 'content']);

        if ($matchedContents->isEmpty()) {
            return response()->json(['message' => 'No relevant content found.'], 404);
        }

        // Format each entry with Markdown-style hyperlinks
        $contextEntries = $matchedContents->map(function ($entry) {
            $cleanContent = strip_tags($entry->content);
            return "**[Visit source]({$entry->url})**\n\n{$cleanContent}";
        })->toArray();

        $context = "Here are some summaries related to your question:\n\n" . implode("\n\n---\n\n", $contextEntries);

        // Build the full prompt
        $prompt = "
You are a helpful assistant. Please answer the user's question clearly and informatively, based on the provided context. Use hyperlinks (like [Visit source](https://example.com)) to refer users to relevant sources when appropriate.

User's question: {$question}

Context: {$context}

Please provide a detailed answer.";

        try {
            $answer = $this->chatGptService->askQuestion($question, $context, [
                'temperature' => 0.5,
                'max_tokens' => 150,
            ]);

            if (!$answer) {
                throw new \Exception('No answer returned from ChatGPT');
            }
        } catch (\Exception $e) {
            \Log::error('ChatGPT Error: ' . $e->getMessage()); // Log error for debugging
            return response()->json(['error' => 'An error occurred while contacting ChatGPT. Please try again later.'], 500);
        }

        return response()->json([
            'question' => $question,
            'answer' => $answer,
            'context' => $context // This ensures the context with URLs is also returned
        ]);
    }





    public function askQuestion($question, $context)
    {
        // Interact with OpenAI API using the provided context
        $response = $this->client->completions()->create([
            'model' => 'text-davinci-003',
            'prompt' => $context . "\n\nQuestion: " . $question . "\nAnswer:",
            'max_tokens' => 150,
        ]);

        return $response['choices'][0]['text'];
    }


}

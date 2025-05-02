# ChatGPT Site Assistant

A Laravel package that scans your website's sitemap, extracts content, and connects to OpenAI's ChatGPT API to create an intelligent assistant that understands your site's actual content.

---

## 📦 Installation

```bash
composer require martink/chatgpt-site-assistant
```

Publish Config and Migrations
```bash
php artisan vendor:publish --tag=config
php artisan vendor:publish --tag=migrations
```
Then run:
```bash
php artisan migrate
```
## ⚙️ Configuration
Add your OpenAI API settings to your .env file:
```bash
OPENAI_API_KEY=your-openai-api-key
OPENAI_MODEL=gpt-3.5-turbo
OPENAI_BASE_URI=https://api.openai.com/v1
OPENAI_MAX_RETRIES=3
OPENAI_TIMEOUT=30
OPENAI_SYSTEM_MESSAGE="You are a helpful assistant for a wedding planning website. Use the following context to answer the user's question."
```
You can override these in the published config file:
<pre><code>config/chatgpt-site-assistant.php</code></pre>

## 🧠 Usage
Step 1: Scan Your Website's Sitemap
```bash
php artisan chatgpt:scan-sitemap http://localhost/sitemap.xml
```
This command will:

* Read your sitemap.

* Visit each route listed.

* Extract the textual content.

* Store it in the <code>website_contents</code> table for future use.

## Step 2: Query the Assistant
Make a <code>POST</code> request to:
```bash
POST /api/chat
```
Example JSON body:
```
{
  "question": "What are your opening hours?"
}
```
The assistant uses your site's content to respond contextually via ChatGPT.
## 🧩 Components
* Service: ChatGptService.php – handles OpenAI API interaction.

* Command: <code>chatgpt:scan-sitemap</code> – crawls and stores your site's content.

* Controller: ChatGptController.php – exposes the assistant via API.

* Model: WebsiteContent.php – stores extracted text per URL.

### ✅ Laravel Compatibility
Laravel 8, 9, 10, and 12 supported.

PHP 8.0 – 8.4 supported.

### 📄 License

This package is open-source software licensed under the [MIT license](LICENSE).


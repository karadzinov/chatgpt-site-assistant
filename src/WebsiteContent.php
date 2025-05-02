<?php
namespace MartinK\ChatGptSiteAssistant;

use Illuminate\Database\Eloquent\Model;

class WebsiteContent extends Model
{
    protected $table = 'website_content';
    protected $fillable = ['url', 'content', 'summary'];

}

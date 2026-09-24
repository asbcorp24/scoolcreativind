<?php
namespace App\Http\Controllers;

use App\Models\NewsPost;
use App\Models\Studio;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [
            ['loc'=>route('home'),'lastmod'=>now()->toDateString(),'priority'=>'1.0'],
            ['loc'=>route('schedule'),'lastmod'=>now()->toDateString(),'priority'=>'0.8'],
            ['loc'=>route('competitions'),'lastmod'=>now()->toDateString(),'priority'=>'0.8'],
            ['loc'=>route('team'),'lastmod'=>now()->toDateString(),'priority'=>'0.7'],
            ['loc'=>route('equipment'),'lastmod'=>now()->toDateString(),'priority'=>'0.7'],
            ['loc'=>route('news.index'),'lastmod'=>now()->toDateString(),'priority'=>'0.8'],
            ['loc'=>route('apply'),'lastmod'=>now()->toDateString(),'priority'=>'0.9'],
        ];

        foreach (Studio::where('is_active',true)->get() as $studio) {
            $urls[]=[
                'loc'=>route('studios.show',$studio),
                'lastmod'=>optional($studio->updated_at)->toDateString() ?: now()->toDateString(),
                'priority'=>'0.9'
            ];
        }

        foreach (NewsPost::where('is_published',true)->get() as $post) {
            $urls[]=[
                'loc'=>route('news.show',$post),
                'lastmod'=>optional($post->updated_at)->toDateString() ?: now()->toDateString(),
                'priority'=>'0.6'
            ];
        }

        $xml=view('seo.sitemap',compact('urls'))->render();
        return response($xml,200,['Content-Type'=>'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $body="User-agent: *\n";
        $body.="Allow: /\n";
        $body.="Disallow: /admin\n";
        $body.="Disallow: /cabinet\n";
        $body.="Disallow: /my-portfolio\n";
        $body.="Sitemap: ".route('sitemap')."\n";

        return response($body,200,['Content-Type'=>'text/plain; charset=UTF-8']);
    }
}

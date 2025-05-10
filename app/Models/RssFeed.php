<?php
/**
 * This file contains the RssFeed model
 */

namespace Neoground\Charm\Blog\Models;

use Carbon\Carbon;
use Charm\Vivid\C;

/**
 * Class RssFeed
 *
 * RssFeed model
 *
 * @package App\Models\Blog
 */
class RssFeed
{
    /**
     * Create and save an XML feed for a specified language.
     *
     * @param string $lang The language code for which to create the feed or 'all' for all
     *
     * @return bool|int Returns the number of bytes written to the file on success, or false on failure.
     */
    public static function createXml(string $lang): bool|int
    {
        $feed_path = C::Storage()->getDataPath() . DS . 'blog' . DS . 'feed_' . $lang . '.xml';
        C::Storage()->deleteFileIfExists($feed_path);

        $b = new BlogPost();
        $posts = $b->getAll();

        // XML header
        $content = "<?xml version='1.0' encoding='UTF-8'?>\n<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n<channel>\n
<title>" . C::Config()->get('blog:rss.title_' . $lang) . "</title>
<link>" . C::Config()->get('blog:rss.link_' . $lang) . "</link>
<description>" . C::Config()->get('blog:rss.description_' . $lang) . "</description>
<generator>" . C::Config()->get('blog:rss.generator') . "</generator>
<copyright>" . C::Config()->get('blog:rss.copyright') . "</copyright>
<image>
  <link>" . C::Config()->get('blog:rss.blog_base_url') . "</link>
  <title>" . C::Config()->get('blog:rss.title_' . $lang) . "</title>
  <url>" . C::Router()->getBaseUrl() . "/" . C::Config()->get('blog:rss.image_relpath') . "</url>
</image>
<lastBuildDate>" . Carbon::now()->toRfc2822String() . "</lastBuildDate>
<atom:link href=\"" . C::Config()->get('blog:rss.blog_base_url') . "/feed/" . $lang . "\" rel=\"self\" type=\"application/rss+xml\" />
<language>" . $lang . "</language>\n\n";

        foreach ($posts as $post) {
            if ($post['is_published']) {
                // Add single item (post)
                if ($lang != 'all') {
                    // Single language
                    $content .= self::getEntryXml($post, '_' . $lang);
                } else {
                    // All languages
                    foreach (C::Config()->get('main:session.available_languages', []) as $l) {
                        $content .= self::getEntryXml($post, '_' . $l);
                    }
                }
            }
        }

        // XML footer
        $content .= "\n</channel>\n</rss>";

        return file_put_contents($feed_path, $content);
    }

    private static function getEntryXml(array $post, string $key_suffix = null): string
    {
        return "<item>
<title>" . $post['title' . $key_suffix] . "</title>
<guid>" . C::Config()->get('blog:rss.blog_base_url') . '/' . $post['slug' . $key_suffix] . "</guid>
<pubDate>" . Carbon::parse($post['published_at'])->toRfc2822String() . "</pubDate>
<category>" . $post['category' . $key_suffix] . "</category>
<link>" . C::Config()->get('blog:rss.blog_base_url') . '/' . $post['slug' . $key_suffix] . "</link>
<description>" . $post['excerpt' . $key_suffix] . "</description>
</item>\n";
    }
}
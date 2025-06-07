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
     * Create and save a blog XML feed for a specified language.
     *
     * @param string $lang The language code for which to create the feed or 'all' for all
     *
     * @return bool|int Returns the number of bytes written to the file on success, or false on failure.
     */
    public static function createXml(string $lang): bool|int
    {
        $feed_path = C::Storage()->getDataPath() . DS . 'blog' . DS . 'feed_' . $lang . '.xml';
        C::Storage()->createDirectoriesIfNotExisting($feed_path);
        C::Storage()->deleteFileIfExists($feed_path);

        $b = new BlogPost();
        $posts = $b->getAll();

        // Sort posts DESC
        rsort($posts);

        // Limit to latest 50 posts
        $posts = array_slice($posts, 0, C::Config()->get('blog:rss.amount', 50));

        // Format posts in the right array
        $all_langs = C::Config()->get('main:session.available_languages', []);
        $postarr = [];
        foreach ($posts as $post) {
            if ($post['is_published']) {
                // Add single item (post)
                if ($lang != 'all') {
                    // Single language
                    $postarr[] = self::formatBlogPostToEntry($post, '_' . $lang);
                } else {
                    // All languages
                    foreach ($all_langs as $l) {
                        $postarr[] = self::formatBlogPostToEntry($post, '_' . $l);
                    }
                }
            }
        }

        // XML
        $title = C::Config()->get('blog:rss.title_' . $lang);
        $description = C::Config()->get('blog:rss.description_' . $lang);
        $blog_url = C::Config()->get('blog:rss.link_' . $lang);
        $feed_url = C::Config()->get('blog:rss.blog_base_url') . "/feed/" . $lang;
        $content = self::createCustomXmlFeed($postarr, $title, $description, $blog_url, $feed_url, $lang);

        return file_put_contents($feed_path, $content);
    }

    /**
     * Create a custom XML feed.
     *
     * @param array  $posts       the posts array. each sub-array must have a structure suitable for
     *                            self::getEntryXml().
     * @param string $title       RSS / blog title.
     * @param string $description RSS / blog description.
     * @param string $blog_url    absolute URL to the blog / area the RSS feed is about.
     * @param string $feed_url    absolute URL to the feed.
     * @param string $language    language of the feed (e.g., de, en).
     *
     * @return string the RSS feed as a formatted XML string.
     */
    public static function createCustomXmlFeed(array  $posts, string $title, string $description,
                                               string $blog_url, string $feed_url, string $language = 'en'): string
    {
        // XML header
        $content = "<?xml version='1.0' encoding='UTF-8'?>\n<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n<channel>\n
<title>" . $title . "</title>
<link>" . $blog_url . "</link>
<description>" . $description . "</description>
<generator>" . C::Config()->get('blog:rss.generator', 'charm-blog') . "</generator>
<copyright>" . C::Config()->get('blog:rss.copyright', '') . "</copyright>
<image>
  <link>" . $blog_url . "</link>
  <title>" . $title . "</title>
  <url>" . C::Router()->getBaseUrl() . "/" . C::Config()->get('blog:rss.image_relpath') . "</url>
</image>
<lastBuildDate>" . Carbon::now()->toRfc2822String() . "</lastBuildDate>
<atom:link href=\"" . $feed_url . "\" rel=\"self\" type=\"application/rss+xml\" />
<language>" . $language . "</language>\n\n";

        foreach ($posts as $post) {
            if (!array_key_exists('is_published', $post) || $post['is_published']) {
                $content .= self::getEntryXml($post);
            }
        }

        // XML footer
        $content .= "\n</channel>\n</rss>";

        return $content;
    }

    /**
     * Format a blog post array into an entry compatible with self::getEntryXml().
     *
     * @param array       $post       the blog post array.
     * @param string|null $key_suffix optional key suffix (e.g., language).
     *
     * @return array the array in the right structure.
     */
    private static function formatBlogPostToEntry(array $post, string $key_suffix = null): array
    {
        return [
            'title' => $post['title' . $key_suffix],
            'link' => C::Config()->get('blog:rss.blog_base_url') . '/' . $post['slug' . $key_suffix] . "?utm_src=rss",
            'published_at' => $post['published_at'],
            'category' => $post['category' . $key_suffix],
            'description' => $post['excerpt' . $key_suffix],
        ];
    }

    /**
     * Get the XML for a single entry by array.
     *
     * Must have keys for post array: title, link (also used as guid), published_at, category, link, description,
     *                                is_published (optional bool).
     *
     * @param array $post the post array.
     *
     * @return string the entry XML as formatted string.
     */
    public static function getEntryXml(array $post): string
    {
        return "<item>
<title>" . $post['title'] . "</title>
<guid>" . $post['link'] . "</guid>
<pubDate>" . Carbon::parse($post['published_at'])->toRfc2822String() . "</pubDate>
<category>" . $post['category'] . "</category>
<link>" . $post['link'] . "</link>
<description>" . $post['description'] . "</description>
</item>\n";
    }
}
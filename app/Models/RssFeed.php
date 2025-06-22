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
    /** @var array Posts array. each sub-array must have a structure suitable for getEntryXml(). */
    protected array $posts;
    /** @var string Language of the feed, e.g., en, de, fr. */
    protected string $language = 'en';
    /** @var string Feed title. */
    protected string $title = '';
    /** @var string Feed description. */
    protected string $description = '';
    /** @var string Absolute URL to blog index page (or of the area the XML feed is about). */
    protected string $blog_url = '';
    /** @var string Absolute URL to the generated XML feed. */
    protected string $feed_url = '';

    public function __construct()
    {

    }

    public function setPosts(array $posts): static
    {
        $this->posts = $posts;
        return $this;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function setBlogUrl(string $blog_url): static
    {
        $this->blog_url = $blog_url;
        return $this;
    }

    public function setFeedUrl(string $feed_url): static
    {
        $this->feed_url = $feed_url;
        return $this;
    }

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
        return (new self())->setPosts($postarr)
            ->setLanguage($lang)
            ->setMetadataByConfig()
            ->saveXmlAsFile($feed_path);
    }

    /**
     * Set metadata fields by config values.
     *
     * Sets title, description, urls by config. Make sure the language is set first, since
     * the config keys are language dependent.
     *
     * @return static
     */
    public function setMetadataByConfig(): static
    {
        return $this->setTitle(C::Config()->get('blog:rss.title_' . $this->language, ''))
            ->setDescription(C::Config()->get('blog:rss.description_' . $this->language, ''))
            ->setBlogUrl(C::Config()->get('blog:rss.link_' . $this->language, C::Router()->getBaseUrl()))
            ->setFeedUrl(C::Config()->get('blog:rss.blog_base_url', C::Router()->getBaseUrl()) . "/feed/" . $this->language);
    }

    /**
     * Save the XML as a file.
     *
     * @param string $path The absolute path to the file.
     *
     * @return bool|int Returns the number of bytes written to the file on success, or false on failure.
     */
    public function saveXmlAsFile(string $path): bool|int
    {
        C::Storage()->createDirectoriesIfNotExisting(dirname($path));
        C::Storage()->deleteFileIfExists($path);
        return file_put_contents($path, $this->getXmlFeed());
    }

    /**
     * Get the created XML feed as a formatted string.
     *
     * @return string the formatted XML feed string.
     */
    public function getXmlFeed(): string
    {
        // XML header
        $content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n<channel>\n
<title>" . $this->title . "</title>
<link>" . $this->blog_url . "</link>
<description>" . $this->description . "</description>
<generator>" . C::Config()->get('blog:rss.generator', 'charm-blog') . "</generator>
<copyright>" . C::Config()->get('blog:rss.copyright', '') . "</copyright>
<image>
  <link>" . $this->blog_url . "</link>
  <title>" . $this->title . "</title>
  <url>" . C::Router()->getBaseUrl() . "/" . ltrim(C::Config()->get('blog:rss.image_relpath'), '/') . "</url>
</image>
<lastBuildDate>" . Carbon::now()->toRfc2822String() . "</lastBuildDate>
<atom:link href=\"" . $this->feed_url . "\" rel=\"self\" type=\"application/rss+xml\" />
<language>" . $this->language . "</language>\n\n";

        foreach ($this->posts as $post) {
            if (!array_key_exists('is_published', $post) || $post['is_published']) {
                $content .= $this->getEntryXml($post);
            }
        }

        // XML footer
        $content .= "\n</channel>\n</rss>";

        return $content;
    }

    /**
     * Format a blog post array into an entry compatible with getEntryXml().
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
            'link' => C::Config()->get('blog:rss.blog_base_url', C::Router()->getBaseUrl())
                . '/' . $post['slug' . $key_suffix] . "?utm_src=rss",
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
    public function getEntryXml(array $post): string
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
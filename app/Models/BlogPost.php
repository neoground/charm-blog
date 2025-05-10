<?php
/**
 * This file contains the BlogPost model
 */

namespace Neoground\Charm\Blog\Models;

use Carbon\Carbon;
use Charm\Vivid\C;
use Neoground\Charm\Markdown\MarkdownDocument;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Yaml\Yaml;

/**
 * Class BlogPost
 *
 * BlogPost model
 */
class BlogPost
{
    /** @var array all posts data */
    protected array $posts = [];

    /**
     * Projects constructor.
     */
    public function __construct()
    {
        // Load posts via cache on production
        if (!C::Config()->inDebugMode()) {
            if (C::has('Redis') && C::Config()->get('connections:redis.enabled', true)) {
                // Redis enabled -> use this as cache
                if (!C::Redis()->getClient()->exists('blog_posts')) {
                    $this->getMetaData();
                    C::Redis()->getClient()->set('blog_posts', json_encode($this->posts));
                } else {
                    $this->posts = json_decode(C::Redis()->getClient()->get('blog_posts'), true);
                }
            } else {
                // JSON file storage
                $cache_file = C::Storage()->getCachePath() . DS . 'blog_posts.cache';
                if (!file_exists($cache_file)) {
                    $this->getMetaData();
                    file_put_contents($cache_file, json_encode($this->posts));
                } else {
                    $this->posts = json_decode(file_get_contents($cache_file), true);
                }
            }
        }

        // Get posts if not done by cache yet
        if (empty($this->posts)) {
            $this->getMetaData();
        }
    }

    /**
     * Get metadata of all posts and save it in $this->posts
     */
    private function getMetaData(): void
    {
        $posts = [];

        // Go through all post files
        $path = C::Storage()->getDataPath() . DS . 'blog' . DS . 'posts';
        if (file_exists($path)) {
            $post_files = C::Storage()->scanDir($path, SCANDIR_SORT_DESCENDING);
            foreach ($post_files as $post_file) {
                // Load file
                /** @var MarkdownDocument $file */
                $file = C::Markdown()->fromFile($path . DS . $post_file);

                $yaml_data = $file->getYaml();

                // Ignore empty / invalid data
                if (empty($yaml_data)) {
                    continue;
                }

                $yaml_data['filename'] = $post_file;

                // Only add published posts
                if (!array_key_exists('published', $yaml_data) || !$yaml_data['published']) {
                    continue;
                }

                if (!C::Config()->inDebugMode()) {
                    // Hide published posts from the future on prod
                    try {
                        $postdate = Carbon::parse($yaml_data['date']);
                        if ($postdate->isFuture()) {
                            continue;
                        }
                    } catch (\Exception $e) {
                        // Ignore invalid dates
                    }
                }

                // Get thumbnail
                if (array_key_exists('thumbnail_filename', $yaml_data)) {
                    $thumbnail_filename = $yaml_data['thumbnail_filename'];
                } else {
                    $thumbnail_filename = str_replace(".md", ".jpg", $post_file);
                }

                $thumbnail = C::Storage()->getDataPath() . DS . 'blog' . DS . 'thumbnails' . DS . $thumbnail_filename;
                if (file_exists($thumbnail)) {
                    $yaml_data['thumbnail'] = C::Storage()->pathToUrl($thumbnail);
                } else {
                    $yaml_data['thumbnail'] = false;
                }

                // Hero image
                if (array_key_exists('hero_filename', $yaml_data)) {
                    $hero_filename = $yaml_data['hero_filename'];
                } else {
                    $hero_filename = str_replace(".jpg", "-hero.jpg", $thumbnail_filename);
                }

                $hero = C::Storage()->getDataPath() . DS . 'blog' . DS . 'thumbnails' . DS . $hero_filename;
                if (file_exists($hero)) {
                    $yaml_data['hero'] = C::Storage()->pathToUrl($hero);
                } else {
                    $yaml_data['hero'] = false;
                }

                $posts[$yaml_data['slug']] = $yaml_data;
            }
        }

        $this->posts = [
            'tags' => [],
            'categories' => [],
            'posts' => $posts,
        ];

        $this->setTagsAndCategories();
    }

    /**
     * Set $this->posts['tags'] and $this->posts['categories'] with content from available posts
     *
     * @return self
     */
    public function setTagsAndCategories(): self
    {
        $tags = [];
        $categories = [];

        foreach ($this->getAll() as $slug => $post) {
            if (array_key_exists('tags', $post) && is_array($post['tags'])) {
                foreach ($post['tags'] as $tag) {
                    if (array_key_exists($tag, $tags)) {
                        $tags[$tag]++;
                    } else {
                        $tags[$tag] = 1;
                    }
                }
            }
            if (array_key_exists('category', $post)) {
                if (array_key_exists($post['category'], $categories)) {
                    $categories[$post['category']]++;
                } else {
                    $categories[$post['category']] = 1;
                }
            }
        }

        $this->posts = [
            ...$this->posts,
            'categories' => $categories,
            'tags' => $tags,
        ];

        return $this;
    }

    /**
     * Get all tags
     *
     * Key: tag, Value: amount of posts with this tag
     *
     * @return array
     */
    public function getTags()
    {
        return $this->posts['tags'];
    }

    /**
     * Get all categories
     *
     * Key: category, Value: amount of posts with this category
     *
     * @return array|mixed
     */
    public function getCategories()
    {
        return $this->posts['categories'];
    }

    /**
     * Check if a post exists
     *
     * @param string $name doc name
     *
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists($name, $this->posts['posts']);
    }

    /**
     * Get data of a single post
     *
     * @param string $name post slug
     *
     * @return false|array
     */
    public function get($name)
    {
        if (array_key_exists($name, $this->posts['posts'])) {
            return $this->posts['posts'][$name];
        }

        return false;
    }

    /**
     * Get markdown content of a single post
     *
     * @param string $name post slug
     *
     * @return false|string Markdown text or false on error
     */
    public function getContent($name)
    {
        $path = C::Storage()->getDataPath() . DS . 'blog' . DS . 'posts';
        if (array_key_exists($name, $this->posts['posts'])) {
            $file = $path . DS . $this->posts['posts'][$name]['filename'];
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $parts = explode("---", $content);

                // Remove empty + yaml part
                array_shift($parts);
                array_shift($parts);

                // Return content
                return implode("---", $parts);
            }
        }

        return false;
    }

    /**
     * Get formatted HTML content of a single post
     *
     * @param string $name post slug
     *
     * @return false|string HTML content or false on error
     */
    public function getFormattedContent($name)
    {
        $content = $this->getContent($name);
        if ($content === false) {
            return false;
        }

        if (str_contains($content, '+---+')) {
            // Got card sections
            $content = $this->formatMarkdownCards($content);
        }

        $text = C::Markdown()->toHtml($content);

        // HTML improvements
        // TODO Those adjustments are for Bootstrap 5. Make this configurable and custom adjustable.
        $text = str_replace('class="h2"', 'class="h2 mt-4"', $text);
        $text = str_replace('class="h3"', 'class="h3 mt-4"', $text);
        $text = str_replace('class="h4"', 'class="h4 mt-4"', $text);
        $text = str_replace('class="h5"', 'class="h5 mt-4"', $text);
        $text = str_replace("<pre>", '<pre class="line-numbers">', $text);
        $text = str_replace("<code>", '<code class="language-shell">', $text);
        $text = str_replace("<table>", '<table class="table table-striped">', $text);
        $text = str_replace("<img src", '<img class="img-fluid" src', $text);

        // Set variables
        $text = str_replace("*ASSETS*", C::Router()->getBaseUrl() . '/data/blog/assets', $text);
        $text = str_replace("*BASEURL*", C::Router()->getBaseUrl(), $text);

        return $this->formatContent($text);
    }

    private function formatMarkdownCards(string $markdown): string
    {
        // Define the pattern to match the card structure in markdown with support for different line endings
        $pattern = '/\+---\+(\r\n|\r|\n)(.*?)(\r\n|\r|\n)\+---\+/s';

        // Define the replacement pattern using a callback to process inner content
        $replacement = function ($matches) {
            // Prepare the content of the card, escaping any HTML characters to avoid conflicts
            $cardContent = $matches[2];

            // Build the card HTML structure
            return "<div class=\"card\"><div class=\"card-body\" markdown=\"1\">{$cardContent}</div></div>";
        };

        // Execute the regex replacement with the callback
        $htmlContent = preg_replace_callback($pattern, $replacement, $markdown);

        // Return the transformed HTML content
        return $htmlContent ?: $markdown;
    }

    private function formatContent(string $content): string
    {
        $baseUrl = C::Router()->getBaseUrl();
        $content = preg_replace_callback(
            '/<a\s+([^>]*?\s*href=["\'])([^"\'>]+)(["\'][^>]*?)>/i',
            function ($matches) use ($baseUrl) {
                [$fullMatch, $preHref, $url, $postHref] = $matches;

                // Check if it's an external link.
                if (str_starts_with($url, $baseUrl) || str_starts_with($url, '/')) {
                    return $fullMatch;
                }

                // Check if "target" attribute already exists.
                if (str_contains($fullMatch, ' target=')) {
                    return preg_replace('/\s*target=["\'][^"\'>]+["\']/', ' target="_blank"', $fullMatch);
                }

                // Add "target='_blank'" to the link.
                return "<a ${preHref}${url}${postHref} target=\"_blank\">";
            },
            $content
        );


        return $content;
    }

    /**
     * Get all posts
     *
     * @return array
     */
    public function getAll()
    {
        return $this->posts['posts'];
    }

    /**
     * Get a page full of posts
     *
     * @param int    $page      wanted page
     * @param int    $per_page  amount of posts per page
     * @param string $order_by  order by this column
     * @param string $order_dir order dir (asc / desc)
     *
     * @return array|false
     */
    public function getPostsForPage($page, $per_page = 10, $order_by = 'date', $order_dir = 'desc')
    {
        $posts = $this->posts['posts'];
        C::Arrays()->sortByTwoKeys($posts, $order_by, 'title', strtolower($order_dir));

        if ($page == 1) {
            // Get first $per_page elements
            $skip = 0;
        } else if ($page > 1) {
            // Calculate and skip wanted amount
            $skip = ($page - 1) * $per_page;
        } else {
            // Invalid page!
            return false;
        }

        return array_splice($posts, $skip, $per_page);
    }

    /**
     * Get total amount of pages
     *
     * @param int $per_page amount of posts per page
     *
     * @return int
     */
    public function getTotalPages($per_page = 10): int
    {
        $total = count($this->posts['posts']);

        if ($total < $per_page) {
            return 1;
        }

        return (int)ceil($total / $per_page);
    }

    /**
     * Filter posts by a field
     *
     * This will remove all other posts in this instance.
     * If you need to work with all posts again, create
     * a new BlogPost object.
     *
     * @param string $field    name of field
     * @param string $operator how to compare? available: "=" extact match, "in" in array
     * @param mixed  $value    the value to compare to
     *
     * @return self
     */
    public function filter(string $field, string $operator, mixed $value): self
    {
        foreach ($this->posts['posts'] as $k => $post) {
            if ($operator == '=') {
                // Exact match
                if ($post[$field] != $value) {
                    unset($this->posts['posts'][$k]);
                }
            } else if ($operator == 'in') {
                // Array match
                if (!is_array($post[$field]) || !in_array($value, $post[$field])) {
                    unset($this->posts['posts'][$k]);
                }
            }
        }

        $this->setTagsAndCategories();

        return $this;
    }

    public static function backupComments(string $post_slug): void
    {
        $comments = C::Redis()->getClient()->hgetall('blog_post_comments_' . $post_slug);
        $arr = [];
        foreach ($comments as $k => $comment) {
            $arr[$k] = json_decode($comment, true);
        }

        if (count($arr) > 0) {
            $file = C::Storage()->getDataPath() . DS . 'blog' . DS . 'comments';
            C::Storage()->createDirectoriesIfNotExisting($file);
            $file .= DS . $post_slug . '.yaml';

            if (file_exists($file)) {
                unlink($file);
            }

            file_put_contents($file, Yaml::dump($arr));
        }
    }

    public static function restoreCommentsFromBackup(string $post_slug, $output = null): bool
    {
        if (!is_object($output)) {
            $output = new NullOutput();
        }

        $file = C::Storage()->getDataPath() . DS . 'blog' . DS . 'comments' . DS . $post_slug . '.yaml';
        if (file_exists($file)) {
            // First remove whole hash set so only restored comments will appear
            C::Redis()->getClient()->del('blog_post_comments_' . $post_slug);

            // Decode comments and load into redis
            $content = file_get_contents($file);
            $arr = Yaml::parse($content);
            foreach ($arr as $id => $comment) {
                $output->writeln('Loading comment: ' . $id);
                C::Redis()->getClient()->hset('blog_post_comments_' . $post_slug, $id, json_encode($comment));
            }
            return true;
        }

        return false;
    }

    public static function getRecommendedPostsFor(string $post_slug, int $amount = 3): array|bool
    {
        $user_lang = C::Formatter()->getLanguage();

        $x = new self;
        $post = $x->get($post_slug);

        if (!$post) {
            return false;
        }

        $blacklist = [$post_slug];

        $posts = [];

        // Add latest posts from same category, except this post
        $filtered = new self;
        $filtered->filter('category', '=', $post['category']);

        // Filter by language in any case
        if (in_array($user_lang, C::Config()->get('main:session.available_languages', []))) {
            $filtered->filter('language', '=', $user_lang);
            $x->filter('language', '=', $user_lang);
        }

        foreach ($filtered->getPostsForPage(1) as $filterpost) {
            if (!in_array($filterpost['slug'], $blacklist)) {
                $posts[] = $filterpost;
                $blacklist[] = $filterpost['slug'];
            }

            if (count($posts) >= $amount) {
                break;
            }
        }

        // If not enough: Add latest posts in general, except this post
        if (count($posts) < $amount) {
            foreach ($x->getPostsForPage(1) as $filterpost) {
                if (!in_array($filterpost['slug'], $blacklist)) {
                    $posts[] = $filterpost;
                    $blacklist[] = $filterpost['slug'];
                }

                if (count($posts) >= $amount) {
                    break;
                }
            }
        }

        return $posts;
    }

    public function getComments($post_slug): array
    {
        $comments = C::Redis()->getClient()->hgetall('blog_post_comments_' . $post_slug);

        // Format content arrays and only display approved comments
        $comments_arr = [];
        foreach ($comments as $comment) {
            $arr = json_decode($comment, true);
            if ($arr['approved']) {
                // Format content
                $pd = new \ParsedownExtra();
                $content = str_replace("<a href", '<a target="_blank" href', $pd->text($arr['msg']));
                $content = str_replace(['<h1', '<h2', '<h3'], '<h4', $content);
                $content = str_replace(['</h1', '</h2', '</h3'], '</h4', $content);

                // nl2br
                $content = str_replace("\n", '<br />', $content);

                $arr['html_content'] = $content;

                // URL formatting
                if (!empty($arr['website']) && !str_contains($arr['website'], 'http')) {
                    $arr['website'] = 'https://' . $arr['website'];
                }

                $comments_arr[] = $arr;
            }
        }

        // Sort to new -> old
        C::Arrays()->sortByTwoKeys($comments_arr, 'created_at', 'name');

        return $comments_arr;
    }

    /**
     * Add a new comment (data provided via request)
     *
     * @return bool
     */
    public function addComment(): bool
    {
        $post_slug = C::Request()->get('post');
        $name = C::Request()->get('name');
        $website = C::Request()->get('usersite');
        $email = C::Formatter()->sanitizeEmail(C::Request()->get('email'));
        $msg = C::Request()->get('msg');
        $honeypot = C::Request()->get('website');

        $submitted_token = C::Request()->get('token');
        $session_token = C::Session()->get('form_token');

        $bp = new BlogPost();
        $post = $bp->get($post_slug);

        // Sanitize message
        $msg = strip_tags($msg);
        $msg = trim($msg);

        // Spam detection: world edition
        // TODO Improve spam detection
        if (preg_match("/\p{Cyrillic}+/u", $msg)
            || preg_match("/\p{Han}+/u", $msg)) {
            // Got cyrillic / chinese characters!
            C::Logging()->info('[BLOG] Got foreign language, ignoring comment!');
            C::Guard()->saveWrongLoginAttempt();
            C::Session()->delete('form_token');
            return false;
        }

        // Honey pot + validation + csrf token check
        if (!empty($post_slug) && $post
            && !empty($name) && !empty($msg) && strlen($msg) > 6
            && filter_var($email, FILTER_VALIDATE_EMAIL)
            && empty($honeypot)
            && $submitted_token == $session_token
            && C::Guard()->getWrongLoginAttempts() < 20
        ) {
            // Got valid comment -> add
            $len = strlen($msg);
            if ($len > 5000) {
                $msg = mb_substr($msg, 0, 5000);
            }

            $id = C::Token()->createToken();

            $comment = [
                'name' => trim($name),
                'email' => trim($email),
                'website' => trim($website),
                'msg' => strip_tags($msg),
                'created_at' => Carbon::now()->toIso8601String(),
                'ip' => C::Request()->getIpAddress(),
                'approved' => false
            ];

            try {
                C::Redis()->getClient()->hset('blog_post_comments_' . $post_slug, $id, json_encode($comment));
            } catch(\RedisException $e) {
                // Comment could not be saved!
                return false;
            }

            // Notify admin via email
            // TODO Make this optional, add webhook option
            C::Mailman()->compose()
                ->addAddress(C::Config()->get('user:blog.comments_email.to'),
                    C::Config()->get('user:blog.comments_email.to_name'))
                ->setSubject(C::Config()->get('user:blog.comments_email.subject'))
                ->setTemplate('blogcomment', [
                    'comment' => $comment,
                    'id' => $id,
                    'post' => $post
                ], true)
                ->send();

            C::Session()->delete('form_token');
            return true;
        }

        // Empty fields or honeypot
        C::Logging()->info('[BLOG] Got invalid comment, ignoring!');
        C::Guard()->saveWrongLoginAttempt();
        C::Session()->delete('form_token');
        return false;
    }

    /**
     * Endpoint to moderate a comment
     *
     * Data provided via request.
     *
     * @return bool true on success, false on error
     */
    public function moderateComment(): bool
    {
        $id = C::Request()->get('id');
        $slug = C::Request()->get('slug');

        // TODO Add token or other better security

        // Wanted action: approve, remove, removeblock
        $action = C::Request()->get('action');

        $key = 'blog_post_comments_' . $slug;

        try {
            $comment = C::Redis()->getClient()->hget($key, $id);

            if (!empty($comment)) {
                $carr = json_decode($comment, true);

                if ($action == 'approve') {
                    $carr['approved'] = true;
                    C::Redis()->getClient()->hset($key, $id, json_encode($carr));
                    BlogPost::backupComments($slug);
                    return true;
                }

                if ($action == 'remove') {
                    C::Redis()->getClient()->hdel($key, $id);
                    BlogPost::backupComments($slug);
                    return true;
                }

                if ($action == 'removeblock') {
                    C::Redis()->getClient()->hdel($key, [$id]);
                    $blocked = C::Redis()->getClient()->get('blog_blocked_ips');
                    if (empty($blocked)) {
                        $blocked = '';
                    }
                    $blocked .= ';' . $carr['ip'];
                    C::Redis()->getClient()->set('blog_blocked_ips', $blocked);
                    BlogPost::backupComments($slug);
                    return true;
                }
            }

        } catch(\RedisException $e) {
            // Got redis error
            return false;
        }

        return false;
    }

}
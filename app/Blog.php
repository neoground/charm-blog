<?php
/**
 * This file contains the Charm kernel binding.
 */

namespace Neoground\Charm\Blog;

use Carbon\Carbon;
use Neoground\Charm\Blog\Models\BlogPost;
use Charm\Vivid\C;
use Charm\Vivid\Kernel\EngineManager;
use Charm\Vivid\Kernel\Interfaces\ModuleInterface;

/**
 * Class Blog
 *
 * Charm kernel binding
 */
class Blog extends EngineManager implements ModuleInterface
{
    /**
     * Load the module
     */
    public function loadModule()
    {
        // Nothing to do yet.
    }

    public function getFilteredPostList(string $type, string $name): array|bool
    {
        $page = C::Request()->get('page', 1);
        $per_page = 12;

        $name = urldecode($name);

        $b = new BlogPost();
        $b_all = new BlogPost();

        switch ($type) {
            case 'tag':
                $b->filter('tags', 'in', $name);
                break;
            case 'category':
                $b->filter('category', '=', $name);
                break;
        }

        // Add global filters (like language) for all datasets
        $user_lang = C::Formatter()->getLanguage();
        if (in_array($user_lang, C::Config()->get('main:session.available_languages', []))) {
            $b->filter('language', '=', $user_lang);
            $b_all->filter('language', '=', $user_lang);
        }

        if ($page < 1 || count($b->getAll()) < 1) {
            return false;
        }

        $posts = $b->getPostsForPage($page, $per_page);

        return [
            'tag' => $name,
            'category' => $name,
            'posts' => $posts,
            'pagination' => [
                'page' => $page,
                'total' => $b->getTotalPages($per_page)
            ],
            'categories' => $this->getFooterCats($b_all),
            'tags' => $this->getFooterTags($b_all),
        ];
    }

    public function getPost($name): array|bool
    {
        $b = new BlogPost();

        if (!$b->has($name)) {
            return false;
        }

        $post = $b->get($name);

        if (!$post) {
            return false;
        }

        $postdate = Carbon::parse($post['date']);

        if (!C::Config()->inDebugMode() && $postdate->isFuture()) {
            return false;
        }

        $token = C::Session()->get('form_token');
        if (!$token) {
            $token = C::Token()->createToken();
            C::Session()->set('form_token', $token);
        }

        $keywords = C::Config()->get('user:blog.base_keywords', '');
        if (array_key_exists('tags', $post) && count($post['tags']) > 0) {
            $keywords .= ' ' . implode(" ", $post['tags']);
        }

        // Filter posts for displaying
        $b_footer = new BlogPost();
        $user_lang = C::Formatter()->getLanguage();
        if (in_array($user_lang, C::Config()->get('main:session.available_languages', []))) {
            $b_footer->filter('language', '=', $user_lang);
        }

        return [
            'keywords' => $keywords,
            'post' => $post,
            'content' => $b->getFormattedContent($name),
            'categories' => $this->getFooterCats($b_footer),
            'tags' => $this->getFooterTags($b_footer),
            'form_token' => $token,
            'form_input' => C::Request()->getAllFromSession(),
            'recommended' => BlogPost::getRecommendedPostsFor($name),
        ];
    }

    /**
     * Get categories for footer
     *
     * @param BlogPost $b
     *
     * @return array
     */
    private function getFooterCats(BlogPost $b): array
    {
        $cats = $b->getCategories();
        arsort($cats);

        if (count($cats) > 5) {
            $cats = array_splice($cats, 0, 10);
        }

        return $cats;
    }

    /**
     * Get tags for footer
     *
     * @param BlogPost $b
     *
     * @return array
     */
    private function getFooterTags(BlogPost $b): array
    {
        $tags = $b->getTags();
        ksort($tags);
        arsort($tags);

        if (count($tags) > 5) {
            $tags = array_splice($tags, 0, 30);
        }

        return $tags;
    }

    /**
     * Get the path to the RSS feed XML file
     *
     * If the feed doesn't exist, it will be generated.
     * But it must be regenerated manually via
     * BlogPost::createRssFeed($lang).
     *
     * @param string $lang language of the RSS feed (e.g. "en" or "all" for all posts)
     *
     * @return string|false returns the path or false if $lang is invalid
     */
    public function getRssFeedPath(string $lang): string|bool
    {
        $available = C::Config()->get('user:blog.rss_versions');
        if (in_array($lang, $available)) {
            $xml_path = C::Storage()->getDataPath() . DS . 'feed_' . $lang . '.xml';

            if (!file_exists($xml_path)) {
                BlogPost::createRssFeed($lang);
            }

            return $xml_path;
        }

        return false;
    }

}
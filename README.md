# A Blog Module for Charm Framework 3.1+

This provides blog functionalities for Charm apps.

All blog posts are markdown files with YAML frontmatter. You can easily add thumbnails and hero images and
assets of all kind.

The whole blog engine runs flat-file, including comments handling. But you can also provide Redis
for better performance and caching.

## Features

- Create RSS feeds easily via the `RssFeed` class
- Easily access, filter, sort and paginate rss posts
- Saves blog posts metadata as file and in redis

## Installation

Begin your quest by adding charm-blog to your project via Composer:

```bash
composer require neoground/charm-blog
```

Next, install charm-markdown in your application:

```bash
bob cm:i neoground/charm-blog
```

If you haven't installed charm-markdown yet, install it as well:

```bash
bob cm:i neoground/charm-markdown
```

## Usage

Todo. This module is in early alpha. A usage guide will follow once we reach a beta state soon.

For a reference implementation see the [Markcoon](https://github.com/neoground/markcoon) project. 
It's a simple-to-use blog which uses this module under the hood.

### Configuration

Configuration in your app's `user.yaml`:

```yaml
rss:
  # Title, link, description can be multilingual, e.g. "description_de" for german
  title_en: My English Blog
  link_en: https://example.com/en/blog
  # Description of RSS feed
  description_en: Description of our blog
  # Absolute URL to blog index page
  blog_base_url: https://example.com/blog
  # Generator + Copyright tags
  generator: Charm Blog v1.0
  copyright: (c) ACME Corp - All rights reserved
  # Path to feed icon, relative to base URL
  image_relpath: icon.png
  # Prefix to add to each post slug for the guid
  guid_prefix: blog
```
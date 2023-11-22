# A Blog Module for Charm Framework 3.1+

This provides blog functionalities for Charm apps.

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

### Configuration

Configuration in your app's `user.yaml`:

```yaml
rss:
  # Title, link, description can be multilingual, e.g. "description_de" for german
  title_en: My English Blog
  link_en: https://example.com/en/blog
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
  # Description of RSS feed.
```
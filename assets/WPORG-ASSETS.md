# WordPress.org Plugin Directory Assets

Place the following image files in this directory.  They will be committed to
the SVN `assets/` directory (separate from `trunk/`) by the deploy workflow.

## Required files

| File                    | Size        | Purpose                                  |
|-------------------------|-------------|------------------------------------------|
| `banner-1544x500.png`   | 1544 × 500  | High-DPI banner (2×) shown on plugin page |
| `banner-772x250.png`    | 772 × 250   | Standard banner shown on plugin page      |
| `icon-256x256.png`      | 256 × 256   | High-DPI plugin icon (2×)                 |
| `icon-128x128.png`      | 128 × 128   | Standard plugin icon                      |

## Optional screenshot files

Screenshot filenames must match the numbered list in `readme.txt`.

| File                    | Corresponds to                           |
|-------------------------|------------------------------------------|
| `screenshot-1.png`      | Dashboard stats overview                 |
| `screenshot-2.png`      | Import Jobs management table             |
| `screenshot-3.png`      | Field Mapper with live API sample        |
| `screenshot-4.png`      | Two-Stage Config panel                   |
| `screenshot-5.png`      | Run Progress Monitor                     |
| `screenshot-6.png`      | Settings page (five sections)            |
| `screenshot-7.png`      | Vacancy Card Gutenberg block             |
| `screenshot-8.png`      | Elementor Vacancy Listing widget         |

## Notes

- PNG or JPG are both accepted.
- Maximum file size: 1 MB per image.
- SVG icons are **not** supported by WordPress.org.
- All images are hosted on the WordPress.org CDN; they are NOT included in
  the plugin download ZIP.

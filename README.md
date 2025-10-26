# Noah Affiliate

**Version:** 1.0.0  
**Author:** Mohamed Sawah  
**Website:** https://sawahsolutions.com

A comprehensive WordPress affiliate plugin designed for product review sites like Wirecutter and Good Housekeeping. Supports multiple affiliate networks with intelligent auto-linking, manual product insertion, click tracking, and beautiful product displays.

## Features

### 🌐 Multiple Affiliate Networks
- **Amazon Associates** - Full Product Advertising API 5.0 integration with multi-locale support (US, UK, DE, FR, IT, ES, CA, JP, AU, IN, BR, MX)
- **Awin** - Product feeds and deep linking
- **CJ (Commission Junction)** - Product search and tracking
- **Rakuten Advertising** - LinkShare API integration
- **Skimlinks/Sovrn Commerce** - Global auto-linking script

### 🎨 Product Display Options
- **Card Layout** - Full product cards with images, descriptions, pros/cons
- **Inline Layout** - Compact inline product mentions
- **Comparison Tables** - Side-by-side product comparisons
- **Custom Badges** - Best Overall, Best Budget, Best Premium, Editor's Choice
- **Light/Dark Mode** - Full SmartMag theme support with `.s-light` and `.s-dark` classes

### 🤖 Intelligent Auto-Linking
- Automatic keyword extraction from post content
- Smart product matching across all networks
- Contextual placement between paragraphs
- Configurable spacing and maximum products per post
- Background processing to avoid timeouts

### 🔗 Link Management
- **Pretty URLs** - Link cloaking with customizable slugs (e.g., `yoursite.com/go/product-name`)
- **301/302/307 Redirects** - Configurable redirect types
- **Multi-locale Support** - Different affiliate tags for different Amazon regions

### 📊 Analytics & Tracking
- Click tracking with date ranges
- Performance by network
- Top performing posts
- CSV export functionality
- Optional IP and user agent tracking
- Automatic data cleanup

### ✏️ Editor Integration
- **Classic Editor** - Full meta box with product search and management
- **Gutenberg Block** - Dedicated affiliate product block
- **Shortcodes** - `[noah_product]` and `[noah_comparison]`

### 🔄 Background Processing
- Product data refresh (24-hour cache)
- Automatic price updates
- Queue-based processing
- WP-Cron integration

## Installation

1. Upload the `noah-affiliate` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Noah Affiliate → Settings to configure your affiliate networks
4. Start adding products to your posts!

## Configuration

### General Settings

Navigate to **Noah Affiliate → Settings → General**:

- **Link Cloaking**: Enable pretty URLs for affiliate links
- **Link Slug**: Customize the URL slug (default: "go")
- **Redirect Type**: Choose 301, 302, or 307 redirects
- **Cache Duration**: How long to cache product data (default: 24 hours)
- **Click Tracking**: Enable/disable analytics
- **Data Retention**: Automatically delete old tracking data

### Network Configuration

#### Amazon Associates

1. Sign up at https://affiliate-program.amazon.com
2. Get PA-API credentials from https://webservices.amazon.com/paapi5/documentation/
3. In Noah Affiliate settings:
   - Enter your Access Key
   - Enter your Secret Key
   - Select your default locale
   - Add Associate Tags for each region you want to support
   - Click "Test Amazon API" to verify

#### Awin

1. Apply at https://www.awin.com
2. Request API access after approval
3. In Noah Affiliate settings:
   - Enter your Publisher ID
   - Enter your API Token
   - Click "Test Awin API"

#### CJ (Commission Junction)

1. Apply at https://www.cj.com
2. Get API credentials from your account
3. In Noah Affiliate settings:
   - Enter your Website ID
   - Enter your Personal Access Token
   - Click "Test CJ API"

#### Rakuten

1. Apply at https://rakutenadvertising.com
2. Get your SID and API credentials
3. In Noah Affiliate settings:
   - Enter your SID (Site ID)
   - Enter your API Token
   - Click "Test Rakuten API"

#### Skimlinks

1. Sign up at https://commerce.skimlinks.com
2. Get your Publisher ID
3. In Noah Affiliate settings:
   - Enter your Publisher ID
   - Select excluded post types (if any)

### Auto-Linking Configuration

Navigate to **Noah Affiliate → Settings → Auto-Linking**:

- **Enable Auto-Linking**: Turn on automatic product insertion
- **Post Types**: Select which post types should auto-link
- **Categories**: Choose specific categories (leave empty for all)
- **Max Products Per Post**: Limit the number of products (default: 5)
- **Minimum Paragraph Spacing**: Paragraphs between products (default: 3)

## Usage

### Manual Product Insertion (Classic Editor)

1. Edit or create a post
2. Find the "Affiliate Products" meta box
3. Select a network from the dropdown
4. Search for products
5. Click "Add" on desired products
6. Configure each product:
   - Choose layout (Card, Inline, Comparison)
   - Add a badge (optional)
   - Set custom title (optional)
7. Drag products to reorder
8. Publish or update your post

### Gutenberg Block

1. Add a new block
2. Search for "Affiliate Product"
3. Configure in the sidebar:
   - Select network
   - Enter product ID
   - Choose layout
   - Add badge (optional)
4. Publish your post

### Shortcodes

**Single Product:**
```
[noah_product id="B08N5WRWNW" network="amazon" layout="card"]
```

**Comparison Table:**
```
[noah_comparison ids="amazon:B08N5WRWNW,awin:12345,cj:67890"]
```

### Auto-Linking

Auto-linking works in the background:

1. Enable auto-linking in settings
2. Select post types and categories
3. Publish a new post
4. Noah Affiliate will:
   - Extract keywords from your content
   - Search for relevant products
   - Insert them contextually
   - Space them evenly throughout the post

## Best Practices

### For Product Review Posts

1. **Use Descriptive Titles**: Include product category in post title for better keyword extraction
2. **Structure Your Content**: Use H2/H3 headings like "Our Top Pick", "Best Budget Option"
3. **Manual + Auto**: Use manual insertion for hero products, auto-linking for related items
4. **Add Pros/Cons**: Enhance product cards with custom pros/cons lists
5. **Use Badges**: Highlight top picks with "Best Overall", "Editor's Choice", etc.

### For Link Cloaking

1. **Use Meaningful Slugs**: Custom slugs like "best-vacuum-2024" are better than random strings
2. **302 Redirects**: Use 302 for better tracking (not cached by browsers)
3. **Monitor Performance**: Check analytics regularly to optimize placements

### For Multi-Locale

1. **Set Up All Regions**: Add Associate Tags for all Amazon regions you target
2. **Test Each Locale**: Use the test connection button for each configuration
3. **Consider Audience**: Default to your primary audience's region

## Troubleshooting

### Products Not Displaying

- Check if the network is enabled in settings
- Verify API credentials with test connection
- Clear cache: Delete transients from database
- Check PHP error logs

### Auto-Linking Not Working

- Verify auto-linking is enabled
- Check post type and category settings
- Look for queue in **Noah Affiliate → Analytics**
- Ensure WP-Cron is functioning

### Tracking Not Recording

- Enable tracking in general settings
- Check JavaScript console for errors
- Verify AJAX endpoint is accessible
- Review privacy settings (IP/User Agent tracking)

### Amazon API Errors

- **"Access Denied"**: Check Access Key and Secret Key
- **"Invalid Associate Tag"**: Verify tag matches locale
- **"Too Many Requests"**: You've hit API rate limit, wait and retry
- **"ItemNotAccessible"**: Product may not be available in that region

## Developer Hooks

### Filters

```php
// Modify product data before display
add_filter('noah_affiliate_product_data', function($product_data, $network_id) {
    // Modify $product_data
    return $product_data;
}, 10, 2);

// Modify affiliate URL
add_filter('noah_affiliate_link_url', function($url, $product_id, $network) {
    // Modify $url
    return $url;
}, 10, 3);

// Customize product card template
add_filter('noah_affiliate_template_path', function($path, $template_name) {
    // Return custom template path
    return $path;
}, 10, 2);
```

### Actions

```php
// After product is added to post
add_action('noah_affiliate_product_added', function($post_id, $product_data) {
    // Do something
}, 10, 2);

// After click is tracked
add_action('noah_affiliate_click_tracked', function($click_id, $post_id, $product_id, $network) {
    // Do something
}, 10, 4);
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- MySQL 5.6 or higher

## Support

For support, please visit: https://sawahsolutions.com/support

## Changelog

### 1.0.0 (2024)
- Initial release
- Multi-network support (Amazon, Awin, CJ, Rakuten, Skimlinks)
- Intelligent auto-linking
- Product cards with multiple layouts
- Click tracking and analytics
- Link cloaking
- Gutenberg block
- Classic Editor integration
- SmartMag light/dark mode support
- Multi-locale Amazon support

## License

GPL v2 or later

## Credits

Developed by Mohamed Sawah for Sawah Solutions.

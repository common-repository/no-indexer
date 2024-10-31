<?php
/*
Plugin Name: No-Indexer
Plugin URI: https://filed.pro/no-indexer.html
Description: Add a checkbox in page, post, and custom type settings to instruct search engines not to index the content, excluding it from robots.txt and sitemap.
Version: 1.5
Author: Codeboy Rahul
Author URI: https://mondalrahul.github.io/portfolio
License: GPL-2.0
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/


function enqueue_custom_assets()
{
    wp_enqueue_style('custom-styles', plugin_dir_url(__FILE__) . 'assets/css/no-indexer-style.css', [], 'v0.1');
    wp_enqueue_script('custom-script', plugin_dir_url(__FILE__) . 'assets/js/no-indexer-script.js', [], 'v0.1', true);
}
add_action('admin_enqueue_scripts', 'enqueue_custom_assets');

// Add checkbox to page settings sidebar
add_action('add_meta_boxes', 'noindexer_add_page_meta_box');
function noindexer_add_page_meta_box()
{
    add_meta_box('noindexer_page_checkbox', 'No-indexer', 'noindexer_render_page_checkbox', ['page', 'post'], 'side', 'high');
}

function noindexer_render_page_checkbox($post)
{
    // Add nonce field
    wp_nonce_field('noindex_page_nonce', 'noindex_page_nonce');

    $noindex_page = get_post_meta($post->ID, '_noindex_page', true);

    $post_type = get_post_type(get_the_ID());
?>

    <div class="components-base-control components-checkbox-control">
        <div class="components-base-control__field">
            <span class="components-checkbox-control__input-container">
                <input id="noindex_page_checkbox" class="components-checkbox-control__input" name="noindex_page_checkbox" type="checkbox" <?php checked($noindex_page, 'on'); ?>>

                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" role="presentation" class="components-checkbox-control__checked" aria-hidden="true" focusable="false">
                    <path d="M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4L17.9 8z"></path>
                </svg>
            </span>
            <?php
            if ($post_type == 'page') {
            ?>
                <label class="components-checkbox-control__label" for="noindex_page_checkbox"><?php esc_attr_e('Noindex this page', 'noindexer'); ?></label>
            <?php
            } elseif ($post_type == 'post') {
            ?>
                <label class="components-checkbox-control__label" for="noindex_page_checkbox"><?php esc_attr_e('Noindex this post', 'noindexer'); ?></label>
            <?php
            } else {
            ?>
                <label class="components-checkbox-control__label" for="noindex_page_checkbox"><?php esc_attr_e('Noindex this page', 'noindexer'); ?></label>
            <?php
            }
            ?>
        </div>
    </div>


<?php
}

// Save checkbox value with nonce verification
add_action('save_post', 'noindexer_save_page_checkbox');
function noindexer_save_page_checkbox($post_id)
{
    // Check if nonce is set and valid
    if (!isset($_POST['noindex_page_nonce']) || !wp_verify_nonce(wp_unslash(sanitize_text_field($_POST['noindex_page_nonce'])), 'noindex_page_nonce')) {
        return;
    }

    // Proceed with saving checkbox value
    $checked_value = isset($_POST['noindex_page_checkbox']) ? sanitize_text_field($_POST['noindex_page_checkbox']) : 'off';
    update_post_meta($post_id, '_noindex_page', $checked_value);

    // Update robots.txt exclusion for the current page
    $robots_file = ABSPATH . 'robots.txt';
    $page_permalink = get_permalink($post_id);

    // Get current robots.txt content
    $robots_content = file_get_contents($robots_file);

    // Update robots.txt for the current page
    update_robots_file($page_permalink, $checked_value, $robots_content);

    // Write updated robots.txt content back to the file
    file_put_contents($robots_file, $robots_content);

    // Get the array of all checked pages
    $checked_pages = get_option('noindexer_checked_pages', array());

    if ($checked_value === 'on') {
        if (!in_array($post_id, $checked_pages)) {
            $checked_pages[] = $post_id;
        }
    } else {
        $key = array_search($post_id, $checked_pages);
        if ($key !== false) {
            unset($checked_pages[$key]);
        }
    }

    // Save the array of checked pages to wp_options as JSON
    update_option('noindexer_checked_pages', $checked_pages);
}

// Add noindex meta tag to specific pages
add_action('wp_head', 'noindexer_add_noindex_meta_tag');
function noindexer_add_noindex_meta_tag()
{
    // if (is_page()) {
    $noindex_page = get_post_meta(get_the_ID(), '_noindex_page', true);
    if ($noindex_page === 'on') {
        echo '<meta name="robots" content="noindex, follow" />' . PHP_EOL;
    }
    // }
}

// Exclude specific pages from sitemap
add_filter('wp_sitemaps_posts_entry', 'exclude_posts_from_sitemap', 10, 2);

function exclude_posts_from_sitemap($entry, $post)
{
    // Get the array of checked pages from wp_options
    $checked_pages = get_option('noindexer_checked_pages', array());

    // Check if the current page ID is in the array of checked pages
    if (in_array($post->ID, $checked_pages)) {
        // If the page is checked, return an empty array to exclude it from the sitemap
        return array();
    }

    // If the page is not checked, return the original $entry
    return $entry;
}


// Update robots.txt exclusion for each page
function update_robots_file($page_permalink, $checked_value, &$robots_content)
{
    // Check if the marker comments and the Disallow directive for the current page already exist
    $marker_exists = strpos($robots_content, "# START No-INDEXER") !== false;
    $page_disallow_exists = strpos($robots_content, "Disallow: $page_permalink") !== false;

    // If page is checked for exclusion and marker comments do not exist, add them along with the Disallow directive
    if ($checked_value === 'on') {
        if (!$marker_exists) {
            $robots_content .= "\n\n# START No-INDEXER\n# ---------------------------";
        }
        if (!$page_disallow_exists) {
            $robots_content .= "\nDisallow: $page_permalink";
        }
    } else {
        // If page is not checked for exclusion and marker comments exist, remove them along with the Disallow directive
        if ($page_disallow_exists) {
            $robots_content = str_replace("\nDisallow: $page_permalink", '', $robots_content);
        }
    }
}

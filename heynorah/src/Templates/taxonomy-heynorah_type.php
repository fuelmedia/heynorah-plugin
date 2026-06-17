<?php
/**
 * Template Name: Inventory Type Archive (React App Container)
 * Description: Archive template for heynorah_type taxonomy
 */

get_header();

// Get current term information
$current_term = get_queried_object();
$term_id = $current_term->term_id ?? 0;
$term_slug = $current_term->slug ?? '';
$term_name = $current_term->name ?? '';

// Get term meta (industry IDs from API)
$industry_id = get_term_meta($term_id, 'heynorah_industry_id', true);
$org_industry_id = get_term_meta($term_id, 'heynorah_org_industry_id', true);

// Fallback: if term meta is missing or only slug-like, infer industry ID from any post in this term
if (!is_string($industry_id)) {
    $industry_id = '';
}

if ($industry_id === '' || $industry_id === $term_slug) {
    $sample_posts = get_posts([
        'post_type' => 'heynorah_inventory',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'heynorah_type',
                'field' => 'term_id',
                'terms' => [$term_id],
            ],
        ],
    ]);

    if (!empty($sample_posts)) {
        $sample_industry_id = get_post_meta((int) $sample_posts[0], 'heynorah_industry_id', true);
        if (is_string($sample_industry_id) && $sample_industry_id !== '') {
            $industry_id = $sample_industry_id;
        }
    }
}

// Debug log
error_log("Taxonomy Template - Term ID: $term_id, Slug: $term_slug, Industry ID: $industry_id");

// Add term data to window object for React app
?>

<script type="text/javascript">
    window.heynorahTaxonomyContext = {
        termId: <?php echo esc_js($term_id); ?>,
        termSlug: '<?php echo esc_js($term_slug); ?>',
        termName: '<?php echo esc_js($term_name); ?>',
        industryId: '<?php echo esc_js($industry_id); ?>',
        orgIndustryId: '<?php echo esc_js($org_industry_id); ?>',
        isTaxonomyArchive: true
    };
</script>

<div id="heynorah-search-root" class="heynorah-frontend-container"
    data-taxonomy-slug="<?php echo esc_attr($term_slug); ?>" data-term-id="<?php echo esc_attr($term_id); ?>"></div>

<?php get_footer(); ?>

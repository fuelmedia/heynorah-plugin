<?php
/**
 * Template part: Related Inventory
 * Displays "You May Also Like" carousel with related inventory items
 *
 * @var int $post_id
 */

use HeyNorah\Core\Plugin;

$related_terms = get_the_terms($post_id, 'heynorah_type');
$related_term_ids = is_array($related_terms) ? wp_list_pluck($related_terms, 'term_id') : [];
$related_args = [
    'post_type' => get_post_type($post_id),
    'posts_per_page' => 8,
    'post__not_in' => [$post_id],
    'post_status' => 'publish',
];

if (!empty($related_term_ids)) {
    $related_args['tax_query'] = [
        [
            'taxonomy' => 'heynorah_type',
            'field' => 'term_id',
            'terms' => $related_term_ids,
        ],
    ];
}

$related_query = new WP_Query($related_args);
$cdn_base = trailingslashit(Plugin::CDN_BASE_URL);
$placeholder_image = trailingslashit(plugin_dir_url(dirname(__DIR__, 3))) . 'assets/dist/placeholder.jpg';

$resolve_media_url = static function (?string $path) use ($cdn_base): string {
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return $cdn_base . ltrim($path, '/');
};

$format_price = static function (?float $value): string {
    if ($value === null) {
        return '';
    }

    return '$' . number_format($value);
};

if ($related_query->have_posts()):
    ?>
    <section class="mb-24">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mt-16 pt-16 border-t border-gray-100 dark:border-zinc-800">
                <div class="flex justify-between items-center mb-8">
                    <h3 class="text-3xl font-light text-slate-900 dark:text-white mb-0">You may also like</h3>
                    <div class="flex gap-2">
                        <button type="button"
                            class="w-10 h-10 rounded-full border border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-800 flex items-center justify-center transition-colors embla-related__prev text-gray-600 dark:text-gray-300 cursor-pointer"><i
                                class="ti ti-arrow-left"></i></button>
                        <button type="button"
                            class="w-10 h-10 rounded-full border border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-800 flex items-center justify-center transition-colors embla-related__next text-gray-600 dark:text-gray-300 cursor-pointer"><i
                                class="ti ti-arrow-right"></i></button>
                    </div>
                </div>

                <div class="embla-related overflow-hidden">
                    <div class="embla__container flex">
                        <?php while ($related_query->have_posts()):
                            $related_query->the_post();
                            $rel_id = get_the_ID();
                            $rel_data = get_post_meta($rel_id, 'heynorah_raw_data', true);
                            if (!is_array($rel_data)) {
                                $rel_data = [];
                            }

                            $rel_images = is_array($rel_data['media']['images'] ?? null) ? $rel_data['media']['images'] : [];
                            $rel_img_url = '';

                            if (!empty($rel_images)) {
                                usort($rel_images, static function ($a, $b) {
                                    $a_featured = !empty($a['isFeatured']) ? 1 : 0;
                                    $b_featured = !empty($b['isFeatured']) ? 1 : 0;

                                    if ($a_featured !== $b_featured) {
                                        return $b_featured <=> $a_featured;
                                    }

                                    return ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0));
                                });

                                $first_image = $rel_images[0] ?? [];
                                $image_path = (string) (
                                    $first_image['sizes']['thumbnail'] ??
                                    ($first_image['sizes']['medium'] ??
                                        ($first_image['sizes']['large'] ??
                                            ($first_image['sizes']['original'] ?? '')))
                                );
                                $rel_img_url = $resolve_media_url($image_path);
                            }

                            if ($rel_img_url === '' && has_post_thumbnail($rel_id)) {
                                $rel_img_url = (string) get_the_post_thumbnail_url($rel_id, 'medium');
                            }
                            if ($rel_img_url === '') {
                                $rel_img_url = $placeholder_image;
                            }

                            $rel_title = (string) ($rel_data['title'] ?? get_the_title());
                            $rel_condition = (string) ($rel_data['condition'] ?? '');
                            $rel_sku = (string) ($rel_data['stockNumber'] ?? ($rel_data['sku'] ?? ''));

                            $rel_price_obj = is_array($rel_data['price'] ?? null) ? $rel_data['price'] : [];
                            $rel_hide_price = !empty($rel_price_obj['hidePrice']);
                            $rel_price_amount = is_numeric($rel_price_obj['amount'] ?? null) ? (float) $rel_price_obj['amount'] : null;
                            $rel_effective_price = is_numeric($rel_price_obj['effectivePrice'] ?? null) ? (float) $rel_price_obj['effectivePrice'] : null;
                            $rel_sale_amount = is_numeric($rel_price_obj['saleAmount'] ?? null) ? (float) $rel_price_obj['saleAmount'] : null;
                            $rel_msrp = is_numeric($rel_price_obj['msrp'] ?? null) ? (float) $rel_price_obj['msrp'] : null;
                            $rel_base_price = $rel_effective_price ?? $rel_price_amount;
                            $rel_has_discount = !$rel_hide_price && $rel_sale_amount !== null && $rel_base_price !== null && $rel_sale_amount < $rel_base_price;

                            $discount_percent = 0;
                            if ($rel_has_discount && $rel_base_price > 0) {
                                $discount_percent = (int) round((($rel_base_price - $rel_sale_amount) / $rel_base_price) * 100);
                            }

                            $location_label = trim((string) (
                                $rel_data['location']['title'] ??
                                ($rel_data['locationTitle'] ??
                                    ($rel_data['location']['name'] ??
                                        ($rel_data['locationName'] ??
                                            implode(', ', array_filter([
                                                (string) ($rel_data['location']['city'] ?? ''),
                                                (string) ($rel_data['location']['state'] ?? ''),
                                            ])))))
                            ));
                            if ($location_label === '') {
                                $location_label = 'Unknown location';
                            }

                            $display_price = $rel_base_price;
                            if ($rel_has_discount) {
                                $display_price = $rel_sale_amount;
                            }
                            ?>
                            <div
                                class="embla__slide flex-[0_0_100%] sm:flex-[0_0_50%] lg:flex-[0_0_33.3333%] xl:flex-[0_0_25%] pr-4">
                                <a href="<?php the_permalink(); ?>" class="block h-full text-inherit !no-underline group">
                                    <article
                                        class="relative rounded-sm overflow-hidden border border-border bg-card text-card-foreground h-full flex flex-col">
                                        <div class="aspect-video bg-muted overflow-hidden">
                                            <img src="<?php echo esc_url($rel_img_url); ?>" class="w-full h-full object-cover"
                                                alt="<?php echo esc_attr($rel_title); ?>">
                                        </div>

                                        <div class="absolute top-2 left-2 lg:top-4 lg:left-4 flex items-center gap-2">
                                            <?php if ($rel_condition !== ''): ?>
                                                <span
                                                    class="inline-flex items-center rounded-4xl border border-transparent bg-primary text-primary-foreground px-2 py-0.5 text-[10px] lg:text-xs font-medium uppercase tracking-wide"><?php echo esc_html($rel_condition); ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="absolute top-2 right-2 lg:top-4 lg:right-4 flex items-center gap-2">
                                            <?php if ($discount_percent > 0): ?>
                                                <span
                                                    class="inline-flex items-center rounded-4xl border border-transparent bg-destructive text-destructive-foreground px-2 py-0.5 text-[10px] lg:text-xs font-medium uppercase tracking-wide">-<?php echo esc_html((string) $discount_percent); ?>%</span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="flex flex-1 flex-col gap-2 lg:gap-4 py-2 lg:py-4">
                                            <header class="px-2 lg:px-6">
                                                <h4
                                                    class="text-sm lg:text-xl font-medium leading-normal line-clamp-2 text-card-foreground mb-2">
                                                    <?php echo esc_html($rel_title); ?></h4>
                                                <?php if ($rel_sku !== ''): ?>
                                                    <div class="text-muted-foreground text-sm">
                                                        <span
                                                            class="inline-flex items-center gap-1 rounded-4xl border border-border bg-background/80 text-foreground px-2 py-0.5 text-[10px] lg:text-xs font-medium">
                                                            <i class="ti ti-hash text-xs"></i>
                                                            <?php echo esc_html($rel_sku); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </header>

                                            <div class="px-2 lg:px-6 flex flex-col gap-2 lg:gap-3">
                                                <div class="flex items-baseline gap-2 lg:gap-3">
                                                    <div class="flex flex-col gap-1 lg:gap-3">
                                                        <?php if ($rel_hide_price): ?>
                                                            <div>
                                                                <span class="text-base lg:text-xl font-semibold">Call for
                                                                    Price</span>
                                                            </div>
                                                        <?php elseif ($rel_has_discount && $display_price !== null && $rel_base_price !== null): ?>
                                                            <div class="flex flex-col">
                                                                <span
                                                                    class="text-xs lg:text-sm font-normal uppercase text-muted-foreground">Sale
                                                                    Price</span>
                                                                <span
                                                                    class="text-base lg:text-xl font-semibold"><?php echo esc_html($format_price($display_price)); ?></span>
                                                                <span
                                                                    class="text-xs lg:text-sm font-semibold line-through"><?php echo esc_html($format_price($rel_base_price)); ?></span>
                                                            </div>
                                                        <?php elseif ($display_price !== null): ?>
                                                            <div class="flex flex-col">
                                                                <span class="text-xs lg:text-sm font-medium uppercase">Our
                                                                    Price</span>
                                                                <span
                                                                    class="text-base lg:text-xl font-semibold"><?php echo esc_html($format_price($display_price)); ?></span>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="flex flex-col">
                                                                <span class="text-xs lg:text-sm font-medium uppercase">Price</span>
                                                                <span class="text-base lg:text-xl font-semibold">Contact for
                                                                    details</span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!$rel_hide_price && $rel_msrp !== null): ?>
                                                        <div class="flex flex-col">
                                                            <span
                                                                class="text-xs lg:text-sm font-normal uppercase text-muted-foreground">MSRP</span>
                                                            <span
                                                                class="text-xs lg:text-sm font-semibold"><?php echo esc_html($format_price($rel_msrp)); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="mt-auto h-px bg-border w-full"></div>
                                            <footer class="px-2 lg:px-6 flex items-center justify-between gap-2 lg:gap-3">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <i class="ti ti-map-pin text-sm lg:text-base text-muted-foreground"></i>
                                                    <span
                                                        class="text-xs lg:text-sm font-medium line-clamp-1"><?php echo esc_html($location_label); ?></span>
                                                </div>
                                                <i
                                                    class="ti ti-arrow-up-right text-base lg:text-xl transition-transform duration-200 group-hover:-rotate-45"></i>
                                            </footer>
                                        </div>
                                    </article>
                                </a>
                            </div>
                        <?php endwhile;
                        wp_reset_postdata(); ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="mb-24">
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
        <h6 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-2">Disclaimer</h6>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2 leading-relaxed">
            <?php if (!empty($stockNumber)): ?>
                Vessel ID# <?php echo esc_html($stockNumber); ?>
            <?php endif; ?>
            <?php if (!empty($updatedAt)): ?>
                <?php if (!empty($stockNumber)): ?> | <?php endif; ?>
                Last Updated:
                <?php
                $updated_ts = strtotime((string) $updatedAt);
                echo esc_html($updated_ts ? date('m-d-Y g:i A', $updated_ts) : (string) $updatedAt);
                ?>
            <?php endif; ?>
        </p>
        <?php if (!empty($disclaimer)): ?>
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-2 leading-relaxed">
                <?php echo wp_kses_post($disclaimer); ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
/**
 * Template part: Hero Quick Info
 * Displays quick info card with brand, title, specs, price, and CTAs
 *
 * @var int $post_id
 * @var string $brand_name
 * @var string $brand_slug
 * @var string $stockNumber
 * @var string $title
 * @var string $short_description
 * @var int $total_engine_power
 * @var string $engine_power_unit
 * @var int $length_ft
 * @var int $length_in
 * @var int $beam_ft
 * @var int $beam_in
 * @var float $price_msrp
 * @var float $display_price
 * @var float $price_savings
 * @var bool $price_hide
 */
?>

<!-- Quick Info Card -->
<div class="w-full max-w-lg mx-auto">
    <?php
    // Get taxonomy for brand URL
    $inventory_types = wp_get_post_terms($post_id, 'heynorah_type', ['fields' => 'slugs']);
    $taxonomy_slug = !empty($inventory_types) ? $inventory_types[0] : 'marine';

    // Build brand URL: /inventory/{taxonomy}/{brand-slug}
    $brand_url = home_url('/inventory/' . $taxonomy_slug . '/brand-' . $brand_slug);
    ?>
    <div class="flex justify-between items-start mb-6">
        <a href="<?php echo esc_url($brand_url); ?>"
            class="uppercase tracking-wider text-xs font-semibold text-zinc-400 hover:text-white transition-colors"><?php echo esc_html($brand_name); ?></a>
        <?php if (!empty($stockNumber)): ?>
            <span class="uppercase tracking-wider text-xs font-semibold text-zinc-500">Stock ID:
                <?php echo esc_html($stockNumber); ?></span>
        <?php endif; ?>
    </div>

    <h1 class="text-3xl lg:text-4xl xl:text-5xl font-sans font-medium mb-6 leading-tight">
        <?php echo esc_html($title); ?>
    </h1>

    <?php if (!empty($short_description)): ?>
        <p class="text-zinc-400 text-sm leading-relaxed mb-8 border-l-2 border-zinc-800 pl-4">
            <?php echo wp_trim_words($short_description, 20); ?>
        </p>
    <?php endif; ?>

    <div class="grid grid-cols-3 gap-y-4 gap-x-8 py-6 border-t border-b border-zinc-800 mb-8">
        <div class="flex items-start gap-3">
            <i class="ti ti-engine text-white text-2xl mt-0.5"></i>
            <div>
                <div class="text-xs text-zinc-500 uppercase tracking-wide">Power</div>
                <div class="font-bold text-sm">
                    <?php echo esc_html($total_engine_power . ' ' . $engine_power_unit); ?>
                </div>
            </div>
        </div>
        <div class="flex items-start gap-3">
            <i class="ti ti-ruler-2 text-white text-2xl mt-0.5"></i>
            <div>
                <div class="text-xs text-zinc-500 uppercase tracking-wide">Length</div>
                <div class="font-bold text-sm">
                    <?php echo format_feet_inches($length_ft, $length_in); ?>
                </div>
            </div>
        </div>
        <div class="flex items-start gap-3">
            <i class="ti ti-ruler-measure text-white text-2xl mt-0.5"></i>
            <div>
                <div class="text-xs text-zinc-500 uppercase tracking-wide">Beam</div>
                <div class="font-bold text-sm"><?php echo format_feet_inches($beam_ft, $beam_in); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-row gap-3 mb-8">
        <?php if (!$price_hide && !empty($price_msrp) && $price_savings > 0): ?>
            <div class="flex flex-col items-baseline justify-between mb-1">
                <span class="text-zinc-500 text-sm uppercase font-semibold">MSRP</span>
                <span class="text-zinc-500 line-through text-lg font-bold">$<?php echo number_format($price_msrp); ?></span>
            </div>
        <?php endif; ?>
        <div class="flex flex-col items-baseline justify-between">
            <span
                class="text-zinc-500 text-sm uppercase font-semibold"><?php echo !$price_hide && $price_savings > 0 ? 'Our Price' : 'Price'; ?></span>
            <div class="flex items-center gap-3">
                <?php if ($price_hide): ?>
                    <span class="text-white text-3xl font-bold font-sans">Contact for Price</span>
                <?php else: ?>
                    <span
                        class="text-white text-3xl font-bold font-sans">$<?php echo number_format($display_price); ?></span>
                <?php endif; ?>
                <?php if (!$price_hide && $price_savings > 0): ?>
                    <span
                        class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-semibold px-2 py-1 rounded">SAVE
                        $<?php echo number_format($price_savings); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-4">
        <a href="#modal-request-info" data-fancybox
            class="text-white  bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-3 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800 flex-1 text-center !no-underline">
            Request Info
        </a>
        <a href="#modal-financing" data-fancybox
            class="py-2.5 px-3 text-sm font-medium !text-black focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700 flex-1 text-center !no-underline">
            Financing
        </a>
    </div>
</div>

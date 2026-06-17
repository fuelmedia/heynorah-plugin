<?php
/**
 * Template part: Meta Badges
 * Displays general information badges
 * 
 * @var string $brand_name
 * @var string $model_name
 * @var int $year
 * @var string $category
 * @var string $type_name
 * @var string $stock_number
 * @var string $condition
 * @var bool $local_delivery
 * @var float $price_amount
 * @var float $price_sale
 * @var float $price_msrp
 * @var bool $price_hide
 * @var string $broker_name
 * @var string $broker_phone
 * @var string $location_formatted
 * @var string $hull_type
 * @var string $hull_material
 * @var string $keel_type
 * @var string $hin
 * @var string $boat_name
 * @var string $builder
 * @var string $designer
 * @var string $hull_warranty
 * @var string $warranty_until
 */

// Define general information badges with icons
$general_badges = [
    ['label' => 'Make', 'value' => $brand_name ?? null, 'icon' => 'ti-brand-toyota'],
    ['label' => 'Model', 'value' => $model_name ?? null, 'icon' => 'ti-car'],
    ['label' => 'Year', 'value' => $year ?? null, 'icon' => 'ti-calendar'],
    ['label' => 'Category', 'value' => $category ?? null, 'icon' => 'ti-category'],
    ['label' => 'Type', 'value' => $type_name ?? null, 'icon' => 'ti-tag'],
    ['label' => 'Stock Number', 'value' => $stock_number ?? null, 'icon' => 'ti-barcode'],
    ['label' => 'Condition', 'value' => $condition ?? null, 'icon' => 'ti-certificate'],
    ['label' => 'Local Delivery', 'value' => isset($local_delivery) && $local_delivery ? 'Yes' : null, 'icon' => 'ti-truck-delivery'],
    ['label' => 'Price', 'value' => !empty($price_hide) ? 'Contact for Price' : (!empty($price_amount) ? '$' . number_format($price_amount) : null), 'icon' => 'ti-currency-dollar'],
    ['label' => 'Sale Price', 'value' => empty($price_hide) && !empty($price_sale) ? '$' . number_format($price_sale) : null, 'icon' => 'ti-discount'],
    ['label' => 'MSRP', 'value' => empty($price_hide) && !empty($price_msrp) ? '$' . number_format($price_msrp) : null, 'icon' => 'ti-receipt'],
    ['label' => 'Broker', 'value' => $broker_name ?? null, 'icon' => 'ti-user-star'],
    ['label' => 'Location', 'value' => $location_formatted ?? null, 'icon' => 'ti-map-pin'],
    ['label' => 'Warranty Until', 'value' => !empty($warranty_until) ? date('M j, Y', strtotime($warranty_until)) : null, 'icon' => 'ti-calendar-event'],
];

// Filter out empty badges
$visible_badges = array_filter($general_badges, function($badge) {
    return !empty($badge['value']);
});
?>

<div class="flex flex-wrap gap-3 mb-12">
    <?php foreach ($visible_badges as $badge): ?>
        <div class="flex items-center gap-3 px-4 py-3 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800">
            <i class="ti <?php echo esc_attr($badge['icon']); ?> text-lg text-zinc-400"></i>
            <div class="flex flex-col">
                <span class="text-xs font-medium text-zinc-500 uppercase tracking-[.05em]"><?php echo esc_html($badge['label']); ?></span>
                <span class="text-base font-medium text-zinc-900 dark:text-white"><?php echo esc_html($badge['value']); ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
/**
 * Template part: Modal Print
 * Printable "Window Card" layout for inventory items
 *
 * @var int $post_id
 */

use HeyNorah\Core\Plugin;

$raw_title = is_array($raw_data ?? null) ? (string) ($raw_data['title'] ?? '') : '';
$print_title = $raw_title !== '' ? $raw_title : (string) ($title ?? get_the_title());
$print_permalink = get_permalink($post_id);
$print_stock = (string) ($stock_number ?? ($stockNumber ?? ''));
$print_location = (string) ($location_formatted ?? '');
$print_category = (string) ($category ?? ($type_name ?? ''));

$print_price_text = 'Contact for Price';
if (empty($price_hide) && !empty($display_price)) {
    $print_price_text = '$' . number_format((float) $display_price);
    if (!empty($price_savings)) {
        $print_price_text .= ' (Savings: $' . number_format((float) $price_savings) . ')';
    }
}

$print_image_url = '';
if (!empty($images) && is_array($images)) {
    $print_images = $images;
    usort($print_images, static function ($a, $b) {
        return (!empty($b['isFeatured']) ? 1 : 0) <=> (!empty($a['isFeatured']) ? 1 : 0);
    });

    $selected_image = $print_images[0] ?? [];
    $image_path = (string) ($selected_image['sizes']['large'] ?? ($selected_image['sizes']['original'] ?? ($selected_image['sizes']['medium'] ?? '')));
    if ($image_path !== '') {
        if (preg_match('#^https?://#i', $image_path)) {
            $print_image_url = $image_path;
        } else {
            $print_image_url = trailingslashit(Plugin::CDN_BASE_URL) . ltrim($image_path, '/');
        }
    }
}

$overview_text = trim((string) ($short_description ?? ''));
if ($overview_text === '') {
    $overview_text = trim(wp_strip_all_tags((string) ($description ?? '')));
}
if ($overview_text === '') {
    $overview_text = 'No overview available.';
}

$listify = static function ($value): array {
    if (is_array($value)) {
        return array_values(array_filter(array_map(static function ($item) {
            return is_scalar($item) ? trim((string) $item) : '';
        }, $value)));
    }

    $string_value = trim((string) $value);
    if ($string_value === '') {
        return [];
    }

    $parts = preg_split('/[\r\n,;]+/', $string_value) ?: [];
    return array_values(array_filter(array_map('trim', $parts)));
};

$detailed_feature_groups = [];
if (!empty($key_features) && is_array($key_features)) {
    $feature_lines = $listify($key_features);
    if (!empty($feature_lines)) {
        $detailed_feature_groups['Highlights'] = $feature_lines;
    }
}

$equipment_lines = $listify($additional_equipment ?? '');
if (!empty($equipment_lines)) {
    $detailed_feature_groups['Equipment'] = $equipment_lines;
}

$performance_lines = [];
if (!empty($engine_1_make) || !empty($engine_1_model) || !empty($total_engine_power)) {
    $engine_line = trim(implode(' ', array_filter([
        $engine_1_make,
        $engine_1_model,
        !empty($total_engine_power) ? '(' . number_format((float) $total_engine_power) . ' ' . ($engine_power_unit ?? 'HP') . ')' : '',
    ])));
    if ($engine_line !== '') {
        $performance_lines[] = 'Engine: ' . $engine_line;
    }
}
if (!empty($fuel_type)) {
    $performance_lines[] = 'Fuel Type: ' . $fuel_type;
}
if (!empty($engine_1_hours)) {
    $performance_lines[] = 'Engine Hours: ' . $engine_1_hours;
}
if (!empty($performance_lines)) {
    $detailed_feature_groups['Performance'] = $performance_lines;
}

$key_specs = [
    'Engine' => trim(implode(' ', array_filter([$engine_1_make, $engine_1_model]))),
    'Category' => $print_category,
    'Fuel Type' => (string) ($fuel_type ?? ''),
    'Hours' => (string) ($engine_1_hours ?? ''),
    'Power' => !empty($total_engine_power) ? number_format((float) $total_engine_power) . ' ' . ($engine_power_unit ?? 'HP') : '',
    'Hull Material' => (string) ($hull_material ?? ''),
    'Builder' => (string) ($builder ?? ''),
    'Location' => $print_location,
    'Condition' => (string) ($condition ?? ''),
];
$key_specs = array_filter($key_specs, static fn($value) => trim((string) $value) !== '');
$key_specs = array_slice($key_specs, 0, 6, true);

$technical_specs = [
    'Year' => !empty($year) ? (string) $year : '',
    'Make' => (string) ($brand_name ?? ''),
    'Model' => (string) ($model_name ?? ''),
    'Stock Number' => $print_stock,
    'Length' => function_exists('format_feet_inches') ? format_feet_inches($length_ft ?? 0, $length_in ?? 0) : '',
    'Beam' => function_exists('format_feet_inches') ? format_feet_inches($beam_ft ?? 0, $beam_in ?? 0) : '',
    'Draft' => function_exists('format_feet_inches') ? format_feet_inches($max_draft_ft ?? 0, $max_draft_in ?? 0) : '',
    'Weight' => !empty($dry_weight) ? number_format((float) $dry_weight) . ' ' . ($dry_weight_unit ?: 'lb') : '',
    'HIN' => (string) ($hin ?? ''),
    'Engine Make' => (string) ($engine_1_make ?? ''),
    'Engine Model' => (string) ($engine_1_model ?? ''),
    'Power' => !empty($total_engine_power) ? number_format((float) $total_engine_power) . ' ' . ($engine_power_unit ?? 'HP') : '',
    'Fuel Capacity' => !empty($fuel_capacity) ? (string) $fuel_capacity : '',
];
$technical_specs = array_filter($technical_specs, static fn($value) => trim((string) $value) !== '' && trim((string) $value) !== '--');
$technical_specs = array_slice($technical_specs, 0, 12, true);

$additional_info_parts = [];
if (!empty($disclaimer)) {
    $additional_info_parts[] = trim((string) $disclaimer);
}
if (!empty($location_name) || !empty($location_address)) {
    $additional_info_parts[] = trim(implode(' - ', array_filter([(string) ($location_name ?? ''), (string) ($location_address ?? '')])));
}
if (!empty($broker_name) || !empty($broker_email) || !empty($broker_phone)) {
    $additional_info_parts[] = trim(implode(' | ', array_filter([(string) ($broker_name ?? ''), (string) ($broker_phone ?? ''), (string) ($broker_email ?? '')])));
}
if (!empty($documents) && is_array($documents)) {
    $additional_info_parts[] = 'Documents available: ' . count($documents);
}
$additional_info_text = trim(implode(' ', array_filter($additional_info_parts)));
if ($additional_info_text === '') {
    $additional_info_text = 'Contact us for complete equipment list and full details.';
}

$company_name = get_bloginfo('name');
$footer_line = implode(' | ', array_filter([
    (string) ($location_address ?? ''),
    (string) ($broker_phone ?? ($location_phone ?? '')),
    (string) ($broker_email ?? ($location_email ?? '')),
    preg_replace('#^https?://#i', '', home_url('/')),
]));
?>

<div style="display: none; min-width: 800px; max-width: 1024px; width: 100%;" id="modal-print" class="bg-white p-0">
    <div id="printable-area" class="p-8 border-[5px] border-gray-800 m-2" style="border: 5px solid #333;">

        <div class="flex justify-between items-center mb-6 border-b border-black pb-4">
            <div class="pr-4">
                <h1 class="font-bold mb-1 text-4xl text-black"><?php echo esc_html($print_title); ?></h1>
                <p class="text-gray-600 text-sm mb-0"><?php echo esc_html(trim(implode(' | ', array_filter([$print_location, $print_price_text])))); ?></p>
            </div>
            <div class="text-right shrink-0">
                <h4 class="font-bold uppercase mb-0 text-xl text-black">Window Card</h4>
                <?php if ($print_stock !== ''): ?>
                    <small style="font-size: 0.7rem;" class="text-gray-500">Stock #<?php echo esc_html($print_stock); ?></small>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-8">
            <div class="col-span-7">
                <?php if ($print_image_url !== ''): ?>
                    <img src="<?php echo esc_url($print_image_url); ?>"
                        class="w-full rounded mb-6 object-cover" style="max-height: 350px;" alt="<?php echo esc_attr($print_title); ?>">
                <?php else: ?>
                    <div class="w-full rounded mb-6 flex items-center justify-center bg-gray-100 text-gray-400" style="height: 280px;">
                        No image available
                    </div>
                <?php endif; ?>

                <div class="mb-6">
                    <h6 class="font-bold border-b border-gray-300 pb-1 mb-2 text-black">Overview</h6>
                    <p class="text-gray-600 text-sm mb-0" style="font-size: 0.8rem;"><?php echo esc_html($overview_text); ?></p>
                </div>

                <div class="mb-0">
                    <h6 class="font-bold border-b border-gray-300 pb-1 mb-2 text-black">Detailed Features</h6>
                    <?php if (!empty($detailed_feature_groups)): ?>
                        <?php foreach ($detailed_feature_groups as $group_title => $group_items): ?>
                            <div class="mb-3">
                                <h6 class="font-bold uppercase mb-1 text-black" style="font-size: 0.75rem;"><?php echo esc_html($group_title); ?></h6>
                                <ul class="text-gray-600 text-sm mb-0 pl-4 list-disc" style="font-size: 0.8rem;">
                                    <?php foreach ($group_items as $group_item): ?>
                                        <li><?php echo esc_html($group_item); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-600 text-sm mb-0" style="font-size: 0.8rem;">No detailed features available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-span-5 flex flex-col">
                <h6 class="font-bold border-b border-gray-300 pb-1 mb-2 text-black">Key Specs</h6>
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <?php if (!empty($key_specs)): ?>
                        <?php foreach ($key_specs as $spec_label => $spec_value): ?>
                            <div class="<?php echo (strlen((string) $spec_value) > 24) ? 'col-span-2' : 'col-span-1'; ?>">
                                <div class="border border-gray-200 rounded px-3 py-2 bg-gray-50 h-full">
                                    <small class="block uppercase text-gray-500 font-bold" style="font-size: 0.65rem;"><?php echo esc_html($spec_label); ?></small>
                                    <span style="font-size: 0.8rem;" class="text-black"><?php echo esc_html((string) $spec_value); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-2">
                            <div class="border border-gray-200 rounded px-3 py-2 bg-gray-50 h-full">
                                <span style="font-size: 0.8rem;" class="text-black">No key specs available.</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <h6 class="font-bold border-b border-gray-300 pb-1 mb-2 text-black">Technical Specifications</h6>
                <ul class="list-none mb-6" style="font-size: 0.8rem;">
                    <?php if (!empty($technical_specs)): ?>
                        <?php foreach ($technical_specs as $spec_label => $spec_value): ?>
                            <li class="mb-2 flex justify-between border-b border-dashed border-gray-200 pb-1 text-black gap-3">
                                <strong><?php echo esc_html($spec_label); ?>:</strong>
                                <span class="text-right"><?php echo esc_html((string) $spec_value); ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="mb-2 text-black">No technical specifications available.</li>
                    <?php endif; ?>
                </ul>

                <h6 class="font-bold border-b border-gray-300 pb-1 mb-2 text-black">Additional Info</h6>
                <p class="text-gray-600 text-sm mb-6" style="font-size: 0.8rem;"><?php echo esc_html($additional_info_text); ?></p>

                <div class="mt-auto p-4 bg-gray-50 border border-gray-200 rounded text-center">
                    <h6 class="font-bold mb-2 text-black" style="font-size: 0.9rem;">Scan for Details</h6>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($print_permalink); ?>"
                        alt="QR Code" class="h-auto mx-auto" style="width: 100px;">
                </div>
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-black text-center">
            <h5 class="font-bold text-lg mb-0 text-black"><?php echo esc_html($company_name); ?></h5>
            <?php if ($footer_line !== ''): ?>
                <p class="text-xs text-gray-500 mb-0"><?php echo esc_html($footer_line); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="text-center pb-8 print:hidden">
        <button onclick="printDiv('printable-area')"
            class="bg-black hover:bg-gray-800 text-white font-bold py-3 px-6 rounded inline-flex items-center transition-colors"><i
                class="ti ti-printer mr-2"></i>
            Print Window Card</button>
    </div>
</div>

<script>
    if (typeof window.printDiv !== "function") {
        window.printDiv = function (elementId) {
            const printArea = document.getElementById(elementId);
            if (!printArea) return;

            const printWindow = window.open("", "_blank", "width=1024,height=900");
            if (!printWindow) return;

            const styleTags = Array.from(document.querySelectorAll("style, link[rel='stylesheet']"))
                .map((tag) => tag.outerHTML)
                .join("");

            printWindow.document.open();
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Window Card</title>
                        ${styleTags}
                        <style>
                            body { margin: 0; padding: 20px; background: #fff; }
                        </style>
                    </head>
                    <body>${printArea.outerHTML}</body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 300);
        };
    }
</script>

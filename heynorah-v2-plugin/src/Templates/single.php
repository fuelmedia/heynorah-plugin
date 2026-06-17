<?php
/**
 * The template for displaying single inventory items
 * SEO Friendly, Server Side Rendered
 * 
 * This file has been refactored into template parts for better maintainability.
 * See /src/Templates/parts/ directory for individual components.
 */

use HeyNorah\Core\Plugin;

get_header();

// Post Meta Keys - All variable definitions
$post_id = get_the_ID();

// Extract all post meta data
$raw_data = get_post_meta($post_id, 'heynorah_raw_data', true);
if (!is_array($raw_data)) {
    $raw_data = [];
}

$attributes = is_array($raw_data['attributes'] ?? null) ? $raw_data['attributes'] : [];
$media = is_array($raw_data['media'] ?? null) ? $raw_data['media'] : [];

$attribute_keys = [
    'additional_equipment',
    'beam_ft',
    'beam_in',
    'boat_name',
    'boat_name_privacy',
    'builder',
    'covers',
    'cruising_rpm',
    'cruising_speed',
    'cruising_speed_unit',
    'deadrise_at_transom',
    'deadrise',
    'designer',
    'electrical_circuit',
    'electrical_equipment',
    'electronics',
    'engine_units_of_measure',
    'fuel_capacity',
    'fuel_type',
    'galley_equipment',
    'head_equipment',
    'hull_color',
    'hull_material',
    'hull_type',
    'inside_equipment',
    'length_ft',
    'length_in',
    'loa_ft',
    'loa_in',
    'length_on_deck_ft',
    'length_on_deck_in',
    'lwl_ft',
    'lwl_in',
    'max_bridge_clearance_ft',
    'max_bridge_clearance_in',
    'headroom_ft',
    'headroom_in',
    'min_draft_ft',
    'min_draft_in',
    'max_draft_ft',
    'max_draft_in',
    'freeboard_ft',
    'freeboard_in',
    'max_speed',
    'max_speed_unit',
    'model_name',
    'number_of_engines',
    'outside_equipment',
    'propulsion',
    'safety_equipment',
    'seating_capacity',
    'dry_weight',
    'dry_weight_unit',
    'weight',
    'salesTag',
    'warranty_until',
    'windlass',
    'windlass_type',
    'keel_type',
    'hin',
    'hull_warranty',
    'engine_max_rpm',
    'range',
    'gross_tonnage',
    'displacement',
    'displacement_unit',
    'ballast',
    'ballast_unit',
    'fuel_tanks_capacity',
    'fuel_tanks_count',
    'fresh_water_tanks_capacity',
    'fresh_water_tanks_count',
    'holding_tanks_capacity',
    'holding_tanks_count',
    'max_passengers',
    'guest_cabins',
    'guest_heads',
    'number_of_cabins',
    'number_of_heads',
    'number_of_single_berths',
    'number_of_double_berths',
    'crew_cabins',
    'crew_heads',
    'liferaft_capacity',
    'total_engine_power',
];

foreach ($attribute_keys as $attribute_key) {
    ${$attribute_key} = $attributes[$attribute_key] ?? '';
}

if (($dry_weight === '' || $dry_weight === null) && $weight !== '') {
    $dry_weight = $weight;
}
if (($fuel_capacity === '' || $fuel_capacity === null) && $fuel_tanks_capacity !== '') {
    $fuel_capacity = $fuel_tanks_capacity;
}

// Dynamic item fields
$title = (string) ($raw_data['title'] ?? get_the_title());
$slug = (string) ($raw_data['slug'] ?? '');
$status = (string) ($raw_data['status'] ?? '');
$visibility = (string) ($raw_data['visibility'] ?? '');
$year = (int) ($raw_data['year'] ?? 0);
$condition = (string) ($raw_data['condition'] ?? 'Used');
$stockNumber = (string) ($raw_data['stockNumber'] ?? ($raw_data['sku'] ?? ''));
$stock_number = $stockNumber;
$publishedAt = (string) ($raw_data['publishedAt'] ?? '');
$updatedAt = (string) ($raw_data['updatedAt'] ?? '');
$local_delivery = !empty($raw_data['localDelivery']);

// Brand / type / model
$brand = is_array($raw_data['brand'] ?? null) ? $raw_data['brand'] : [];
$type = is_array($raw_data['type'] ?? null) ? $raw_data['type'] : [];
$model = is_array($raw_data['model'] ?? null) ? $raw_data['model'] : [];

$brand_description = (string) ($brand['description'] ?? '');
$brand_id = (string) ($brand['id'] ?? '');
$brand_logoUrl = (string) ($brand['logoUrl'] ?? '');
$brand_name = (string) ($brand['name'] ?? ($raw_data['brandName'] ?? ''));
$brand_slug = (string) ($brand['slug'] ?? ($brand_name !== '' ? sanitize_title($brand_name) : ''));
$brand_website = (string) ($brand['website'] ?? '');

$model_name = (string) ($model['name'] ?? ($raw_data['modelName'] ?? ($attributes['model_name'] ?? '')));
$type_name = (string) ($type['name'] ?? ($raw_data['categoryName'] ?? ($raw_data['category'] ?? '')));
$category = (string) ($raw_data['categoryName'] ?? ($raw_data['category'] ?? $type_name));

// Description
$description = (string) ($raw_data['description'] ?? '');
$short_description = (string) ($raw_data['shortDescription'] ?? '');
if ($short_description === '' && $description !== '') {
    $short_description = wp_trim_words(wp_strip_all_tags($description), 28, '...');
}
if ($description === '') {
    $description = '<p>No description available.</p>';
}

// Feature and spec arrays
$key_features = is_array($raw_data['keyFeatures'] ?? null) ? $raw_data['keyFeatures'] : [];
$specifications = is_array($raw_data['specifications'] ?? null) ? $raw_data['specifications'] : [];

// Price
$price_obj = is_array($raw_data['price'] ?? null) ? $raw_data['price'] : [];
if (empty($price_obj)) {
    $price_obj = [
        'amount' => $raw_data['priceAmount'] ?? 0,
        'currency' => $raw_data['defaultCurrency'] ?? 'USD',
        'msrp' => $raw_data['msrp'] ?? 0,
        'saleAmount' => $raw_data['saleAmount'] ?? 0,
        'effectivePrice' => $raw_data['effectivePrice'] ?? 0,
        'hidePrice' => !empty($raw_data['hidePrice']),
    ];
}
$price_amount = is_numeric($price_obj['amount'] ?? null) ? (float) $price_obj['amount'] : 0.0;
$price_currency = (string) ($price_obj['currency'] ?? 'USD');
$price_msrp = is_numeric($price_obj['msrp'] ?? null) ? (float) $price_obj['msrp'] : 0.0;
$price_sale_amount = is_numeric($price_obj['saleAmount'] ?? null) ? (float) $price_obj['saleAmount'] : 0.0;
$price_effective = is_numeric($price_obj['effectivePrice'] ?? null) ? (float) $price_obj['effectivePrice'] : 0.0;
$price_hide = !empty($price_obj['hidePrice']);
$price_sale = $price_sale_amount;
if ($price_sale <= 0 && $price_effective > 0 && ($price_amount <= 0 || $price_effective < $price_amount)) {
    $price_sale = $price_effective;
}

$display_price = $price_sale > 0 ? $price_sale : ($price_effective > 0 ? $price_effective : $price_amount);
$price_savings = 0;
if ($price_sale > 0 && $price_amount > 0 && $price_sale < $price_amount) {
    $price_savings = $price_amount - $price_sale;
}
if ($price_hide) {
    $display_price = 0;
    $price_savings = 0;
}

$loan_default_amount = 10000;
if (!$price_hide && $display_price > 0) {
    $loan_default_amount = $display_price;
}

// Media
$images = is_array($media['images'] ?? null) ? $media['images'] : [];
$videos = is_array($media['videos'] ?? null) ? $media['videos'] : [];
$documents = is_array($media['documents'] ?? null) ? $media['documents'] : [];

// Engines
$engines = is_array($attributes['engines'] ?? null) ? $attributes['engines'] : [];

$total_engine_power = 0;
foreach ($engines as $engine) {
    $total_engine_power += (int) ($engine['engine_power'] ?? 0);
}
if ($total_engine_power === 0 && is_numeric($attributes['total_power'] ?? null)) {
    $total_engine_power = (int) $attributes['total_power'];
}
if ($total_engine_power === 0 && is_numeric($attributes['total_engine_power'] ?? null)) {
    $total_engine_power = (int) $attributes['total_engine_power'];
}

$engine_1_make = (string) ($engines[0]['engine_make'] ?? '');
$engine_1_model = (string) ($engines[0]['engine_model'] ?? '');
$engine_1_fuel_type = (string) ($engines[0]['engine_fuel_type'] ?? '');
$engine_1_hours = (string) ($engines[0]['engine_hours'] ?? '');
$engine_1_type = (string) ($engines[0]['engine_type'] ?? '');
$engine_1_drive_type = (string) ($engines[0]['engine_drive_type'] ?? '');
if (empty($fuel_type) && $engine_1_fuel_type !== '') {
    $fuel_type = $engine_1_fuel_type;
}

$engine_units_of_measure = (string) ($engine_units_of_measure ?: 'hp - horsepower');
$engine_power_unit = 'HP';
if (stripos($engine_units_of_measure, 'kw') !== false) {
    $engine_power_unit = 'KW';
}

// Broker
$broker = is_array($raw_data['broker'] ?? null) ? $raw_data['broker'] : [];
$broker_name = (string) ($broker['name'] ?? ($raw_data['brokerName'] ?? 'Sales Team'));
$broker_email = (string) ($broker['email'] ?? ($raw_data['brokerEmail'] ?? ''));
$broker_phone = (string) ($broker['phone'] ?? ($broker['phoneNumber'] ?? ($raw_data['brokerPhoneNumber'] ?? '')));
$broker_role = (string) ($broker['role'] ?? 'broker');
$broker_picture_url = (string) ($broker['pictureUrl'] ?? ($raw_data['brokerPictureUrl'] ?? ''));
$broker_listing_count = 0;

// Location
$location = is_array($raw_data['location'] ?? null) ? $raw_data['location'] : [];
$location_name = (string) ($location['name'] ?? ($raw_data['locationName'] ?? ''));
$location_address = (string) ($location['address'] ?? '');
$location_city = (string) ($location['city'] ?? '');
$location_state = (string) ($location['state'] ?? '');
$location_zip_code = (string) ($location['zipCode'] ?? '');
$location_country = (string) ($location['country'] ?? '');
$location_latitude = (string) ($location['latitude'] ?? '');
$location_longitude = (string) ($location['longitude'] ?? '');
$location_phone = (string) ($location['phone'] ?? ($location['phoneNumber'] ?? ($raw_data['locationPhoneNumber'] ?? '')));
$location_email = (string) ($location['email'] ?? ($raw_data['locationEmail'] ?? ''));
$location_street_number = (string) ($location['streetNumber'] ?? '');
$location_street = (string) ($location['street'] ?? '');

$location_hours = (string) ($location['hours'] ?? '');
if ($location_hours === '' && !empty($location['operatingHours']) && is_array($location['operatingHours'])) {
    $location_hours = 'Check operating hours';
}
if ($location_hours === '') {
    $location_hours = 'Contact showroom for hours';
}

$location_title = (string) ($location['title'] ?? ($raw_data['locationTitle'] ?? ''));
if ($location_title === '') {
    $location_title = trim(implode(', ', array_filter([$location_city, $location_state])));
}
if ($location_name === '') {
    $location_name = $location_title;
}

$location_formatted_address = trim(
    implode(', ', array_filter([
        trim($location_street_number . ' ' . $location_street),
        $location_address,
        trim($location_city . ($location_state !== '' ? ', ' . $location_state : '')),
        $location_zip_code,
        $location_country,
    ]))
);

$location_formatted = $location_title !== ''
    ? $location_title
    : trim(implode(', ', array_filter([$location_city, $location_state, $location_country])));

$location_google_maps_url = '';
if (!empty($location['placeId'])) {
    $location_google_maps_url = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($location['placeId']);
}

$location_directions_url = '';
if ($location_latitude !== '' && $location_longitude !== '') {
    $location_directions_url = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($location_latitude . ',' . $location_longitude);
} elseif ($location_formatted !== '') {
    $location_directions_url = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($location_formatted);
}

$disclaimer = (string) ($raw_data['disclaimer'] ?? '');

// Helper function for formatting feet and inches
if (!function_exists('format_feet_inches')) {
    function format_feet_inches($feet, $inches) {
        $feet = is_numeric($feet) ? (int) $feet : 0;
        $inches = is_numeric($inches) ? (int) $inches : 0;

        if ($inches >= 12 && $feet > 0 && $inches >= ($feet * 12)) {
            $inches -= ($feet * 12);
        }
        if ($inches >= 12) {
            $feet += intdiv($inches, 12);
            $inches = $inches % 12;
        }

        $parts = [];
        if ($feet > 0) $parts[] = $feet . '\'';
        if ($inches > 0) $parts[] = $inches . '"';
        return !empty($parts) ? implode(' ', $parts) : '--';
    }
}

$parts_dir = __DIR__ . '/parts/';
?>

<div id="heynorah-root"
    class="heynorah-single-container min-h-screen bg-neutral-50 dark:bg-zinc-950 font-sans text-slate-900 dark:text-slate-100">
    
    <?php
    // 1. Hero Section - Breadcrumb
    include $parts_dir . 'hero-breadcrumb.php';
    ?>
    
    <!-- Hero Container -->
    <div class="inv-hero w-full">
        <div class="w-full">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 h-auto lg:h-[640px] min-h-[600px]">
                <!-- Left: Gallery -->
                <div class="lg:col-span-7 xl:col-span-8 bg-gray-100 dark:bg-zinc-900 relative">
                    <?php
                    // 2. Hero Section - Gallery
                    include $parts_dir . 'hero-gallery.php';
                    ?>
                </div>
                
                <!-- Right: Quick Info -->
                <div class="lg:col-span-5 xl:col-span-4 bg-zinc-950 text-white flex items-center justify-center p-8 lg:p-12 xl:p-16">
                    <?php
                    // 3. Hero Section - Quick Info
                    include $parts_dir . 'hero-quick-info.php';
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <div id="content" class="site-content max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-16 relative z-10">
        <div id="primary" class="content-area">
            <main id="main" class="site-main">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
                    <!-- Left Column: Gallery & Details -->
                    <div class="lg:col-span-8">
                        <?php
                        // 2. Meta Badges
                        include $parts_dir . 'meta-badges.php';
                        
                        // 3. Accordion Details (Inventory Details, Specifications, Engine, Additional Info)
                        include $parts_dir . 'accordion-details.php';
                        ?>
                    </div> <!-- End Left Column -->

                    <?php
                    // 4. Sidebar Broker
                    include $parts_dir . 'sidebar-broker.php';
                    ?>
                </div> <!-- End Row -->
            </main>
        </div>
    </div>

    <?php
    // 5. Loan Calculator
    include $parts_dir . 'loan-calculator.php';
    
    // 6. Showroom Information
    include $parts_dir . 'showroom-info.php';
    ?>

    <section>
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
            <?php
            // 7. CTA Section
            include $parts_dir . 'cta-section.php';
            ?>
        </div>
    </section>

    <?php
    // 8. Related Inventory ("You May Also Like")
    include $parts_dir . 'related-inventory.php';
    
    // 9. Modals
    include $parts_dir . 'modal-forms.php';
    include $parts_dir . 'modal-share.php';
    include $parts_dir . 'modal-print.php';
    
    // 10. Scripts (All JavaScript)
    include $parts_dir . 'scripts.php';
    ?>

</div> <!-- End heynorah-root -->

<?php
get_footer();

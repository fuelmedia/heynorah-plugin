<?php
/**
 * Template part: Accordion Details
 * Product details accordion with multiple sections:
 * - Inventory Details
 * - Specifications
 * - Engine
 * - Additional Information
 * 
 * @var int $post_id
 * @var string $description
 * @var array $key_features
 * @var array $specifications
 * @var array $engines
 * @var array $additional_info
 */
?>

                        <!-- Product Details Accordion -->
                        <!-- Product Details Accordion (Flowbite) -->
                        <div id="accordion-flush" data-accordion="collapse"
                            data-active-classes="bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-white"
                            data-inactive-classes="text-gray-500 dark:text-gray-400">
                            <!-- Inventory Details -->
                            <h2 id="accordion-flush-heading-1" class="!text-xl">
                                <button type="button"
                                    class="flex items-center justify-between w-full py-3 font-medium rtl:text-right border-b border-gray-200 dark:border-zinc-700 gap-3"
                                    data-accordion-target="#accordion-flush-body-1" aria-expanded="true"
                                    aria-controls="accordion-flush-body-1">
                                    <span>Inventory Details</span>
                                    <svg data-accordion-icon class="w-5 h-5 rotate-180 shrink-0" aria-hidden="true"
                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                        viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                            stroke-width="2" d="m5 15 7-7 7 7" />
                                    </svg>
                                </button>
                            </h2>
                            <div id="accordion-flush-body-1" style="display: block;"
                                aria-labelledby="accordion-flush-heading-1">
                                <div class="py-3 text-gray-500 dark:text-gray-400">
                                    <div id="inv-details-text" class="inv-details-collapsed relative">
                                        <?php echo wp_kses_post($description); ?>
                                    </div>
                                    <a href="#" id="inv-read-more"
                                        class="inline-block mt-4 text-emerald-600 dark:text-emerald-400 font-semibold hover:text-emerald-700 !no-underline"
                                        onclick="event.preventDefault(); var text = this.previousElementSibling; if(text.classList.contains('inv-details-collapsed')){ text.classList.remove('inv-details-collapsed'); text.classList.add('inv-details-expanded'); this.innerHTML = 'Read Less <i class=\'ti ti-chevron-up ms-1 text-xs\'></i>'; } else { text.classList.remove('inv-details-expanded'); text.classList.add('inv-details-collapsed'); this.innerHTML = 'Read More <i class=\'ti ti-chevron-down ms-1 text-xs\'></i>'; }">Read
                                        More <i class="ti ti-chevron-down ms-1 text-xs"></i></a>
                                    <!-- Key Features Inside Accordion Body -->
                                    <!-- <div
                                        class="rounded-xl border border-gray-100 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-800/30 my-8 overflow-hidden">
                                        <div
                                            class="flex items-center p-4 border-b border-gray-100 dark:border-zinc-800">
                                            <div
                                                class="bg-black text-white rounded-full w-10 h-10 flex items-center justify-center mr-3 shadow-sm">
                                                <i class="ti ti-robot"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-0 font-medium text-gray-900 dark:text-white">Ask heynorah
                                                </h5>
                                            </div>
                                        </div>
                                        <div class="p-6">
                                            <p class="text-gray-500 font-semibold mb-2 text-sm uppercase tracking-wide">
                                                HeyNorah</p>
                                            <p class="mb-4 text-gray-600 dark:text-gray-300">Hi there! I’m here to help
                                                answer your questions about this vessel.
                                                Let’s dive in!</p>
                                            <div class="flex flex-wrap gap-2">
                                                <span
                                                    class="bg-white dark:bg-zinc-800 text-gray-700 dark:text-gray-200 border dark:border-zinc-700 px-4 py-2 rounded-full text-sm cursor-pointer hover:bg-gray-50 dark:hover:bg-zinc-700 transition-colors">What's
                                                    the fuel consumption?</span>
                                                <span
                                                    class="bg-white dark:bg-zinc-800 text-gray-700 dark:text-gray-200 border dark:border-zinc-700 px-4 py-2 rounded-full text-sm cursor-pointer hover:bg-gray-50 dark:hover:bg-zinc-700 transition-colors">Any
                                                    features with a shower?</span>
                                                <span
                                                    class="bg-white dark:bg-zinc-800 text-gray-700 dark:text-gray-200 border dark:border-zinc-700 px-4 py-2 rounded-full text-sm cursor-pointer hover:bg-gray-50 dark:hover:bg-zinc-700 transition-colors">Tell
                                                    me about the owner</span>
                                            </div>
                                        </div>
                                        <div
                                            class="bg-white dark:bg-zinc-800 p-4 border-t border-gray-100 dark:border-zinc-700">
                                            <div class="relative">
                                                <input type="text"
                                                    class="w-full bg-gray-100 dark:bg-zinc-900 border-transparent rounded-full py-3 h-12 pl-6 pr-14 focus:border-gray-300 dark:focus:border-zinc-600 focus:bg-white dark:focus:bg-zinc-950 focus:ring-0 transition-colors"
                                                    placeholder="Ask heynorah something specific...">
                                                <button
                                                    class="bg-black hover:bg-gray-800 text-white w-10 h-10 rounded-full absolute top-1 right-1 flex items-center justify-center transition-colors">
                                                    <i class="ti ti-arrow-right"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div> -->
                                </div>
                            </div>
                            <!-- Specifications -->
                            <h2 id="accordion-flush-heading-2" class="!text-xl">
                                <button type="button"
                                    class="flex items-center justify-between w-full py-3 font-medium rtl:text-right border-b border-gray-200 dark:border-zinc-700 gap-3"
                                    data-accordion-target="#accordion-flush-body-2" aria-expanded="false"
                                    aria-controls="accordion-flush-body-2">
                                    <span>Specifications</span>
                                    <svg data-accordion-icon class="w-5 h-5 rotate-180 shrink-0" aria-hidden="true"
                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                        viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                            stroke-width="2" d="m5 15 7-7 7 7" />
                                    </svg>
                                </button>
                            </h2>
                            <div id="accordion-flush-body-2" style="display: none;"
                                aria-labelledby="accordion-flush-heading-2">
                                <div class="py-3 text-gray-500 dark:text-gray-400">
                                    <?php
                                    // Helper variables for formatted location
                                    $us_states = [
                                        'Alabama' => 'AL',
                                        'Alaska' => 'AK',
                                        'Arizona' => 'AZ',
                                        'Arkansas' => 'AR',
                                        'California' => 'CA',
                                        'Colorado' => 'CO',
                                        'Connecticut' => 'CT',
                                        'Delaware' => 'DE',
                                        'Florida' => 'FL',
                                        'Georgia' => 'GA',
                                        'Hawaii' => 'HI',
                                        'Idaho' => 'ID',
                                        'Illinois' => 'IL',
                                        'Indiana' => 'IN',
                                        'Iowa' => 'IA',
                                        'Kansas' => 'KS',
                                        'Kentucky' => 'KY',
                                        'Louisiana' => 'LA',
                                        'Maine' => 'ME',
                                        'Maryland' => 'MD',
                                        'Massachusetts' => 'MA',
                                        'Michigan' => 'MI',
                                        'Minnesota' => 'MN',
                                        'Mississippi' => 'MS',
                                        'Missouri' => 'MO',
                                        'Montana' => 'MT',
                                        'Nebraska' => 'NE',
                                        'Nevada' => 'NV',
                                        'New Hampshire' => 'NH',
                                        'New Jersey' => 'NJ',
                                        'New Mexico' => 'NM',
                                        'New York' => 'NY',
                                        'North Carolina' => 'NC',
                                        'North Dakota' => 'ND',
                                        'Ohio' => 'OH',
                                        'Oklahoma' => 'OK',
                                        'Oregon' => 'OR',
                                        'Pennsylvania' => 'PA',
                                        'Rhode Island' => 'RI',
                                        'South Carolina' => 'SC',
                                        'South Dakota' => 'SD',
                                        'Tennessee' => 'TN',
                                        'Texas' => 'TX',
                                        'Utah' => 'UT',
                                        'Vermont' => 'VT',
                                        'Virginia' => 'VA',
                                        'Washington' => 'WA',
                                        'West Virginia' => 'WV',
                                        'Wisconsin' => 'WI',
                                        'Wyoming' => 'WY'
                                    ];

                                    $display_state = $location_state ?? '';
                                    $normalized_state = ucwords(strtolower($display_state));
                                    if (isset($us_states[$normalized_state])) {
                                        $display_state = $us_states[$normalized_state];
                                    }

                                    $countries = [
                                        'United States' => 'US',
                                        'United States of America' => 'USA',
                                        'Canada' => 'CA',
                                        'United Kingdom' => 'UK'
                                    ];

                                    $display_country = $location_country ?? '';
                                    $normalized_country = ucwords(strtolower($display_country));
                                    if (isset($countries[$normalized_country])) {
                                        $display_country = $countries[$normalized_country];
                                    }

                                    $loc_parts = array_filter([$location_city ?? '', $display_state, $display_country]);
                                    $location_formatted = implode(', ', $loc_parts);

                                    // Helper for tanks
                                    function format_tank_info($cap, $count, $unit = 'gal')
                                    {
                                        if (empty($cap))
                                            return null;
                                        $out = $cap . ' ' . $unit;
                                        if (!empty($count) && $count > 1) {
                                            $out .= ' (' . $count . ' tx)';
                                        }
                                        return $out;
                                    }

                                    $spec_groups = [
                                        'General' => [
                                            'Make' => $brand_name ?? null,
                                            'Model' => $model_name ?? null,
                                            'Year' => $year ?? null,
                                            'Category' => $category ?? null,
                                            'Type' => $type_name ?? null,
                                            'Stock Number' => $stock_number ?? null,
                                            'Condition' => $condition ?? null,
                                            'Local Delivery' => isset($local_delivery) && $local_delivery ? 'Yes' : null,
                                            'Price' => !empty($price_hide) ? 'Contact for Price' : (!empty($price_amount) ? '$' . number_format($price_amount) : null),
                                            'Sale Price' => empty($price_hide) && !empty($price_sale) ? '$' . number_format($price_sale) : null,
                                            'MSRP' => empty($price_hide) && !empty($price_msrp) ? '$' . number_format($price_msrp) : null,
                                            'Location' => $location_formatted,
                                            'Hull Type' => $hull_type ?? null,
                                            'Hull Material' => $hull_material ?? null,
                                            'Keel Type' => $keel_type ?? null,
                                            'HIN' => $hin ?? null,
                                            'Boat Name' => $boat_name ?? null,
                                            'Builder' => $builder ?? null,
                                            'Designer' => $designer ?? null,
                                            'Hull Warranty' => $hull_warranty ?? null,
                                            'Warranty Until' => !empty($warranty_until) ? date('M j, Y', strtotime($warranty_until)) : null,
                                        ],
                                        'Speed and Distance' => [
                                            'Cruising Speed' => !empty($cruising_speed) ? $cruising_speed . ' ' . ($cruising_speed_unit ?? 'kn') : null,
                                            'Cruising RPM' => $cruising_rpm ?? null,
                                            'Max RPM' => $engine_max_rpm ?? null,
                                            'Range' => !empty($range) ? $range . ' nm' : null,
                                            'Max Speed' => !empty($max_speed) ? $max_speed . ' ' . ($max_speed_unit ?? 'kn') : null,
                                        ],
                                        'Dimensions' => [
                                            'Length' => format_feet_inches($length_ft ?? 0, $length_in ?? 0),
                                            'LOA' => format_feet_inches($loa_ft ?? 0, $loa_in ?? 0), // Assumed LOF was typo for LOA
                                            'Length on Deck' => format_feet_inches($length_on_deck_ft ?? 0, $length_on_deck_in ?? 0),
                                            'Beam' => format_feet_inches($beam_ft ?? 0, $beam_in ?? 0),
                                            'LWL' => format_feet_inches($lwl_ft ?? 0, $lwl_in ?? 0),
                                            'Max Bridge Clearance' => format_feet_inches($max_bridge_clearance_ft ?? 0, $max_bridge_clearance_in ?? 0),
                                            'Headroom' => format_feet_inches($headroom_ft ?? 0, $headroom_in ?? 0),
                                            'Min Draft' => format_feet_inches($min_draft_ft ?? 0, $min_draft_in ?? 0),
                                            'Max Draft' => format_feet_inches($max_draft_ft ?? 0, $max_draft_in ?? 0),
                                            'Freeboard' => format_feet_inches($freeboard_ft ?? 0, $freeboard_in ?? 0),
                                            'Deadrise at Transom' => !empty($deadrise) ? $deadrise . ' deg' : null,
                                            'Gross Tonnage' => $gross_tonnage ?? null,
                                            'Windlass' => $windlass_type ?? null,
                                            'Electrical Circuit' => $electrical_circuit ?? null,
                                        ],
                                        'Weight' => [
                                            'Dry Weight' => !empty($dry_weight) ? number_format($dry_weight) . ' ' . ($dry_weight_unit ?? 'lb') : null,
                                            'Displacement' => !empty($displacement) ? number_format($displacement) . ' ' . ($displacement_unit ?? 'lb') : null,
                                            'Ballast' => !empty($ballast) ? number_format($ballast) . ' ' . ($ballast_unit ?? 'lb') : null,
                                        ],
                                        'Tanks' => [
                                            'Fuel Tanks' => format_tank_info($fuel_tanks_capacity ?? null, $fuel_tanks_count ?? null),
                                            'Fresh Water Tanks' => format_tank_info($fresh_water_tanks_capacity ?? null, $fresh_water_tanks_count ?? null),
                                            'Holding Tanks' => format_tank_info($holding_tanks_capacity ?? null, $holding_tanks_count ?? null),
                                        ],
                                        'Accommodations' => [
                                            'Seating Capacity' => $seating_capacity ?? null,
                                            'Max Passengers' => $max_passengers ?? null,
                                            'Guest Cabins' => $guest_cabins ?? null,
                                            'Guest Heads' => $guest_heads ?? null,
                                            'Cabins' => $number_of_cabins ?? null,
                                            'Heads' => $number_of_heads ?? null,
                                            'Single Berths' => $number_of_single_berths ?? null,
                                            'Double Berths' => $number_of_double_berths ?? null,
                                            'Crew Cabins' => $crew_cabins ?? null,
                                            'Crew Heads' => $crew_heads ?? null,
                                            'Liferaft Capacity' => $liferaft_capacity ?? null,
                                        ],
                                    ];

                                    foreach ($spec_groups as $group_title => $items):
                                        // Filter out empty items
                                        $visible_items = array_filter($items, function ($v) {
                                            return !empty($v) && $v !== '0\' 0"';
                                        });

                                        if (!empty($visible_items)):
                                            ?>
                                            <div
                                                class="mb-3 bg-white p-5">
                                                <h3
                                                    class="!text-lg font-semibold mb-3 mt-5 first:mt-0 text-gray-900 dark:text-white">
                                                    <?php echo $group_title; ?>
                                                </h3>
                                                <div class="columns-1 md:columns-2 lg:columns-4 gap-8">
                                                    <?php foreach ($visible_items as $label => $value): ?>
                                                        <div
                                                            class="break-inside-avoid flex flex-col sm:justify-between border-b  border-gray-100 dark:border-zinc-800 pb-1 sm:border-none mb-2">
                                                            <strong
                                                                class="text-xs font-medium text-zinc-500 uppercase tracking-[.05em]"><?php echo $label; ?></strong>
                                                            <span
                                                                class="text-base font-medium text-zinc-900 dark:text-white"><?php echo esc_html($value); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </div>
                            </div>
                            <!-- Engine -->
                            <h2 id="accordion-flush-heading-3" class="!text-xl">
                                <button type="button"
                                    class="flex items-center justify-between w-full py-3 font-medium rtl:text-right border-b border-gray-200 dark:border-zinc-700 gap-3"
                                    data-accordion-target="#accordion-flush-body-3" aria-expanded="false"
                                    aria-controls="accordion-flush-body-3">
                                    <span>Engine</span>
                                    <svg data-accordion-icon class="w-5 h-5 rotate-180 shrink-0" aria-hidden="true"
                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                        viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                            stroke-width="2" d="m5 15 7-7 7 7" />
                                    </svg>
                                </button>
                            </h2>
                            <div id="accordion-flush-body-3" style="display: none;"
                                aria-labelledby="accordion-flush-heading-3">
                                    <?php if (is_array($engines) && !empty($engines)): ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <?php foreach ($engines as $index => $engine):
                                            $engine_number = $index + 1;
                                            ?>
                                            <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-5">
                                            <?php if (count($engines) > 1): ?>
                                                <h5 class="font-bold text-gray-900 dark:text-white mb-4 first:mt-0">Engine
                                                    #<?php echo $engine_number; ?></h5>
                                            <?php endif; ?>

                                            <?php
                                            // Define engine fields structure
                                            $engine_fields = [
                                                'Make' => $engine['engine_make'] ?? null,
                                                'Model' => $engine['engine_model'] ?? null,
                                                'Year' => $engine['engine_year'] ?? null,
                                                'Power' => !empty($engine['engine_power']) ? $engine['engine_power'] . ' ' . $engine_power_unit : null,
                                                'Hours' => $engine['engine_hours'] ?? null,
                                                'Fuel Type' => $engine['engine_fuel_type'] ?? null,
                                                'Type' => $engine['engine_type'] ?? null,
                                                'Drive Type' => $engine['engine_drive_type'] ?? null,
                                                'Location' => $engine['engine_location_on_vessel'] ?? null,
                                                'Propeller Type' => $engine['engine_propeller_type'] ?? null,
                                                'Propeller Material' => $engine['engine_propeller_material'] ?? null,
                                            ];

                                            // Filter out empty fields
                                            $visible_engine_fields = array_filter($engine_fields, function ($v) {
                                                return !empty($v);
                                            });
                                            ?>

                                            <?php if (!empty($visible_engine_fields)): ?>
                                                <div class="columns-1 md:columns-2 gap-8 mb-6">
                                                    <?php foreach ($visible_engine_fields as $label => $value): ?>
                                                        <div
                                                            class="break-inside-avoid flex flex-col sm:justify-between border-b  border-gray-100 dark:border-zinc-800 pb-1 sm:border-none mb-2">
                                                            <strong class="text-xs font-medium text-zinc-500 uppercase tracking-[.05em]"><?php echo $label; ?></strong>
                                                            <span
                                                                class="text-base font-medium text-zinc-900 dark:text-white"><?php echo esc_html($value); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-gray-500 dark:text-gray-400">No engine information available.</p>
                                    <?php endif; ?>
                            
                            </div>

                            <!-- Additional Information -->
                            <h2 id="accordion-flush-heading-4" class="!text-xl">
                                <button type="button"
                                    class="flex items-center justify-between w-full py-3 font-medium rtl:text-right border-b border-gray-200 dark:border-zinc-700 gap-3"
                                    data-accordion-target="#accordion-flush-body-4" aria-expanded="false"
                                    aria-controls="accordion-flush-body-4">
                                    <span>Additional Information</span>
                                    <svg data-accordion-icon class="w-5 h-5 rotate-180 shrink-0" aria-hidden="true"
                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                        viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                            stroke-width="2" d="m5 15 7-7 7 7" />
                                    </svg>
                                </button>
                            </h2>
                            <div id="accordion-flush-body-4" style="display: none;"
                                aria-labelledby="accordion-flush-heading-4">
                                <div class="py-3 text-gray-500 dark:text-gray-400">
                                    <?php
                                    $info_sections = [
                                        'Electronics' => $electronics ?? null,
                                        'Inside Equipment' => $inside_equipment ?? null,
                                        'Electrical Equipment' => $electrical_equipment ?? null,
                                        'Outside Equipment' => $outside_equipment ?? null,
                                        'Covers' => $covers ?? null,
                                        'Additional Equipment' => $additional_equipment ?? null,
                                    ];

                                    foreach ($info_sections as $section_title => $data):
                                        if (!empty($data)):
                                            ?>
                                            <h3
                                                class="!text-lg font-semibold mb-2 mt-6 first:mt-0 text-gray-900 dark:text-white">
                                                <?php echo $section_title; ?>
                                            </h3>
                                            <?php if (is_array($data)): ?>
                                                <ul
                                                    class="list-none list-inside grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 ps-0 mb-5">
                                                    <?php foreach ($data as $item):
                                                        if (is_string($item) || is_numeric($item)): ?>
                                                            <li class="!text-base"><i class="ti ti-check"></i> <?php echo esc_html($item); ?></li>
                                                        <?php endif; endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <div class="prose dark:prose-invert max-w-none">
                                                    <?php echo wp_kses_post($data); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </div>
                            </div>

                        </div>

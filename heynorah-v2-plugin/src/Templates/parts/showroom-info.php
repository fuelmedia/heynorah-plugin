<?php
/**
 * Template part: Showroom Info
 * Displays showroom location details with Google Maps integration
 * 
 * @var string $location_name
 * @var string $location_address
 * @var string $location_formatted_address
 * @var string $location_google_maps_url
 * @var string $location_directions_url
 * @var string $location_hours
 * @var string $location_phone
 * @var float $location_latitude
 * @var float $location_longitude
 * @var string $location_street_number
 * @var string $location_street
 * @var string $location_city
 * @var string $location_state
 * @var string $location_zip_code
 */
?>

<!-- Showroom Information -->
<section>
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-16">
            <h3 class="text-3xl font-light text-slate-900 dark:text-white mb-8">Showroom Information</h3>
            <div
                class="bg-white dark:bg-zinc-900 border border-gray-100 dark:border-zinc-800 overflow-hidden">
                <div class="grid grid-cols-1 lg:grid-cols-12">
                    <div class="lg:col-span-5 p-8 lg:p-12 flex flex-col justify-center">
                        <h4 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                            <?php echo esc_html($location_name); ?>
                        </h4>
                        <p class="text-gray-500 dark:text-gray-400 text-sm mb-4 leading-relaxed">
                            <?php echo esc_html($location_address); ?>
                        </p>
                        <p class="text-emerald-600 dark:text-emerald-500 text-sm mb-6 flex items-center gap-1">
                            <a href="<?php echo !empty($location_google_maps_url) ? esc_url($location_google_maps_url) : 'https://www.google.com/maps/search/' . urlencode($location_formatted_address); ?>" target="_blank"
                                class="hover:underline flex items-center gap-1 !no-underline">
                                <span>Open in Google Maps</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </a>
                        </p>
                        <h6
                            class="font-bold text-gray-900 dark:text-white uppercase tracking-wider text-xs mb-4 mt-4">
                            Contact</h6>
                        <div class="flex items-center mb-3 text-gray-600 dark:text-gray-400 text-sm">
                            <i class="ti ti-clock w-5 text-gray-400"></i>
                            <span><?php echo esc_html($location_hours); ?></span>
                        </div>
                        <?php if (!empty($location_phone)): ?>
                            <div class="flex items-center mb-3 text-gray-600 dark:text-gray-400 text-sm">
                                <i class="ti ti-phone w-5 text-gray-400"></i>
                                <span><?php echo esc_html($location_phone); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($location_email)): ?>
                            <div class="flex items-center mb-8 text-gray-600 dark:text-gray-400 text-sm">
                                <i class="ti ti-mail w-5 text-gray-400"></i>
                                <a class="hover:underline !no-underline"
                                    href="mailto:<?php echo esc_attr($location_email); ?>"><?php echo esc_html($location_email); ?></a>
                            </div>
                        <?php else: ?>
                            <div class="mb-8"></div>
                        <?php endif; ?>

                        <div class="flex gap-3">
                            <a href="<?php echo !empty($location_directions_url) ? esc_url($location_directions_url) : 'https://www.google.com/maps/search/' . urlencode($location_formatted_address); ?>" target="_blank"
                                class="py-2.5 px-5 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700 !no-underline">
                                Directions
                            </a>
                            <a href="#"
                                class="text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800 !no-underline">
                                Contact Us
                            </a>
                        </div>
                    </div>
                    <div class="lg:col-span-7">
                        <!-- Map Placeholder -->
                        <div class="bg-gray-100 dark:bg-zinc-800 h-full min-h-[300px] lg:min-h-full w-full">
                            <?php
                            $map_query = '';
                            if (!empty($location_latitude) && !empty($location_longitude)) {
                                $map_query = $location_latitude . ',' . $location_longitude;
                            } elseif (!empty($location_formatted_address)) {
                                $map_query = $location_formatted_address;
                            } else {
                                $addr_parts = [];
                                if (!empty($location_street_number) && !empty($location_street)) $addr_parts[] = $location_street_number . ' ' . $location_street;
                                if (!empty($location_city)) $addr_parts[] = $location_city;
                                if (!empty($location_state)) $addr_parts[] = $location_state;
                                if (!empty($location_zip_code)) $addr_parts[] = $location_zip_code;
                                
                                $map_query = implode(', ', $addr_parts);
                            }
                            
                            if (!empty($map_query)) :
                            ?>
                            <iframe
                                src="https://maps.google.com/maps?q=<?php echo urlencode($map_query); ?>&t=&z=13&ie=UTF8&iwloc=&output=embed"
                                class="w-full h-full border-0 min-h-[300px]" allowfullscreen="" loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"></iframe>
                            <?php else: ?>
                                <div class="flex items-center justify-center h-full text-gray-500">Map unavailable</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

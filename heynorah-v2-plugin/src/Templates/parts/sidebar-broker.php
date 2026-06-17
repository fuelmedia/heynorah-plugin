<?php
/**
 * Template part: Sidebar Broker
 * Displays broker contact card with call-to-action buttons
 * 
 * @var string $broker_name
 * @var string $broker_phone
 * @var string $broker_email
 * @var string $broker_role
 * @var string $broker_picture_url
 * @var string $location_address
 * @var int $broker_listing_count
 */
?>

<div class="lg:col-span-4">
    <div class="sticky top-24 z-10">
        <!-- Broker Card -->
        <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-8 mb-6">
            <div class="flex items-start mb-6">
                <div class="shrink-0">
                    <div class="w-12 h-12 bg-gray-200 rounded-full overflow-hidden">
                        <?php if (!empty($broker_picture_url)): ?>
                            <img src="<?php echo esc_url($broker_picture_url); ?>" alt="<?php echo esc_attr($broker_name); ?>"
                                class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-xs font-semibold text-gray-600 uppercase">
                                <?php echo esc_html(substr($broker_name, 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="grow ml-4">
                    <h5 class="font-bold text-gray-900 dark:text-white mb-1 flex items-center">
                        <?php echo esc_html($broker_name); ?> <span
                            class="ml-2 bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-gray-400 text-xs px-2 py-0.5 rounded-full uppercase tracking-wide"><?php echo esc_html($broker_role ?: 'Broker'); ?></span>
                    </h5>
                    <?php if (!empty($location_address)): ?>
                        <p class="text-gray-500 text-sm mb-1"><?php echo esc_html($location_address); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($broker_email)): ?>
                        <p class="text-gray-500 text-sm mb-1">
                            <a href="mailto:<?php echo esc_attr($broker_email); ?>" class="hover:text-emerald-600 transition-colors !no-underline">
                                <?php echo esc_html($broker_email); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($broker_listing_count)): ?>
                        <p class="text-gray-500 text-sm mb-0"><a href="#"
                                class="hover:text-emerald-600 transition-colors !no-underline">View All
                                Listing
                                (<?php echo esc_html((string) $broker_listing_count); ?>)</a></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <?php if (!empty($broker_phone)): ?>
                    <a href="tel:<?php echo esc_attr($broker_phone); ?>"
                        class="!text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800 w-full text-center !no-underline">Call
                        Broker</a>
                <?php else: ?>
                    <button type="button"
                        class="!text-white bg-blue-700/40 font-medium rounded-lg text-sm px-5 py-2.5 cursor-not-allowed w-full text-center"
                        disabled>Call Broker</button>
                <?php endif; ?>
                <a href="#modal-message-broker" data-fancybox
                    class="py-2.5 px-5 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700 w-full text-center !no-underline">Send
                    Message</a>
            </div>
            <hr class="my-6 border-gray-100 dark:border-zinc-800">
            <h5 class="mb-3 font-semibold text-gray-900 dark:text-white">Live Meeting Schedule</h5>
            <div class="flex items-center mb-6">
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Live tours
                        available: </div>
                    <div class="text-gray-700 dark:text-gray-300 font-medium text-sm">Every day from
                        10:00 AM - 5:00 PM
                    </div>
                </div>
            </div>
            <div class="flex">
                <a href="#modal-appointment" data-fancybox
                    class="!text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800 w-full text-center !no-underline">Make
                    an Appointment</a>
            </div>
        </div>
    </div>
</div>

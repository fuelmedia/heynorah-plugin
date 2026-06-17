<?php
/**
 * Template part: Hero Breadcrumb
 * Displays breadcrumb navigation and action buttons (print, share)
 */

$inventory_link = get_post_type_archive_link('heynorah_inventory');
if (!is_string($inventory_link) || $inventory_link === '') {
    $inventory_link = home_url('/');
}

$inventory_terms = wp_get_post_terms(get_the_ID(), 'heynorah_type', ['number' => 1]);
if (!is_wp_error($inventory_terms) && !empty($inventory_terms)) {
    $primary_term = $inventory_terms[0];
    $term_link = get_term_link($primary_term);
    if (!is_wp_error($term_link) && is_string($term_link) && $term_link !== '') {
        $inventory_link = $term_link;
    }
}
?>

<div class="inv-top border-b border-gray-100 dark:border-zinc-800">
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
        <!-- Breadcrumb -->
        <?php if (function_exists('rank_math_the_breadcrumbs')): ?>
            <div class="heynorah-rank-math-breadcrumbs">
                <?php rank_math_the_breadcrumbs(); ?>
            </div>
        <?php elseif (function_exists('yoast_breadcrumb')): ?>
            <div class="heynorah-yoast-breadcrumbs">
                <?php yoast_breadcrumb('<div id="breadcrumbs">', '</div>'); ?>
            </div>
        <?php else: ?>
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse !mb-0">
                    <li class="inline-flex items-center">
                        <a href="<?php echo esc_url(home_url('/')); ?>"
                            class="inline-flex text-decoration-none items-center text-sm font-medium text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">
                            <svg class="w-4 h-4 me-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                    stroke-width="2"
                                    d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5" />
                            </svg>
                            Home
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center space-x-1.5">
                            <svg class="w-3.5 h-3.5 rtl:rotate-180 text-gray-700 dark:text-gray-400" aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                    stroke-width="2" d="m9 5 7 7-7 7" />
                            </svg>
                            <a href="<?php echo esc_url($inventory_link); ?>"
                                class="inline-flex text-decoration-none items-center text-sm font-medium text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">Inventory</a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center space-x-1.5">
                            <svg class="w-3.5 h-3.5 rtl:rotate-180 text-gray-700 dark:text-gray-400" aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                    stroke-width="2" d="m9 5 7 7-7 7" />
                            </svg>
                            <span
                                class="inline-flex items-center text-sm font-medium text-gray-500 dark:text-gray-400"><?php echo esc_html(get_the_title()); ?></span>
                        </div>
                    </li>
                </ol>
            </nav>
        <?php endif; ?>
        <ul class="flex items-center gap-4 !mb-0 list-none">
            <li>
                <a href="#modal-print" data-fancybox
                    class="text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors print-link text-decoration-none">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 19.5 21"
                        class="stroke-current">
                        <path id="Path_33" data-name="Path 33"
                            d="M6.72,13.829q-.36.045-.72.1m.72-.1a42.415,42.415,0,0,1,10.56,0m-10.56,0L6.34,18m10.94-4.171q.36.045.72.1m-.72-.1L17.66,18m0,0,.229,2.523a1.125,1.125,0,0,1-1.12,1.227H7.231a1.124,1.124,0,0,1-1.12-1.227L6.34,18m11.318,0h1.091A2.25,2.25,0,0,0,21,15.75V9.456a2.179,2.179,0,0,0-1.837-2.175q-.954-.143-1.913-.247M6.34,18H5.25A2.25,2.25,0,0,1,3,15.75V9.456A2.179,2.179,0,0,1,4.837,7.281q.954-.143,1.913-.247m10.5,0a48.536,48.536,0,0,0-10.5,0m10.5,0V3.375A1.125,1.125,0,0,0,16.125,2.25H7.875A1.125,1.125,0,0,0,6.75,3.375V7.034M18,10.5h.008v.008H18Zm-3,0h.008v.008H15Z"
                            transform="translate(-2.25 -1.5)" fill="none" class="stroke-current"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" />
                    </svg>
                </a>
            </li>
            <li>
                <a href="#modal-share" data-fancybox
                    class="text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors share-link text-decoration-none">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 19.644 21.016"
                        class="stroke-current">
                        <path id="Path_30" data-name="Path 30"
                            d="M7.217,10.907a2.25,2.25,0,1,0,0,2.186m0-2.186a2.252,2.252,0,0,1,0,2.186m0-2.186,9.566-5.314m-9.566,7.5,9.566,5.314m0,0a2.251,2.251,0,1,0,3.061-.875,2.251,2.251,0,0,0-3.061.875Zm0-12.814a2.25,2.25,0,1,0,.874-3.059,2.249,2.249,0,0,0-.874,3.059Z"
                            transform="translate(-2.25 -1.492)" fill="none" class="stroke-current"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" />
                    </svg>
                </a>
            </li>
        </ul>
    </div>
</div>

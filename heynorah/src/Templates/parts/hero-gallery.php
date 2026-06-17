<?php
/**
 * Template part: Hero Gallery
 * Displays image carousel/gallery with Embla
 * 
 * @var array $images
 */

use HeyNorah\Core\Plugin;
?>

<!-- Gallery Section -->
<?php if ($images): ?>
    <div class="h-full relative overflow-hidden section-embla group lg:h-[640px] min-h-[600px]">
        <div class="embla h-full overflow-hidden">
            <div class="embla__container h-full flex">
                <?php
                // Sort images: featured first
                usort($images, function ($a, $b) {
                    $a_featured = $a['isFeatured'] ?? false;
                    $b_featured = $b['isFeatured'] ?? false;
                    return $b_featured <=> $a_featured;
                });

                foreach ($images as $index => $image):
                    // Medium for display, large for Fancybox
                    $display_path = $image['sizes']['medium'] ?? ($image['sizes']['large'] ?? '');
                    $large_path = $image['sizes']['large'] ?? ($image['sizes']['original'] ?? '');
                    $alt_text = $image['alt'] ?? 'Boat Image';

                    if (empty($display_path) || empty($large_path))
                        continue;

                    $display_url = preg_match('#^https?://#i', (string) $display_path)
                        ? (string) $display_path
                        : trailingslashit(Plugin::CDN_BASE_URL) . ltrim((string) $display_path, '/');
                    $large_url = preg_match('#^https?://#i', (string) $large_path)
                        ? (string) $large_path
                        : trailingslashit(Plugin::CDN_BASE_URL) . ltrim((string) $large_path, '/');
                    ?>
                    <div class="embla__slide h-full flex-[0_0_100%] min-w-0">
                        <div class="embla__parallax h-full">
                            <div class="embla__parallax__layer h-full">
                                <a href="<?php echo esc_url($large_url); ?>" data-fancybox="gallery"
                                    data-caption="<?php echo esc_attr($alt_text); ?>"
                                    class="block h-full w-full">
                                    <img src="<?php echo esc_url($display_url); ?>"
                                        class="h-full w-full object-cover"
                                        alt="<?php echo esc_attr($alt_text); ?>">
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Navigation Buttons -->
        <button type="button"
            class="embla__button embla__prev absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 flex items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-md hover:bg-white/20 transition-all opacity-0 group-hover:opacity-100 z-20 cursor-pointer"
            aria-label="Previous slide">
            <i class="ti ti-chevron-left text-lg"></i>
        </button>
        <button type="button"
            class="embla__button embla__next absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 flex items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-md hover:bg-white/20 transition-all opacity-0 group-hover:opacity-100 z-20 cursor-pointer"
            aria-label="Next slide">
            <i class="ti ti-chevron-right text-lg"></i>
        </button>

        <button
            class="absolute bottom-6 right-6 z-[2] inline-flex items-center gap-2 px-4 py-2 bg-white/90 backdrop-blur text-base font-medium text-gray-900 rounded-lg shadow-lg hover:bg-white transition-colors"
            data-fancybox-trigger="gallery">
            <i class="ti ti-photo"></i> View <?php echo count($images); ?> Images
        </button>
    </div>
<?php endif; ?>

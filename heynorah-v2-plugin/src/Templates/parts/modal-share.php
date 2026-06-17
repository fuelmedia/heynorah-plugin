<?php
/**
 * Template part: Modal Share
 * Social sharing modal with Facebook, Twitter, Email, and copy link
 */
?>

<!-- Share Modal -->
<div id="modal-share" class="hidden p-8 text-center bg-white dark:bg-zinc-900 rounded-xl max-w-[400px] w-full! max-w-[480px]">
    <h4 class="mb-2 text-xl font-bold text-gray-900 dark:text-white">Share this Boat</h4>
    <div class="flex justify-center gap-4 mt-6">
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_permalink()); ?>"
            target="_blank"
            class="w-12 h-12 flex items-center justify-center rounded-full border border-blue-600 text-blue-600 hover:bg-blue-600 hover:text-white transition-colors">
            <i class="ti ti-brand-facebook text-lg"></i>
        </a>
        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(get_permalink()); ?>&text=Check+out+this+boat!"
            target="_blank"
            class="w-12 h-12 flex items-center justify-center rounded-full border border-black dark:border-white text-black dark:text-white hover:bg-black dark:hover:bg-white hover:text-white dark:hover:text-black transition-colors">
            <i class="ti ti-brand-x text-lg"></i>
        </a>
        <a href="mailto:?subject=Check out this inventory&body=<?php echo urlencode(get_permalink()); ?>"
            class="w-12 h-12 flex items-center justify-center rounded-full border border-gray-400 text-gray-400 hover:bg-gray-400 hover:text-white transition-colors">
            <i class="ti ti-mail text-lg"></i>
        </a>
    </div>
    <div class="mt-8">
        <p class="text-gray-500 dark:text-gray-400 text-sm mb-2">Or copy link</p>
        <div class="flex">
            <input type="text"
                class="w-full border border-gray-300 dark:border-zinc-700 rounded-l-lg px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-800 text-gray-700 dark:text-gray-300 focus:outline-none"
                value="<?php echo get_permalink(); ?>" readonly id="share-link-input">
            <button
                class="bg-gray-100 dark:bg-zinc-700 border border-l-0 border-gray-300 dark:border-zinc-600 rounded-r-lg px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-zinc-600 transition-colors"
                type="button"
                onclick="navigator.clipboard.writeText(document.getElementById('share-link-input').value); this.innerHTML='Copied!'; setTimeout(() => this.innerHTML='Copy', 2000);">Copy</button>
        </div>
    </div>
</div>

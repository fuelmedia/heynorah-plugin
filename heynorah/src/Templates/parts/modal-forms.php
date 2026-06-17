<?php
/**
 * Template part: Modal Forms
 * All Gravity Forms modals for contact, financing, appointments, etc.
 */

$settings_service = new \HeyNorah\Services\SettingsService();
$inquiry_form_id = $settings_service->get('inquiry_form_id');
$test_drive_form_id = $settings_service->get('test_drive_form_id');

$render_configured_form = static function (string $form_id, string $form_type): string {
    if (!shortcode_exists('gravityform')) {
        return '<p class="text-sm text-gray-600 dark:text-gray-300">Gravity Forms plugin is not active.</p>';
    }

    if ($form_id === '') {
        return '<p class="text-sm text-gray-600 dark:text-gray-300">No form is selected for ' . esc_html($form_type) . '. Configure it in HeyNorah admin panel.</p>';
    }

    if (!ctype_digit($form_id)) {
        return '<p class="text-sm text-red-600 dark:text-red-400">Invalid form configuration for ' . esc_html($form_type) . '.</p>';
    }

    return do_shortcode(sprintf('[gravityform id="%s" title="false" description="false" ajax="true"]', $form_id));
};
?>

<!-- Hidden Forms for Fancybox -->
<div id="modal-financing" class="hidden p-8 bg-white dark:bg-zinc-900 rounded-xl min-w-[500px] w-full !max-w-[600px]">
    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Apply for Financing</h3>
    <p class="text-gray-600 dark:text-gray-400 mb-6">Fill out the form below to apply for financing.</p>
    <?php echo do_shortcode('[gravityform id="1" title="false" description="false" ajax="true"]'); ?>
</div>

<div id="modal-request-info"
    class="hidden p-8 bg-white dark:bg-zinc-900 rounded-xl min-w-[500px] w-full !max-w-[600px]">
    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Request More Information</h3>
    <p class="text-gray-600 dark:text-gray-400 mb-6">Fill out the form below to request information.</p>
    <?php echo do_shortcode('[gravityform id="1" title="false" description="false" ajax="true"]'); ?>
</div>

<div id="modal-message-broker"
    class="hidden p-8 bg-white dark:bg-zinc-900 rounded-xl min-w-[500px] w-full !max-w-[600px]">
    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Send Message</h3>
    <p class="text-gray-600 dark:text-gray-400 mb-6">Fill out the form below to message a broker.</p>
    <?php echo do_shortcode('[gravityform id="1" title="false" description="false" ajax="true"]'); ?>
</div>

<div id="modal-appointment" class="hidden p-8 bg-white dark:bg-zinc-900 rounded-xl min-w-[500px] w-full !max-w-[600px]">
    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Make an Appointment</h3>
    <p class="text-gray-600 dark:text-gray-400 mb-6">Fill out the form below to request an appointment.</p>
    <?php echo do_shortcode('[gravityform id="1" title="false" description="false" ajax="true"]'); ?>
</div>

<div id="modal-inquiry" class="hidden p-8 bg-white dark:bg-zinc-900 rounded-xl min-w-[500px] w-full !max-w-[600px]">
    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Make an Inquiry</h3>
    <p class="text-gray-600 dark:text-gray-400 mb-6">Fill out the form below to request an inquiry.</p>
    <?php echo $render_configured_form($inquiry_form_id, 'inquiry'); ?>
</div>

<div id="modal-test-drive" class="hidden p-8 bg-white dark:bg-zinc-900 rounded-xl min-w-[500px] w-full !max-w-[600px]">
    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Request a Test Drive</h3>
    <p class="text-gray-600 dark:text-gray-400 mb-6">Fill out the form below to request a test drive.</p>
    <?php echo $render_configured_form($test_drive_form_id, 'test drive'); ?>
</div>

<?php
declare(strict_types=1);

namespace HeyNorah\Services;

use Exception;
use HeyNorah\Core\Plugin;
use HeyNorah\Utils\Encryption;

class InventorySyncService
{
    public function handle_webhook(string $event, array $data): void
    {
        error_log("InventorySyncService: handle_webhook() called with event: $event , data: " . print_r($data, true));
        try {
            switch ($event) {
                case "integration.test":
                case "webhook.test":
                    $this->test();
                    break;
                case 'inventory/item.created':
                case 'inventory/item.updated':
                case 'inventory/item.published':
                case 'inventory/item.duplicated':
                case 'inventory.item.created':
                case 'inventory.item.updated':
                case 'inventory.item.published':
                case 'inventory.item.duplicated':
                    $this->upsert_item($data);
                    break;

                case 'inventory/item.archived':
                case 'inventory.item.archived':
                    $archive_id = $this->resolve_item_id($data);
                    if (empty($archive_id)) {
                        throw new Exception("Missing item id for archived event");
                    }

                    if (!$this->is_pointer_payload($data)) {
                        $this->upsert_item($data);
                    }

                    $this->archive_item((string) $archive_id, $data);
                    break;

                case 'inventory/item.deleted':
                case 'inventory.item.deleted':
                    $delete_id = $this->resolve_item_id($data);

                    if (empty($delete_id)) {
                        throw new Exception("Missing item id for delete event");
                    }

                    $this->delete_item((string) $delete_id, $data);
                    break;

                case "organization/updated":
                case "organization.updated":
                    $this->update_organization($this->extract_payload_body($data));
                    break;

                default:
                    throw new Exception("Unknown event type: $event");
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    private function test(): void
    {

    }

    private function upsert_item(array $data): void
    {
        $original_payload = $data;
        $data = $this->normalize_item_payload($data);

        $heynorah_id = sanitize_text_field($this->resolve_item_id($data));

        if (empty($heynorah_id)) {
            throw new Exception("Missing item id for upsert event");
        }

        // Prefer upstream listing title; fallback to synthesized title
        $title_parts = array_filter([
            $data['brand']['name'] ?? null,
            $data['model']['name'] ?? null,
            $data['year'] ?? null
        ]);

        $upstream_title = sanitize_text_field((string) ($data['title'] ?? ($data['preparedListingTitle'] ?? '')));
        $fallback_title = !empty($title_parts)
            ? sanitize_text_field(implode(' ', $title_parts))
            : 'Untitled Item';
        $title = $upstream_title !== '' ? $upstream_title : $fallback_title;

        $incoming_slug = (string) ($data['slug'] ?? ($data['urlSlug'] ?? ''));
        $slug = $incoming_slug !== '' ? sanitize_title($incoming_slug) : sanitize_title($title);
        $status = $this->map_inventory_status_to_post_status((string) ($data['status'] ?? ''));

        $existing_post_id = $this->get_post_id_by_heynorah_id($heynorah_id);

        $post_args = [
            'post_title' => $title,
            'post_name' => $slug,
            'post_status' => $status,
            'post_type' => Plugin::CPT_INVENTORY,
            'post_content' => wp_kses_post($data['description'] ?? ''),
        ];

        if ($existing_post_id) {
            $post_args['ID'] = $existing_post_id;
            $post_id = wp_update_post($post_args);
        } else {
            $post_id = wp_insert_post($post_args);
        }

        if (is_wp_error($post_id)) {
            throw new Exception("WP Post Error: " . $post_id->get_error_message());
        }

        $actual_post_slug = (string) get_post_field('post_name', (int) $post_id, 'raw');
        update_post_meta($post_id, 'heynorah_requested_slug', $slug);
        update_post_meta($post_id, 'heynorah_wp_slug', $actual_post_slug);
        update_post_meta($post_id, 'heynorah_url_slug', $slug);

        if ($actual_post_slug !== '' && $actual_post_slug !== $slug) {
            error_log("InventorySyncService: Slug adjusted by WordPress. requested={$slug} actual={$actual_post_slug} post_id={$post_id}");
        }

        // Core identification
        update_post_meta($post_id, '_heynorah_id', $heynorah_id);

        delete_post_meta($post_id, 'heynorah_tombstone');
        delete_post_meta($post_id, 'heynorah_deleted_at');
        delete_post_meta($post_id, 'heynorah_archived_at');
        update_post_meta($post_id, 'heynorah_lifecycle_state', $status === 'publish' ? 'active' : 'draft');

        $this->clear_legacy_sync_meta($post_id);
        $this->clear_legacy_source_meta($post_id);

        // Basic item info
        update_post_meta($post_id, 'heynorah_stock_number', sanitize_text_field($data['stockNumber'] ?? ''));
        update_post_meta($post_id, 'heynorah_serial_number', sanitize_text_field($data['serialNumber'] ?? ''));
        update_post_meta($post_id, 'heynorah_year', isset($data['year']) ? (int) $data['year'] : '');
        update_post_meta($post_id, 'heynorah_condition', sanitize_text_field($data['condition'] ?? ''));
        update_post_meta($post_id, 'heynorah_visibility', sanitize_text_field($data['visibility'] ?? ''));

        // Dates
        update_post_meta($post_id, 'heynorah_created_at', sanitize_text_field($data['createdAt'] ?? ''));
        update_post_meta($post_id, 'heynorah_updated_at', sanitize_text_field($data['updatedAt'] ?? ''));
        update_post_meta($post_id, 'heynorah_published_at', sanitize_text_field($data['publishedAt'] ?? ''));
        update_post_meta($post_id, 'heynorah_expires_at', sanitize_text_field($data['expiresAt'] ?? ''));
        update_post_meta($post_id, 'heynorah_deleted_at', sanitize_text_field($data['deletedAt'] ?? ''));
        update_post_meta($post_id, 'heynorah_created_by_user_id', sanitize_text_field($data['createdByUserId'] ?? ''));
        update_post_meta($post_id, 'heynorah_updated_by_user_id', sanitize_text_field($data['updatedByUserId'] ?? ''));

        // Flags
        update_post_meta($post_id, 'heynorah_is_featured', (bool) ($data['isFeatured'] ?? false));
        update_post_meta($post_id, 'heynorah_is_ai_generated', (bool) ($data['isAiGenerated'] ?? false));
        update_post_meta($post_id, 'heynorah_live_video_tour', (bool) ($data['liveVideoTour'] ?? false));
        update_post_meta($post_id, 'heynorah_local_delivery', (bool) ($data['localDelivery'] ?? false));

        // Tags
        update_post_meta($post_id, 'heynorah_prepared_listing_title', sanitize_text_field($data['preparedListingTitle'] ?? ''));
        update_post_meta($post_id, 'heynorah_sales_tag', sanitize_text_field($data['salesTag'] ?? ''));

        // AI Confidence
        if (isset($data['aiConfidence'])) {
            update_post_meta($post_id, 'heynorah_ai_confidence', (float) $data['aiConfidence']);
        }

        // IDs
        update_post_meta($post_id, 'heynorah_organization_id', sanitize_text_field($data['organizationId'] ?? ''));
        update_post_meta($post_id, 'heynorah_brand_id', sanitize_text_field($data['brandId'] ?? ''));
        update_post_meta($post_id, 'heynorah_model_id', sanitize_text_field($data['modelId'] ?? ''));
        update_post_meta($post_id, 'heynorah_type_id', sanitize_text_field($data['typeId'] ?? ''));
        update_post_meta($post_id, 'heynorah_category_id', sanitize_text_field($data['categoryId'] ?? ''));
        update_post_meta(
            $post_id,
            'heynorah_category_ids',
            (isset($data['categoryIds']) && is_array($data['categoryIds'])) ? array_values($data['categoryIds']) : []
        );
        update_post_meta(
            $post_id,
            'heynorah_category_names',
            (isset($data['categoryNames']) && is_array($data['categoryNames'])) ? array_values($data['categoryNames']) : []
        );
        update_post_meta(
            $post_id,
            'heynorah_category_paths',
            (isset($data['categoryPaths']) && is_array($data['categoryPaths'])) ? array_values($data['categoryPaths']) : []
        );
        update_post_meta($post_id, 'heynorah_category_root_name', sanitize_text_field($data['categoryRootName'] ?? ''));
        update_post_meta($post_id, 'heynorah_industry_id', sanitize_text_field($data['industryId'] ?? ''));
        update_post_meta($post_id, 'heynorah_location_id', sanitize_text_field($data['locationId'] ?? ''));
        update_post_meta($post_id, 'heynorah_prefilled_from_id', sanitize_text_field($data['prefilledFromId'] ?? ''));
        update_post_meta($post_id, 'heynorah_origin', sanitize_text_field($data['origin'] ?? ''));
        update_post_meta($post_id, 'heynorah_broker_user_id_ref', sanitize_text_field($data['brokerUserId'] ?? ''));
        update_post_meta($post_id, 'heynorah_custom_field_values_json', sanitize_text_field($data['customFieldValuesJson'] ?? ''));

        if (isset($data['seoQualityScore'])) {
            update_post_meta($post_id, 'heynorah_seo_quality_score', (float) $data['seoQualityScore']);
        }
        update_post_meta($post_id, 'heynorah_seo_quality_analyzed_at', sanitize_text_field($data['seoQualityAnalyzedAt'] ?? ''));

        // Brand object
        $brand = is_array($data['brand'] ?? null) ? $data['brand'] : [];
        update_post_meta($post_id, 'heynorah_brand', $brand);
        update_post_meta($post_id, 'heynorah_brand_name', sanitize_text_field($brand['name'] ?? ''));
        update_post_meta($post_id, 'heynorah_brand_slug', sanitize_text_field($brand['slug'] ?? ''));
        update_post_meta($post_id, 'heynorah_brand_description', sanitize_text_field($brand['description'] ?? ''));
        update_post_meta($post_id, 'heynorah_brand_logo_url', esc_url_raw($brand['logoUrl'] ?? ''));
        update_post_meta($post_id, 'heynorah_brand_website', esc_url_raw($brand['website'] ?? ''));

        // Model object
        $model = is_array($data['model'] ?? null) ? $data['model'] : [];
        update_post_meta($post_id, 'heynorah_model', $model);
        update_post_meta($post_id, 'heynorah_model_name', sanitize_text_field($model['name'] ?? ''));
        update_post_meta($post_id, 'heynorah_model_slug', sanitize_text_field($model['slug'] ?? ''));
        update_post_meta($post_id, 'heynorah_model_description', sanitize_text_field($model['description'] ?? ''));
        update_post_meta($post_id, 'heynorah_model_is_active', (bool) ($model['isActive'] ?? false));
        update_post_meta($post_id, 'heynorah_model_year_from', isset($model['yearFrom']) ? (int) $model['yearFrom'] : '');
        update_post_meta($post_id, 'heynorah_model_year_to', isset($model['yearTo']) ? (int) $model['yearTo'] : '');

        // Type object
        $type = is_array($data['type'] ?? null) ? $data['type'] : [];
        update_post_meta($post_id, 'heynorah_type', $type);
        update_post_meta($post_id, 'heynorah_type_name', sanitize_text_field($type['name'] ?? ''));
        update_post_meta($post_id, 'heynorah_type_slug', sanitize_text_field($type['slug'] ?? ''));
        update_post_meta($post_id, 'heynorah_type_description', sanitize_text_field($type['description'] ?? ''));
        update_post_meta($post_id, 'heynorah_type_is_active', (bool) ($type['isActive'] ?? false));
        update_post_meta($post_id, 'heynorah_type_display_order', isset($type['displayOrder']) ? (int) $type['displayOrder'] : 0);

        // Broker object
        $broker = is_array($data['broker'] ?? null) ? $data['broker'] : [];
        update_post_meta($post_id, 'heynorah_broker', $broker);
        update_post_meta($post_id, 'heynorah_broker_name', sanitize_text_field($broker['name'] ?? ''));
        update_post_meta($post_id, 'heynorah_broker_email', sanitize_email($broker['email'] ?? ''));
        update_post_meta($post_id, 'heynorah_broker_phone', sanitize_text_field($broker['phone'] ?? ($broker['phoneNumber'] ?? '')));
        update_post_meta($post_id, 'heynorah_broker_role', sanitize_text_field($broker['role'] ?? 'broker'));
        update_post_meta($post_id, 'heynorah_broker_user_id', sanitize_text_field($broker['userId'] ?? ''));
        update_post_meta($post_id, 'heynorah_broker_first_name', sanitize_text_field($broker['firstName'] ?? ''));
        update_post_meta($post_id, 'heynorah_broker_last_name', sanitize_text_field($broker['lastName'] ?? ''));
        update_post_meta($post_id, 'heynorah_broker_picture_url', esc_url_raw($broker['pictureUrl'] ?? ''));
        update_post_meta($post_id, 'heynorah_broker_token_identifier', sanitize_text_field($broker['tokenIdentifier'] ?? ''));
        update_post_meta($post_id, 'heynorah_broker_workos_user_id', sanitize_text_field($broker['workosUserId'] ?? ''));

        $location = is_array($data['location'] ?? null) ? $data['location'] : [];
        update_post_meta($post_id, 'heynorah_location_name', sanitize_text_field($location['name'] ?? ''));
        update_post_meta($post_id, 'heynorah_location_title', sanitize_text_field($location['title'] ?? ''));
        update_post_meta($post_id, 'heynorah_location_address', sanitize_text_field($location['address'] ?? ''));
        update_post_meta($post_id, 'heynorah_location_city', sanitize_text_field($location['city'] ?? ''));
        update_post_meta($post_id, 'heynorah_location_state', sanitize_text_field($location['state'] ?? ''));
        update_post_meta($post_id, 'heynorah_location_zip_code', sanitize_text_field($location['zipCode'] ?? ''));
        update_post_meta($post_id, 'heynorah_location_country', sanitize_text_field($location['country'] ?? ''));
        update_post_meta($post_id, 'heynorah_location_phone', sanitize_text_field($location['phone'] ?? ($location['phoneNumber'] ?? '')));
        update_post_meta($post_id, 'heynorah_location_email', sanitize_email($location['email'] ?? ''));
        update_post_meta($post_id, 'heynorah_location_place_id', sanitize_text_field($location['placeId'] ?? ''));
        update_post_meta($post_id, 'heynorah_location_notes', sanitize_text_field($location['notes'] ?? ''));
        update_post_meta($post_id, 'heynorah_location_is_archived', !empty($location['isArchived']));
        update_post_meta($post_id, 'heynorah_location_name_normalized', sanitize_text_field($location['nameNormalized'] ?? ''));
        update_post_meta($post_id, 'heynorah_location_hours', sanitize_text_field($location['hours'] ?? ''));
        update_post_meta($post_id, 'heynorah_location_assigned_user_ids', is_array($location['assignedUserIds'] ?? null) ? $location['assignedUserIds'] : []);
        update_post_meta($post_id, 'heynorah_location_operating_hours', is_array($location['operatingHours'] ?? null) ? $location['operatingHours'] : []);

        // Price object
        $price = is_array($data['price'] ?? null) ? $data['price'] : [];
        update_post_meta($post_id, 'heynorah_price', $price);
        update_post_meta($post_id, 'heynorah_price_amount', isset($price['amount']) ? (float) $price['amount'] : 0);
        update_post_meta($post_id, 'heynorah_price_currency', sanitize_text_field($price['currency'] ?? 'USD'));
        update_post_meta($post_id, 'heynorah_price_type', sanitize_text_field($price['type'] ?? 'fixed'));
        update_post_meta($post_id, 'heynorah_price_hide_price', (bool) ($price['hidePrice'] ?? false));
        update_post_meta($post_id, 'heynorah_price_msrp', isset($price['msrp']) ? (float) $price['msrp'] : null);
        update_post_meta($post_id, 'heynorah_price_sale_amount', isset($price['saleAmount']) ? (float) $price['saleAmount'] : null);
        update_post_meta($post_id, 'heynorah_price_effective_price', isset($price['effectivePrice']) ? (float) $price['effectivePrice'] : null);

        // Media object
        if (isset($data['media']) && is_array($data['media'])) {
            update_post_meta($post_id, 'heynorah_media', $data['media']);

            $images = $data['media']['images'] ?? [];
            $featured_asset_id = (string) ($data['featuredAssetId'] ?? '');
            $featured_image = $this->find_featured_image($images, $featured_asset_id);

            if ($featured_image !== null) {
                $thumbnail_url = $this->resolve_media_url((string) (
                    $featured_image['sizes']['thumbnail'] ??
                    ($featured_image['sizes']['medium'] ??
                        ($featured_image['sizes']['large'] ??
                            ($featured_image['sizes']['original'] ?? '')))
                ));
                if ($thumbnail_url !== '') {
                    update_post_meta($post_id, 'heynorah_thumbnail_url', esc_url_raw($thumbnail_url));
                }

                $featured_image_url = $this->resolve_media_url((string) ($featured_image['sizes']['original'] ?? ''));
                if ($featured_image_url !== '') {
                    $this->set_featured_image_from_url(
                        $post_id,
                        $featured_image_url,
                        (string) ($featured_image['alt'] ?? ''),
                        (string) ($featured_image['key'] ?? '')
                    );
                }
            }
        }

        // Dynamic Attributes - stored as-is since they can change
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            update_post_meta($post_id, 'heynorah_attributes', $data['attributes']);
        }

        // SEO Meta
        update_post_meta($post_id, 'heynorah_meta_title', sanitize_text_field($data['metaTitle'] ?? ''));
        update_post_meta($post_id, 'heynorah_meta_description', sanitize_text_field($data['metaDescription'] ?? ''));
        update_post_meta($post_id, 'heynorah_disclaimer', wp_kses_post((string) ($data['disclaimer'] ?? '')));

        // Store complete raw data for reference
        update_post_meta($post_id, 'heynorah_raw_data', $data);
        update_post_meta($post_id, 'heynorah_raw_webhook_payload', $original_payload);
        $payload_body = $this->extract_payload_body($original_payload);
        update_post_meta($post_id, 'heynorah_source_general', is_array($payload_body['general'] ?? null) ? $payload_body['general'] : []);
        update_post_meta($post_id, 'heynorah_source_catalog', is_array($payload_body['catalog'] ?? null) ? $payload_body['catalog'] : []);
        update_post_meta($post_id, 'heynorah_source_advanced', is_array($payload_body['advanced'] ?? null) ? $payload_body['advanced'] : []);
        update_post_meta($post_id, 'heynorah_source_price', is_array($payload_body['price'] ?? null) ? $payload_body['price'] : []);
        update_post_meta($post_id, 'heynorah_source_seo', is_array($payload_body['seo'] ?? null) ? $payload_body['seo'] : []);
        update_post_meta($post_id, 'heynorah_source_location', is_array($payload_body['location'] ?? null) ? $payload_body['location'] : []);
        update_post_meta($post_id, 'heynorah_source_broker', is_array($payload_body['broker'] ?? null) ? $payload_body['broker'] : []);
        update_post_meta($post_id, 'heynorah_source_media', is_array($payload_body['media'] ?? null) ? $payload_body['media'] : []);
        update_post_meta($post_id, 'heynorah_source_documents', is_array($payload_body['documents'] ?? null) ? $payload_body['documents'] : []);

        // Assign taxonomy term based on industryId
        if (!empty($data['industryId'])) {
            $this->ensure_industry_term_exists($data);
            $this->assign_industry_term($post_id, $data['industryId']);
        }
    }

    private function normalize_item_payload(array $data): array
    {
        if ($this->has_legacy_inventory_shape($data)) {
            throw new Exception('Invalid payload schema: legacy inventory payload is no longer supported.');
        }

        $payload = $this->extract_payload_body($data);
        $general = is_array($payload['general'] ?? null) ? $payload['general'] : null;

        if ($general === null) {
            throw new Exception('Invalid payload schema: expected data.general in webhook payload.');
        }

        $catalog = is_array($payload['catalog'] ?? null) ? $payload['catalog'] : [];
        $price_payload = is_array($payload['price'] ?? null) ? $payload['price'] : [];
        $seo = is_array($payload['seo'] ?? null) ? $payload['seo'] : [];
        $location_payload = is_array($payload['location'] ?? null) ? $payload['location'] : [];
        $broker_payload = is_array($payload['broker'] ?? null) ? $payload['broker'] : [];
        $media_payload = is_array($payload['media'] ?? null) ? $payload['media'] : [];
        $documents_payload = is_array($payload['documents'] ?? null) ? $payload['documents'] : [];
        $advanced = is_array($payload['advanced'] ?? null) ? $payload['advanced'] : [];

        $item_id = sanitize_text_field((string) (
            $general['itemId'] ??
            ($data['resourceId'] ?? '')
        ));
        if ($item_id === '') {
            throw new Exception('Invalid payload schema: missing general.itemId or resourceId.');
        }

        $title = (string) ($general['title'] ?? ($general['name'] ?? ''));
        $name = (string) ($general['name'] ?? $title);
        $slug = sanitize_title((string) ($general['slug'] ?? $title));

        $brand = is_array($catalog['brand'] ?? null) ? $catalog['brand'] : [];
        $model = is_array($catalog['model'] ?? null) ? $catalog['model'] : [];
        $industry = is_array($catalog['industry'] ?? null) ? $catalog['industry'] : [];
        $categories = is_array($catalog['categories'] ?? null) ? $catalog['categories'] : [];
        $category_ids = is_array($catalog['categoryIds'] ?? null)
            ? array_values(array_filter($catalog['categoryIds'], static fn($value) => is_scalar($value) && trim((string) $value) !== ''))
            : [];
        $category_names = [];
        $category_paths = [];
        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }

            $category_name = trim((string) ($category['name'] ?? ''));
            if ($category_name !== '') {
                $category_names[] = $category_name;
                $category_paths[] = $category_name;
            }

            $category_id = trim((string) ($category['id'] ?? ''));
            if ($category_id !== '' && !in_array($category_id, $category_ids, true)) {
                $category_ids[] = $category_id;
            }
        }

        $primary_category_name = trim((string) ($catalog['categoryLabel'] ?? ($category_names[0] ?? '')));
        $primary_category_id = trim((string) (
            $category_ids[0] ??
            (($categories[0]['id'] ?? ''))
        ));
        if ($primary_category_name === '' && isset($categories[0]['name'])) {
            $primary_category_name = trim((string) $categories[0]['name']);
        }
        if (empty($category_names) && $primary_category_name !== '') {
            $category_names[] = $primary_category_name;
            $category_paths[] = $primary_category_name;
        }
        $category_root_name = $category_names[0] ?? $primary_category_name;

        $operating_hours = $this->normalize_schedule_days($location_payload['operatingHours']['days'] ?? ($location_payload['operatingHours'] ?? []));
        $working_hours = $this->normalize_schedule_days($broker_payload['workingHours']['days'] ?? ($broker_payload['workingHours'] ?? []));
        $location_address = is_array($location_payload['address'] ?? null) ? $location_payload['address'] : [];
        $broker_address = is_array($broker_payload['address'] ?? null) ? $broker_payload['address'] : [];
        $location_title = $this->build_location_title(
            (string) ($location_address['city'] ?? ''),
            (string) ($location_address['state'] ?? '')
        );
        if ($location_title === '') {
            $location_title = (string) ($location_payload['name'] ?? '');
        }

        $attributes = $this->normalize_custom_fields($advanced);
        $images = $this->normalize_media_images($payload);
        $videos = $this->normalize_media_videos($payload);
        $documents = $this->normalize_media_documents($payload);
        $featured_asset_id = (string) (
            $general['featuredAssetId'] ??
            ($media_payload['featuredAssetId'] ?? '')
        );
        $has_featured_image = $this->find_featured_image($images, $featured_asset_id) !== null;

        $default_currency = sanitize_text_field((string) ($price_payload['currency'] ?? 'USD'));
        $price_amount = $this->to_float($price_payload['amount'] ?? 0);
        $sale_amount = $this->to_nullable_float($price_payload['sale'] ?? null);
        $effective_price = $sale_amount !== null ? $sale_amount : $price_amount;

        $broker_name = (string) ($broker_payload['fullName'] ?? '');
        $broker_email = (string) ($broker_payload['email'] ?? '');
        $broker_phone = (string) ($broker_payload['phoneNumber'] ?? '');
        $broker_user_id = (string) ($broker_payload['linkedUserId'] ?? ($broker_payload['id'] ?? ''));
        $broker_picture_url = (string) ($broker_payload['avatarUrl'] ?? '');

        $location = [
            'id' => (string) ($location_payload['id'] ?? ''),
            'name' => (string) ($location_payload['name'] ?? ''),
            'address' => trim(implode(', ', array_filter([
                trim((string) ($location_address['line1'] ?? '')),
                trim((string) ($location_address['line2'] ?? '')),
            ]))),
            'title' => $location_title,
            'city' => (string) ($location_address['city'] ?? ''),
            'state' => (string) ($location_address['state'] ?? ''),
            'zipCode' => (string) ($location_address['postalCode'] ?? ''),
            'country' => (string) ($location_address['country'] ?? ''),
            'phone' => (string) ($location_payload['contactPhoneNumber'] ?? ''),
            'email' => (string) ($location_payload['contactEmail'] ?? ''),
            'streetNumber' => '',
            'street' => '',
            'placeId' => (string) ($location_address['placeId'] ?? ''),
            'notes' => (string) ($location_payload['notes'] ?? ''),
            'isArchived' => !empty($location_payload['isArchived']),
            'assignedUserIds' => [],
            'nameNormalized' => '',
            'operatingHours' => $operating_hours,
            'hours' => $this->format_operating_hours($operating_hours),
        ];

        return [
            'id' => $item_id,
            'name' => $name,
            'title' => $title,
            'urlSlug' => $slug,
            'slug' => $slug,
            'featuredAssetId' => $featured_asset_id,
            'status' => (string) ($general['status'] ?? 'draft'),
            'description' => (string) ($general['description'] ?? ''),
            'shortDescription' => (string) ($general['shortDescription'] ?? ($general['description'] ?? '')),
            'disclaimer' => (string) ($general['disclaimer'] ?? ''),
            'year' => (int) ($general['year'] ?? 0),
            'condition' => (string) ($general['condition'] ?? ''),
            'visibility' => 'public',
            'stockNumber' => (string) ($general['sku'] ?? ''),
            'sku' => (string) ($general['sku'] ?? ''),
            'serialNumber' => '',
            'customFieldValuesJson' => !empty($advanced) ? (string) wp_json_encode($advanced) : '',
            'createdAt' => $this->normalize_timestamp($general['createdAt'] ?? null),
            'createdByUserId' => (string) ($general['createdByUserId'] ?? ''),
            'updatedAt' => $this->normalize_timestamp($general['updatedAt'] ?? null),
            'updatedByUserId' => (string) ($general['updatedByUserId'] ?? ''),
            'publishedAt' => $this->normalize_timestamp($data['occurredAt'] ?? ($general['updatedAt'] ?? ($general['createdAt'] ?? null))),
            'expiresAt' => '',
            'deletedAt' => '',
            'isFeatured' => $has_featured_image,
            'isAiGenerated' => (string) ($general['origin'] ?? '') === 'ai_assisted',
            'liveVideoTour' => !empty($videos),
            'localDelivery' => false,
            'preparedListingTitle' => $title,
            'salesTag' => (string) ($general['salesTag'] ?? ''),
            'aiConfidence' => null,
            'seoQualityScore' => null,
            'seoQualityAnalyzedAt' => '',
            'organizationId' => (string) ($general['organizationId'] ?? ''),
            'brandId' => (string) ($brand['id'] ?? ''),
            'brandName' => (string) ($brand['name'] ?? ''),
            'modelId' => (string) ($model['id'] ?? ''),
            'modelName' => (string) ($model['name'] ?? ''),
            'typeId' => $primary_category_id,
            'categoryId' => $primary_category_id,
            'category' => $primary_category_name,
            'categoryName' => $primary_category_name,
            'categoryIds' => $category_ids,
            'categoryNames' => $category_names,
            'categoryPaths' => $category_paths,
            'categoryRootName' => $category_root_name,
            'industryId' => (string) ($industry['id'] ?? ''),
            'industryName' => (string) ($industry['name'] ?? ''),
            'industryCode' => (string) ($industry['code'] ?? ''),
            'locationId' => (string) ($location_payload['id'] ?? ''),
            'locationName' => (string) ($location_payload['name'] ?? ''),
            'locationTitle' => $location_title,
            'locationPhoneNumber' => (string) ($location_payload['contactPhoneNumber'] ?? ''),
            'locationEmail' => (string) ($location_payload['contactEmail'] ?? ''),
            'prefilledFromId' => '',
            'origin' => (string) ($general['origin'] ?? ''),
            'brokerUserId' => $broker_user_id,
            'brokerName' => $broker_name,
            'brokerEmail' => $broker_email,
            'brokerPhoneNumber' => $broker_phone,
            'brokerPictureUrl' => $broker_picture_url,
            'brand' => [
                'id' => (string) ($brand['id'] ?? ''),
                'name' => (string) ($brand['name'] ?? ''),
                'slug' => sanitize_title((string) ($brand['code'] ?? ($brand['name'] ?? ''))),
                'description' => '',
                'logoUrl' => '',
                'website' => '',
            ],
            'model' => [
                'id' => (string) ($model['id'] ?? ''),
                'name' => (string) ($model['name'] ?? ''),
                'slug' => sanitize_title((string) ($model['code'] ?? ($model['name'] ?? ''))),
                'description' => '',
                'isActive' => false,
                'yearFrom' => null,
                'yearTo' => null,
            ],
            'type' => [
                'id' => $primary_category_id,
                'name' => $primary_category_name,
                'slug' => sanitize_title($primary_category_name),
                'description' => '',
                'isActive' => false,
                'displayOrder' => 0,
            ],
            'industry' => [
                'id' => (string) ($industry['id'] ?? ''),
                'name' => (string) ($industry['name'] ?? ''),
                'slug' => sanitize_title((string) ($industry['code'] ?? ($industry['name'] ?? ''))),
            ],
            'broker' => [
                'id' => (string) ($broker_payload['id'] ?? ''),
                'name' => $broker_name,
                'email' => $broker_email,
                'phone' => $broker_phone,
                'phoneNumber' => $broker_phone,
                'role' => 'broker',
                'userId' => $broker_user_id,
                'firstName' => '',
                'lastName' => '',
                'pictureUrl' => $broker_picture_url,
                'tokenIdentifier' => '',
                'workosUserId' => '',
                'workingHours' => $working_hours,
                'address' => $broker_address,
            ],
            'location' => $location,
            'price' => [
                'amount' => $price_amount,
                'currency' => $default_currency,
                'type' => 'fixed',
                'hidePrice' => !empty($price_payload['hidePrice']) || !empty($general['hidePrice']),
                'msrp' => $this->to_nullable_float($price_payload['msrp'] ?? null),
                'saleAmount' => $sale_amount,
                'effectivePrice' => $effective_price,
            ],
            'defaultCurrency' => $default_currency,
            'priceAmount' => $price_amount,
            'saleAmount' => $sale_amount,
            'effectivePrice' => $effective_price,
            'msrp' => $this->to_nullable_float($price_payload['msrp'] ?? null),
            'hidePrice' => !empty($price_payload['hidePrice']) || !empty($general['hidePrice']),
            'media' => [
                'images' => $images,
                'videos' => $videos,
                'documents' => $documents,
            ],
            'attributes' => $attributes,
            'specifications' => [],
            'keyFeatures' => [],
            'metaTitle' => (string) ($seo['title'] ?? $title),
            'metaDescription' => (string) ($seo['description'] ?? ''),
            'seoKeywords' => is_array($seo['keywords'] ?? null) ? array_values($seo['keywords']) : [],
        ];
    }

    private function normalize_media_images(array $data): array
    {
        $payload = $this->extract_payload_body($data);
        $images = [];
        $media_images = is_array($payload['media']['images'] ?? null) ? $payload['media']['images'] : [];

        foreach ($media_images as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $original = $this->resolve_media_url((string) ($asset['publicUrl'] ?? ''));
            if ($original === '') {
                continue;
            }

            $images[] = [
                'id' => (string) ($asset['id'] ?? ''),
                'key' => (string) ($asset['storageAssetId'] ?? ($asset['id'] ?? '')),
                'isFeatured' => !empty($asset['isFeatured']),
                'order' => (int) ($asset['position'] ?? 0),
                'alt' => (string) ($asset['altText'] ?? ''),
                'caption' => (string) ($asset['caption'] ?? ''),
                'mimeType' => (string) ($asset['contentType'] ?? ''),
                'fileName' => (string) ($asset['fileName'] ?? ''),
                'sizes' => [
                    'original' => $original,
                    'large' => $this->resolve_media_url((string) ($asset['largeUrl'] ?? $original)),
                    'medium' => $this->resolve_media_url((string) ($asset['mediumUrl'] ?? ($asset['largeUrl'] ?? $original))),
                    'thumbnail' => $this->resolve_media_url((string) ($asset['thumbnailUrl'] ?? ($asset['mediumUrl'] ?? ($asset['largeUrl'] ?? $original)))),
                ],
            ];
        }

        usort($images, static function (array $left, array $right): int {
            $left_featured = !empty($left['isFeatured']) ? 1 : 0;
            $right_featured = !empty($right['isFeatured']) ? 1 : 0;
            if ($left_featured !== $right_featured) {
                return $right_featured <=> $left_featured;
            }

            return ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0));
        });

        return $images;
    }

    private function normalize_media_videos(array $data): array
    {
        $payload = $this->extract_payload_body($data);
        $videos = [];
        $media_videos = is_array($payload['media']['videos'] ?? null) ? $payload['media']['videos'] : [];

        foreach ($media_videos as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $original = $this->resolve_media_url((string) ($asset['publicUrl'] ?? ''));
            if ($original === '') {
                continue;
            }

            $videos[] = [
                'id' => (string) ($asset['id'] ?? ''),
                'key' => (string) ($asset['storageAssetId'] ?? ($asset['id'] ?? '')),
                'isFeatured' => !empty($asset['isFeatured']),
                'order' => (int) ($asset['position'] ?? 0),
                'alt' => (string) ($asset['altText'] ?? ''),
                'caption' => (string) ($asset['caption'] ?? ''),
                'mimeType' => (string) ($asset['contentType'] ?? ''),
                'fileName' => (string) ($asset['fileName'] ?? ''),
                'url' => $original,
                'thumbnail' => $this->resolve_media_url((string) ($asset['thumbnailUrl'] ?? '')),
            ];
        }

        usort($videos, static fn(array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));

        return $videos;
    }

    private function normalize_media_documents(array $data): array
    {
        $payload = $this->extract_payload_body($data);
        $documents = [];

        $items = is_array($payload['documents']['items'] ?? null) ? $payload['documents']['items'] : [];
        foreach ($items as $document) {
            if (!is_array($document)) {
                continue;
            }

            $url = $this->resolve_media_url((string) ($document['publicUrl'] ?? ''));
            if ($url === '') {
                continue;
            }

            $documents[] = [
                'id' => (string) ($document['id'] ?? ''),
                'key' => (string) ($document['storageAssetId'] ?? ($document['id'] ?? '')),
                'fileName' => (string) ($document['fileName'] ?? basename($url)),
                'mimeType' => (string) ($document['contentType'] ?? ''),
                'position' => (int) ($document['position'] ?? 0),
                'path' => $url,
                'url' => $url,
                'caption' => (string) ($document['caption'] ?? ''),
                'alt' => (string) ($document['altText'] ?? ''),
            ];
        }

        usort($documents, static fn(array $left, array $right): int => ((int) ($left['position'] ?? 0)) <=> ((int) ($right['position'] ?? 0)));

        return $documents;
    }

    private function normalize_custom_fields(array $custom_fields): array
    {
        $attributes = $custom_fields;

        $engines = [];
        if (isset($custom_fields['engines']) && is_array($custom_fields['engines'])) {
            foreach ($custom_fields['engines'] as $engine_row) {
                if (!is_array($engine_row)) {
                    continue;
                }

                $engine = [
                    'engine_make' => (string) ($engine_row['brand'] ?? ''),
                    'engine_model' => (string) ($engine_row['model'] ?? ''),
                    'engine_power' => (string) ($engine_row['power'] ?? ''),
                    'engine_fuel_type' => (string) ($engine_row['fuel_type'] ?? ($engine_row['fuelType'] ?? '')),
                    'engine_hours' => (string) ($engine_row['hours'] ?? ''),
                    'engine_year' => (string) ($engine_row['year_built'] ?? ($engine_row['yearBuilt'] ?? ($engine_row['year'] ?? ''))),
                    'engine_type' => (string) ($engine_row['type'] ?? ''),
                    'engine_drive_type' => (string) ($engine_row['drive_transmissions'] ?? ($engine_row['driveType'] ?? ($engine_row['drive_type'] ?? ''))),
                    'engine_location_on_vessel' => (string) ($engine_row['location'] ?? ''),
                    'engine_propeller_type' => (string) ($engine_row['propeller'] ?? ($engine_row['propellerType'] ?? ($engine_row['propeller_type'] ?? ''))),
                    'engine_propeller_material' => (string) ($engine_row['propeller_material'] ?? ''),
                ];

                if (
                    $engine['engine_make'] === '' &&
                    $engine['engine_model'] === '' &&
                    $engine['engine_power'] === ''
                ) {
                    continue;
                }

                $engines[] = $engine;
            }
        }

        if (!empty($engines)) {
            $attributes['engines'] = $engines;
            $attributes['number_of_engines'] = count($engines);
            if (!isset($attributes['fuel_type']) && !empty($engines[0]['engine_fuel_type'])) {
                $attributes['fuel_type'] = $engines[0]['engine_fuel_type'];
            }
        }

        if (!isset($attributes['total_engine_power'])) {
            if (isset($custom_fields['total_power'])) {
                $attributes['total_engine_power'] = $custom_fields['total_power'];
            } elseif (!empty($engines)) {
                $total_engine_power = 0.0;
                foreach ($engines as $engine) {
                    $total_engine_power += (float) ($engine['engine_power'] ?? 0);
                }
                if ($total_engine_power > 0) {
                    $attributes['total_engine_power'] = $total_engine_power;
                }
            }
        }

        if (!isset($attributes['hull_color']) && isset($custom_fields['exterior_color'])) {
            $attributes['hull_color'] = $custom_fields['exterior_color'];
        }

        if (!isset($attributes['inside_color']) && isset($custom_fields['interior_color'])) {
            $attributes['inside_color'] = $custom_fields['interior_color'];
        }

        if (!isset($attributes['fuel_capacity']) && isset($custom_fields['fuel_tanks_capacity'])) {
            $attributes['fuel_capacity'] = $custom_fields['fuel_tanks_capacity'];
        }

        if (!isset($attributes['dry_weight']) && isset($custom_fields['weight'])) {
            $attributes['dry_weight'] = $custom_fields['weight'];
        }

        if (!isset($attributes['engine_max_rpm']) && isset($custom_fields['max_rpm'])) {
            $attributes['engine_max_rpm'] = $custom_fields['max_rpm'];
        }

        if (!isset($attributes['max_passengers']) && isset($custom_fields['seating_capacity'])) {
            $attributes['max_passengers'] = $custom_fields['seating_capacity'];
        }

        if (!isset($attributes['guest_cabins']) && isset($custom_fields['number_of_cabins'])) {
            $attributes['guest_cabins'] = $custom_fields['number_of_cabins'];
        }

        if (!isset($attributes['guest_heads']) && isset($custom_fields['number_of_heads'])) {
            $attributes['guest_heads'] = $custom_fields['number_of_heads'];
        }

        $this->populate_dimension_attributes($attributes, $custom_fields, 'length_overall', 'length');
        $this->populate_dimension_attributes($attributes, $custom_fields, 'loa', 'loa');
        $this->populate_dimension_attributes($attributes, $custom_fields, 'length_on_deck', 'length_on_deck');
        $this->populate_dimension_attributes($attributes, $custom_fields, 'beam', 'beam');
        if (
            !isset($attributes['beam_ft']) &&
            !isset($attributes['beam_in']) &&
            $this->is_plausible_feet_measurement($custom_fields['width'] ?? null, 60.0)
        ) {
            $this->populate_dimension_attributes($attributes, $custom_fields, 'width', 'beam');
        }
        if ($this->is_plausible_feet_measurement($custom_fields['height'] ?? null, 80.0)) {
            $this->populate_dimension_attributes($attributes, $custom_fields, 'height', 'max_bridge_clearance');
        }

        return $attributes;
    }

    private function populate_dimension_attributes(array &$attributes, array $source, string $source_key, string $target_prefix): void
    {
        if (!array_key_exists($source_key, $source)) {
            return;
        }

        [$feet, $inches] = $this->parse_feet_inches($source[$source_key]);
        if ($feet !== null && !isset($attributes[$target_prefix . '_ft'])) {
            $attributes[$target_prefix . '_ft'] = $feet;
        }
        if ($inches !== null && !isset($attributes[$target_prefix . '_in'])) {
            $attributes[$target_prefix . '_in'] = $inches;
        }
    }

    private function parse_feet_inches($value): array
    {
        if ($value === null || $value === '') {
            return [null, null];
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;
            $feet = (int) floor($numeric);
            $inches = (int) round(($numeric - $feet) * 12);
            if ($inches >= 12) {
                $feet += intdiv($inches, 12);
                $inches = $inches % 12;
            }

            return [$feet, $inches];
        }

        $normalized = str_replace(['′', '″', '’', '“', '”'], ["'", '"', "'", '"', '"'], trim((string) $value));
        $matches = [];

        if (preg_match('/^(?<ft>\d+(?:\.\d+)?)\s*$/', $normalized, $matches)) {
            return $this->parse_feet_inches((float) $matches['ft']);
        }

        if (!preg_match('/^(?<ft>\d+)\s*\'(?:\s*(?<in>\d+(?:\.\d+)?)\s*")?$/', $normalized, $matches)) {
            return [null, null];
        }

        $feet = isset($matches['ft']) ? (int) $matches['ft'] : null;
        $inches = isset($matches['in']) && $matches['in'] !== '' ? (int) round((float) $matches['in']) : 0;

        if ($feet === null) {
            return [null, null];
        }

        if ($inches >= 12) {
            $feet += intdiv($inches, 12);
            $inches = $inches % 12;
        }

        return [$feet, $inches];
    }

    private function is_plausible_feet_measurement($value, float $max_feet): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;
            return $numeric > 0 && $numeric <= $max_feet;
        }

        [$feet] = $this->parse_feet_inches($value);
        return $feet !== null && $feet > 0 && $feet <= $max_feet;
    }

    private function normalize_timestamp($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;

            // Milliseconds to seconds conversion if needed
            if ($timestamp > 1000000000000) {
                $timestamp = (int) floor($timestamp / 1000);
            }

            return gmdate('c', $timestamp);
        }

        return sanitize_text_field((string) $value);
    }

    private function to_float($value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function to_nullable_float($value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function format_operating_hours(array $operating_hours): string
    {
        if (empty($operating_hours)) {
            return '';
        }

        $ordered_days = [
            'monday' => 'Mon',
            'tuesday' => 'Tue',
            'wednesday' => 'Wed',
            'thursday' => 'Thu',
            'friday' => 'Fri',
            'saturday' => 'Sat',
            'sunday' => 'Sun',
        ];

        $open_day_labels = [];
        $time_windows = [];

        foreach ($ordered_days as $day_key => $label) {
            $day_data = $operating_hours[$day_key] ?? null;
            if (!is_array($day_data) || empty($day_data['isOpen'])) {
                continue;
            }

            $open_day_labels[] = $label;

            $open_time = trim((string) ($day_data['openTime'] ?? ''));
            $close_time = trim((string) ($day_data['closeTime'] ?? ''));
            if ($open_time !== '' && $close_time !== '') {
                $time_windows[] = $open_time . '-' . $close_time;
            }
        }

        if (empty($open_day_labels)) {
            return '';
        }

        $first_day = $open_day_labels[0];
        $last_day = $open_day_labels[count($open_day_labels) - 1];
        $day_range = $first_day === $last_day ? $first_day : $first_day . '-' . $last_day;

        $time_window = '';
        if (!empty($time_windows)) {
            $unique_windows = array_values(array_unique($time_windows));
            $time_window = $unique_windows[0];
        }

        return trim($day_range . ' ' . $time_window);
    }

    private function is_pointer_payload(array $data): bool
    {
        $payload = $this->extract_payload_body($data);
        $has_id = isset($data['resourceId']) || isset($payload['itemId']) || isset($payload['id']) || isset($payload['general']['itemId']);
        if (!$has_id) {
            return false;
        }

        if ($this->has_legacy_inventory_shape($data)) {
            return false;
        }

        if (isset($payload['general']) && is_array($payload['general'])) {
            return false;
        }

        $full_fields = ['title', 'brand', 'model', 'price', 'media', 'attributes', 'catalog', 'location', 'broker'];

        foreach ($full_fields as $field) {
            if (isset($payload[$field])) {
                return false;
            }
        }

        return true;
    }

    private function archive_item(string $heynorah_id, array $data = []): void
    {
        $post_id = $this->get_post_id_by_heynorah_id($heynorah_id);

        if (!$post_id) {
            error_log("InventorySyncService: Archive requested for item {$heynorah_id} but no matching post found.");
            return;
        }

        wp_update_post([
            'ID' => $post_id,
            'post_status' => 'draft',
        ]);

        $archived_at = $this->normalize_timestamp(
            $data['occurredAt'] ?? ($data['emittedAt'] ?? ($data['updatedAt'] ?? null))
        );

        update_post_meta($post_id, 'heynorah_archived_at', $archived_at !== '' ? $archived_at : current_time('mysql'));
        update_post_meta($post_id, 'heynorah_lifecycle_state', 'archived');

        error_log("InventorySyncService: Archived item {$heynorah_id} mapped to post {$post_id} (set to draft).");
    }

    private function delete_item(string $heynorah_id, array $data = []): void
    {
        $post_id = $this->get_post_id_by_heynorah_id($heynorah_id);

        if ($post_id) {
            wp_update_post([
                'ID' => $post_id,
                'post_status' => 'draft'
            ]);
            $deleted_at = $this->normalize_timestamp(
                $data['deletedAt'] ?? ($data['occurredAt'] ?? ($data['emittedAt'] ?? ($data['updatedAt'] ?? null)))
            );
            update_post_meta($post_id, 'heynorah_deleted_at', $deleted_at !== '' ? $deleted_at : current_time('mysql'));
            update_post_meta($post_id, 'heynorah_tombstone', 1);
            update_post_meta($post_id, 'heynorah_lifecycle_state', 'deleted');
            error_log("InventorySyncService: Deleted item {$heynorah_id} mapped to post {$post_id} (set to draft).");
            return;
        }

        error_log("InventorySyncService: Delete requested for item {$heynorah_id} but no matching post found.");
    }

    private function get_post_id_by_heynorah_id(string $id): ?int
    {
        $posts = get_posts([
            'post_type' => Plugin::CPT_INVENTORY,
            'meta_key' => '_heynorah_id',
            'meta_value' => $id,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'post_status' => 'any'
        ]);

        return !empty($posts) ? (int) $posts[0] : null;
    }

    private function resolve_item_id(array $data): string
    {
        $payload = $this->extract_payload_body($data);

        return sanitize_text_field((string) (
            $payload['id'] ??
            $payload['itemId'] ??
            $payload['general']['itemId'] ??
            $data['resourceId'] ??
            ''
        ));
    }

    private function extract_payload_body(array $payload): array
    {
        return is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
    }

    private function has_legacy_inventory_shape(array $payload): bool
    {
        $body = $this->extract_payload_body($payload);

        return isset($body['inventoryItem']) || isset($body['inventorySnapshot']) || isset($body['searchDocument']);
    }

    private function map_inventory_status_to_post_status(string $status): string
    {
        $normalized = strtolower(trim($status));

        return in_array($normalized, ['active', 'published'], true) ? 'publish' : 'draft';
    }

    private function clear_legacy_sync_meta(int $post_id): void
    {
        $keys = [
            'heynorah_meilisearch_sync_status',
            'heynorah_meilisearch_synced_at',
            'heynorah_meilisearch_sync_error',
            'heynorah_webhook_sync_status',
            'heynorah_webhook_synced_at',
            'heynorah_webhook_sync_error',
            'heynorah_webhook_attempts',
            'heynorah_sync_search_document_exists',
            'heynorah_sync_search_index_uid',
            'heynorah_sync_search_updated_at',
            'heynorah_sync_website_endpoint_key',
            'heynorah_sync_website_last_event_id',
            'heynorah_sync_website_last_http_status',
            'heynorah_sync_website_updated_at',
            'heynorah_sync_website_last_acknowledged_at',
            'heynorah_sync_website_remote_id',
        ];

        foreach ($keys as $key) {
            delete_post_meta($post_id, $key);
        }
    }

    private function clear_legacy_source_meta(int $post_id): void
    {
        $keys = [
            'heynorah_source_inventory_item',
            'heynorah_source_inventory_snapshot',
            'heynorah_source_search_document',
            'heynorah_source_general',
            'heynorah_source_catalog',
            'heynorah_source_advanced',
            'heynorah_source_price',
            'heynorah_source_seo',
            'heynorah_source_location',
            'heynorah_source_broker',
            'heynorah_source_media',
            'heynorah_source_documents',
        ];

        foreach ($keys as $key) {
            delete_post_meta($post_id, $key);
        }
    }

    private function normalize_schedule_days($days): array
    {
        if (!is_array($days)) {
            return [];
        }

        $normalized = [];

        if (isset($days['days']) && is_array($days['days'])) {
            $days = $days['days'];
        }

        if ($this->is_list_array($days)) {
            foreach ($days as $day) {
                if (!is_array($day)) {
                    continue;
                }

                $day_key = sanitize_key((string) ($day['day'] ?? ''));
                if ($day_key === '') {
                    continue;
                }

                $normalized[$day_key] = [
                    'openTime' => $day['openTime'] ?? null,
                    'closeTime' => $day['closeTime'] ?? null,
                    'isOpen' => !empty($day['isOpen']),
                ];
            }

            return $normalized;
        }

        foreach ($days as $day_key => $day) {
            if (!is_array($day)) {
                continue;
            }

            $normalized[sanitize_key((string) $day_key)] = [
                'openTime' => $day['openTime'] ?? null,
                'closeTime' => $day['closeTime'] ?? null,
                'isOpen' => !empty($day['isOpen']),
            ];
        }

        return $normalized;
    }

    private function is_list_array(array $value): bool
    {
        $expected_key = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $expected_key) {
                return false;
            }
            $expected_key++;
        }

        return true;
    }

    private function build_location_title(string $city, string $state): string
    {
        return trim(implode(', ', array_filter([trim($city), trim($state)])));
    }

    private function resolve_media_url(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        return trailingslashit(Plugin::CDN_BASE_URL) . ltrim($value, '/');
    }

    private function find_featured_image(array $images, string $featured_asset_id): ?array
    {
        foreach ($images as $image) {
            if (!is_array($image)) {
                continue;
            }

            if ($featured_asset_id !== '' && (string) ($image['key'] ?? '') === $featured_asset_id) {
                return $image;
            }
        }

        foreach ($images as $image) {
            if (is_array($image) && !empty($image['isFeatured'])) {
                return $image;
            }
        }

        return isset($images[0]) && is_array($images[0]) ? $images[0] : null;
    }

    private function set_featured_image_from_url(int $post_id, string $image_url, string $alt_text = '', string $image_key = ''): void
    {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Check if we already have an attachment for this image key/URL on this post
        $existing_attachment_id = get_post_meta($post_id, '_heynorah_featured_image_attachment_id', true);
        $stored_image_key = get_post_meta($post_id, '_heynorah_featured_image_key', true);

        // If attachment exists on this post and the image key matches, just update metadata and return
        if ($existing_attachment_id && get_post($existing_attachment_id) && $stored_image_key === $image_key && !empty($image_key)) {
            // Update alt text if changed
            if (!empty($alt_text)) {
                update_post_meta($existing_attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
            }

            // Ensure it's still set as featured image
            set_post_thumbnail($post_id, $existing_attachment_id);
            return;
        }

        // Check if this image key already exists in media library (from other posts)
        if (!empty($image_key)) {
            $existing_global_attachment = $this->get_attachment_by_image_key($image_key);
            if ($existing_global_attachment) {
                // Reuse existing attachment
                set_post_thumbnail($post_id, $existing_global_attachment);

                // Update alt text if changed
                if (!empty($alt_text)) {
                    update_post_meta($existing_global_attachment, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
                }

                // Store attachment ID and image key for this post
                update_post_meta($post_id, '_heynorah_featured_image_attachment_id', $existing_global_attachment);
                update_post_meta($post_id, '_heynorah_featured_image_key', $image_key);

                // Delete old attachment if it exists and is different
                if ($existing_attachment_id && $existing_attachment_id != $existing_global_attachment) {
                    wp_delete_attachment($existing_attachment_id, true);
                }

                return;
            }
        }

        // Image doesn't exist anywhere - download new one
        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) {
            return;
        }

        $file_array = [
            'name' => basename($image_url),
            'tmp_name' => $tmp
        ];

        // Upload the file
        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            @unlink($file_array['tmp_name']);
            return;
        }

        // Set alt text
        if (!empty($alt_text)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
        }

        // Store the image key on the attachment itself for global lookup
        if (!empty($image_key)) {
            update_post_meta($attachment_id, '_heynorah_image_key', $image_key);
        }

        // Set as featured image
        set_post_thumbnail($post_id, $attachment_id);

        // Store attachment ID and image key for future reference
        update_post_meta($post_id, '_heynorah_featured_image_attachment_id', $attachment_id);
        update_post_meta($post_id, '_heynorah_featured_image_key', $image_key);

        // Delete old attachment if it exists and is different
        if ($existing_attachment_id && $existing_attachment_id != $attachment_id) {
            wp_delete_attachment($existing_attachment_id, true);
        }
    }

    private function get_attachment_by_image_key(string $image_key): ?int
    {
        $attachments = get_posts([
            'post_type' => 'attachment',
            'meta_key' => '_heynorah_image_key',
            'meta_value' => $image_key,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'post_status' => 'any'
        ]);

        return !empty($attachments) ? (int) $attachments[0] : null;
    }

    private function update_organization(array $organization_data): void
    {
        global $wpdb;
        $table = $wpdb->prefix . Plugin::TABLE_SETTINGS;

        // Ensure settings row exists
        $settings = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i ORDER BY id DESC LIMIT 1", $table), ARRAY_A);

        if (!$settings) {
            error_log('InventorySyncService: Settings row not found, cannot update organization');
            return;
        }

        $settings_id = (int) ($settings['id'] ?? 0);
        if ($settings_id <= 0) {
            error_log('InventorySyncService: Settings row ID missing, cannot update organization');
            return;
        }

        // Encrypt and save organization data
        $org_json = wp_json_encode($organization_data);
        $encrypted_org_data = Encryption::encrypt($org_json);

        // Update organization data
        $wpdb->update(
            $table,
            [
                'organization_data' => $encrypted_org_data,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $settings_id],
            ['%s', '%s'],
            ['%d']
        );

        // Sync industries taxonomy from either supported payload shape
        $industries = [];
        if (isset($organization_data['industries']) && is_array($organization_data['industries'])) {
            $industries = $organization_data['industries'];
        } elseif (isset($organization_data['organizationIndustries']) && is_array($organization_data['organizationIndustries'])) {
            foreach ($organization_data['organizationIndustries'] as $org_industry) {
                if (!is_array($org_industry) || !isset($org_industry['industry']) || !is_array($org_industry['industry'])) {
                    continue;
                }

                $industry = $org_industry['industry'];
                $industries[] = [
                    'id' => $industry['id'] ?? '',
                    'name' => $industry['name'] ?? '',
                    'slug' => $industry['slug'] ?? ($industry['code'] ?? ''),
                ];
            }
        }

        if (!empty($industries)) {
            $this->sync_industries_taxonomy($industries);
        }

        error_log('InventorySyncService: Organization data updated successfully');
    }

    private function sync_industries_taxonomy(array $industries): void
    {
        if (empty($industries)) {
            return;
        }

        foreach ($industries as $industry) {
            $industry_id = $industry['id'] ?? '';
            $industry_name = $industry['name'] ?? '';
            $industry_slug = $industry['slug'] ?? '';

            if (empty($industry_name) || empty($industry_slug) || empty($industry_id)) {
                continue;
            }

            // Check if term already exists
            $term = term_exists($industry_slug, Plugin::TAX_INDUSTRY);

            if ($term === 0 || $term === null) {
                // Term doesn't exist, create it
                $result = wp_insert_term(
                    $industry_name,
                    Plugin::TAX_INDUSTRY,
                    [
                        'slug' => $industry_slug,
                    ]
                );

                if (!is_wp_error($result)) {
                    // Save industry ID as term meta
                    update_term_meta($result['term_id'], 'heynorah_industry_id', $industry_id);
                }
            } else {
                // Term exists, update it if needed
                wp_update_term(
                    $term['term_id'],
                    Plugin::TAX_INDUSTRY,
                    [
                        'name' => $industry_name,
                        'slug' => $industry_slug,
                    ]
                );

                // Update term meta
                update_term_meta($term['term_id'], 'heynorah_industry_id', $industry_id);
            }
        }
    }

    private function assign_industry_term(int $post_id, string $industry_id): void
    {
        // Find taxonomy term by industry_id meta
        $terms = get_terms([
            'taxonomy' => Plugin::TAX_INDUSTRY,
            'meta_key' => 'heynorah_industry_id',
            'meta_value' => $industry_id,
            'hide_empty' => false,
            'number' => 1,
        ]);

        if (!empty($terms) && !is_wp_error($terms)) {
            $term = $terms[0];
            // Assign term to post
            wp_set_object_terms($post_id, $term->term_id, Plugin::TAX_INDUSTRY, false);
            error_log("InventorySyncService: Assigned term '{$term->name}' (ID: {$term->term_id}) to post {$post_id}");
        } else {
            // Term not found, log warning
            error_log("InventorySyncService: Warning - No taxonomy term found for industryId: {$industry_id}");
        }
    }

    private function ensure_industry_term_exists(array $data): void
    {
        $industry_id = sanitize_text_field((string) ($data['industryId'] ?? ''));
        if (empty($industry_id)) {
            return;
        }

        $existing_terms = get_terms([
            'taxonomy' => Plugin::TAX_INDUSTRY,
            'meta_key' => 'heynorah_industry_id',
            'meta_value' => $industry_id,
            'hide_empty' => false,
            'number' => 1,
        ]);

        if (!empty($existing_terms) && !is_wp_error($existing_terms)) {
            return;
        }

        $industry_data = is_array($data['industry'] ?? null) ? $data['industry'] : [];
        $industry_name = sanitize_text_field((string) ($industry_data['name'] ?? ''));
        $industry_slug = sanitize_title((string) ($industry_data['slug'] ?? ''));

        if (empty($industry_name)) {
            $industry_name = sanitize_text_field((string) ($data['type']['name'] ?? ''));
        }
        if (empty($industry_slug)) {
            $industry_slug = sanitize_title($industry_name);
        }

        if (empty($industry_name)) {
            error_log("InventorySyncService: Cannot auto-create taxonomy term, missing industry name for industryId: {$industry_id}");
            return;
        }

        $term = !empty($industry_slug)
            ? term_exists($industry_slug, Plugin::TAX_INDUSTRY)
            : 0;

        if ($term === 0 || $term === null) {
            $inserted = wp_insert_term(
                $industry_name,
                Plugin::TAX_INDUSTRY,
                ['slug' => $industry_slug]
            );

            if (is_wp_error($inserted)) {
                error_log("InventorySyncService: Failed to create taxonomy term for industryId {$industry_id}: " . $inserted->get_error_message());
                return;
            }

            update_term_meta($inserted['term_id'], 'heynorah_industry_id', $industry_id);
            error_log("InventorySyncService: Auto-created taxonomy term '{$industry_name}' for industryId {$industry_id}");
            return;
        }

        $term_id = (int) $term['term_id'];
        update_term_meta($term_id, 'heynorah_industry_id', $industry_id);
        if (!empty($industry_name)) {
            wp_update_term($term_id, Plugin::TAX_INDUSTRY, ['name' => $industry_name]);
        }
        error_log("InventorySyncService: Updated taxonomy meta for existing term #{$term_id} and industryId {$industry_id}");
    }

}

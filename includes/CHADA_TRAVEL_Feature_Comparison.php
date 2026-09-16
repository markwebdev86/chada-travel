<?php
/** Renders the Free-owned edition-aware feature screen. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

final class CHADA_TRAVEL_Feature_Comparison {
    public const MENU_SLUG = 'chada-travel-features';
    public const CAPABILITY = 'manage_chada_travel_visa_settings';

    /**
     * Returns the comparison columns shown on the administrator screen.
     *
     * @return array{feature: string, free: string, pro?: string}
     */
    public static function get_column_labels(bool $chada_travel_include_pro = true): array {
        $chada_travel_columns = [
            'feature' => __('Feature', 'chada-travel'),
            'free' => __('Free', 'chada-travel'),
            'pro' => __('Pro', 'chada-travel'),
        ];
        if (!$chada_travel_include_pro) {
            unset($chada_travel_columns['pro']);
        }
        return $chada_travel_columns;
    }

    /**
     * Returns the current edition matrix. Pro includes every Free feature and adds independent extensions.
     *
     * @return array<int, array{title: string, features: array<int, array{name: string, description: string, free: bool, pro: bool, pro_only: bool}>}>
     */
    public static function get_feature_sections(bool $chada_travel_include_pro = true): array {
        $chada_travel_shared = static fn(string $chada_travel_name, string $chada_travel_description): array => [
            'name' => $chada_travel_name,
            'description' => $chada_travel_description,
            'free' => true,
            'pro' => true,
            'pro_only' => false,
        ];
        $chada_travel_pro_only = static fn(string $chada_travel_name, string $chada_travel_description): array => [
            'name' => $chada_travel_name,
            'description' => $chada_travel_description,
            'free' => false,
            'pro' => true,
            'pro_only' => true,
        ];

        $chada_travel_sections = [
            [
                'title' => __('Core Tour and Visa Operations', 'chada-travel'),
                'features' => [
                    $chada_travel_shared(__('Tour Management', 'chada-travel'), __('Create, edit, duplicate, archive, restore, search, filter, and publish Tours with travel dates, pricing, downloadable files, and public detail pages.', 'chada-travel')),
                    $chada_travel_shared(__('Built-in Tour Destinations and default Tour Types', 'chada-travel'), __('Use the built-in Tour taxonomy records in public Tour navigation, filters, and Tour forms.', 'chada-travel')),
                    $chada_travel_pro_only(__('Custom Tour Type Management', 'chada-travel'), __('Create, edit, archive, restore, and delete additional Tour Types while preserving existing Tour relationships.', 'chada-travel')),
                    $chada_travel_pro_only(__('Tours Export and Import', 'chada-travel'), __('Transfer complete Tour snapshots between sites with versioned XLSX workbooks, portable media, preview validation, and atomic imports.', 'chada-travel')),
                    $chada_travel_shared(__('Featured Tours', 'chada-travel'), __('Display selected Tours through public Featured Tours shortcodes and layouts.', 'chada-travel')),
                    $chada_travel_shared(__('Tour Search', 'chada-travel'), __('Provide public Tour Search forms, results, filters, and pagination.', 'chada-travel')),
                    $chada_travel_shared(__('Tour Featured Image Processing', 'chada-travel'), __('Validate, process, crop, and safely name Tour Featured Images.', 'chada-travel')),
                    $chada_travel_shared(__('Visa Countries', 'chada-travel'), __('Manage countries, fees, guide content, requirements, images, and public Visa Country pages.', 'chada-travel')),
                    $chada_travel_shared(__('Visa Checkout', 'chada-travel'), __('Provide staged customer checkout, applicant details, country selection, review, payment, confirmation, and document release.', 'chada-travel')),
                    $chada_travel_shared(__('Manual Payments', 'chada-travel'), __('Support Bank Payment and Digital Wallet configuration, checkout submission, proof upload, administrator review, confirmation, rejection, and retry.', 'chada-travel')),
                    $chada_travel_shared(__('Public REST API', 'chada-travel'), __('Provide checkout, manual-payment, confirmation, proof-upload, document-list, and document-upload endpoints under chada-travel/v1.', 'chada-travel')),
                ],
            ],
            [
                'title' => __('Administration and Settings', 'chada-travel'),
                'features' => [
                    $chada_travel_shared(__('Dashboard', 'chada-travel'), __('Provide an administrator overview of plugin activity and operational status.', 'chada-travel')),
                    $chada_travel_shared(__('Booking Administration', 'chada-travel'), __('Review, filter, archive, restore, inspect, and update Visa Bookings and applicants while preserving audit history.', 'chada-travel')),
                    $chada_travel_shared(__('Documents and Uploads', 'chada-travel'), __('Configure document requirements, issue secure upload links, review submissions, and serve protected files.', 'chada-travel')),
                    $chada_travel_shared(__('Tour Settings', 'chada-travel'), __('Configure Tour currency and Featured Image processing defaults.', 'chada-travel')),
                    $chada_travel_shared(__('Visa Countries Settings', 'chada-travel'), __('Configure Visa Country guide image processing defaults.', 'chada-travel')),
                    $chada_travel_shared(__('Consent and Policy Controls', 'chada-travel'), __('Configure policy pages, consent snapshots, transaction disclaimers, and policy-version tracking.', 'chada-travel')),
                    $chada_travel_shared(__('Audit History', 'chada-travel'), __('Record significant booking, payment, document, settings, and security events in append-only audit history.', 'chada-travel')),
                    $chada_travel_shared(__('Email Notifications', 'chada-travel'), __('Configure sender and administrator notification settings and send workflow notifications and test messages.', 'chada-travel')),
                    $chada_travel_shared(__('Regional Settings', 'chada-travel'), __('Configure company country, address, timezone, currency, and date and time formats manually.', 'chada-travel')),
                ],
            ],
            [
                'title' => __('Independent Pro Services', 'chada-travel'),
                'features' => [
                    $chada_travel_pro_only(__('Record Grid Library', 'chada-travel'), __('Use server-side grids for Applicants, Applications, Requirements, Documents, Payments, Tags, Assignments, and Audit Events with search, filters, pagination, CRUD, and logs.', 'chada-travel')),
                    $chada_travel_pro_only(__('Record Tags and Internal Notes', 'chada-travel'), __('Add record tags, tag assignments, internal booking notes, and Booking Detail extensions.', 'chada-travel')),
                    $chada_travel_pro_only(__('Booking Workflow Settings', 'chada-travel'), __('Configure booking draft expiry, applicant limits, completed-booking auto-archive, cleanup, schedule status, and last-run results.', 'chada-travel')),
                    $chada_travel_pro_only(__('Data and Privacy Settings', 'chada-travel'), __('Configure retention policy, eligibility preview, archive, anonymization, token cleanup, and result history.', 'chada-travel')),
                    $chada_travel_pro_only(__('PayPal', 'chada-travel'), __('Add PayPal settings, checkout handling, REST routes, webhook verification, payment states, and provider assets in Pro.', 'chada-travel')),
                    $chada_travel_pro_only(__('PayMongo', 'chada-travel'), __('Add PayMongo settings, checkout handling, REST routes, webhook verification, payment states, and provider assets in Pro.', 'chada-travel')),
                    $chada_travel_pro_only(__('Booking Workflow Automation', 'chada-travel'), __('Run bounded archive and expired-draft cleanup jobs with safe cron registration and deactivation cleanup.', 'chada-travel')),
                    $chada_travel_pro_only(__('Retention and Anonymization', 'chada-travel'), __('Add retention review, archive, anonymization safeguards, previews, and protected-file and token cleanup workflows.', 'chada-travel')),
                    $chada_travel_pro_only(__('Interactive Administrator Tour', 'chada-travel'), __('Add the interactive administrator tour and its locally bundled Driver.js assets.', 'chada-travel')),
                ],
            ],
            [
                'title' => __('Platform and Extension Contracts', 'chada-travel'),
                'features' => [
                    $chada_travel_shared(__('Extension Contracts', 'chada-travel'), __('Expose stable extension actions and filters for providers, menus, Settings, REST, Dashboard, automation, booking details, and templates.', 'chada-travel')),
                ],
            ],
        ];
        if ($chada_travel_include_pro) {
            return $chada_travel_sections;
        }
        $chada_travel_free_sections = [];
        foreach ($chada_travel_sections as $chada_travel_section) {
            $chada_travel_section['features'] = array_values(array_filter(
                $chada_travel_section['features'],
                static fn(array $chada_travel_feature): bool => empty($chada_travel_feature['pro_only'])
            ));
            if ($chada_travel_section['features'] !== []) {
                $chada_travel_free_sections[] = $chada_travel_section;
            }
        }
        return $chada_travel_free_sections;
    }

    /** Renders the protected administrator comparison page. */
    public static function render_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to view the feature comparison.', 'chada-travel'));
        }
        $chada_travel_pro_active = CHADA_TRAVEL_Extension_Manager::is_pro_active();
        $chada_travel_columns = self::get_column_labels($chada_travel_pro_active);
        $chada_travel_sections = self::get_feature_sections($chada_travel_pro_active);
        $chada_travel_column_count = $chada_travel_pro_active ? 3 : 2;
        $chada_travel_edition = $chada_travel_pro_active ? 'pro' : 'free';
        $chada_travel_caption = $chada_travel_pro_active
            ? __('Chada Travel - Agency Manager feature comparison', 'chada-travel')
            : __('Chada Travel - Agency Manager Free feature list', 'chada-travel');
        $chada_travel_description = $chada_travel_pro_active
            ? __('Review the included Free features and the independent Pro extensions.', 'chada-travel')
            : __('Review the features included in the Chada Travel Free version.', 'chada-travel');
        ?>
        <div class="wrap chada-travel-admin chada-travel-features">
            <h1><?php echo esc_html__('Features', 'chada-travel'); ?></h1>
            <p class="description"><?php echo esc_html($chada_travel_description); ?></p>
            <div class="chada-travel-features__table-wrap">
                <table class="widefat fixed striped chada-travel-features__table chada-travel-features__table--<?php echo esc_attr($chada_travel_edition); ?>">
                    <caption class="screen-reader-text"><?php echo esc_html($chada_travel_caption); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php echo esc_html($chada_travel_columns['feature']); ?></th>
                            <th scope="col" class="chada-travel-features__status-column"><?php echo esc_html($chada_travel_columns['free']); ?></th>
                            <?php if ($chada_travel_pro_active) : ?>
                            <th scope="col" class="chada-travel-features__status-column"><?php echo esc_html($chada_travel_columns['pro']); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($chada_travel_sections as $chada_travel_section) : ?>
                            <tr class="chada-travel-features__section">
                                <th scope="rowgroup" colspan="<?php echo esc_attr((string) $chada_travel_column_count); ?>"><?php echo esc_html($chada_travel_section['title']); ?></th>
                            </tr>
                            <?php foreach ($chada_travel_section['features'] as $chada_travel_feature) : ?>
                            <tr<?php echo !empty($chada_travel_feature['pro_only']) ? ' class="chada-travel-features__pro-only"' : ''; ?>>
                                <th scope="row">
                                        <span class="chada-travel-features__name"><?php echo esc_html($chada_travel_feature['name']); ?><?php if (!empty($chada_travel_feature['pro_only'])) : ?><span class="chada-travel-features__pro-only-label"><?php echo esc_html__('Pro only', 'chada-travel'); ?></span><?php endif; ?></span>
                                        <span class="chada-travel-features__description"><?php echo esc_html($chada_travel_feature['description']); ?></span>
                                    </th>
                                    <td class="chada-travel-features__status-column"><?php self::render_status($chada_travel_feature['free']); ?></td>
                                    <?php if ($chada_travel_pro_active) : ?>
                                    <td class="chada-travel-features__status-column"><?php self::render_status($chada_travel_feature['pro']); ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /** Renders an accessible availability indicator. */
    private static function render_status(bool $chada_travel_available): void {
        $chada_travel_label = $chada_travel_available
            ? __('Available', 'chada-travel')
            : __('Not included', 'chada-travel');
        $chada_travel_class = $chada_travel_available ? 'is-available' : 'is-unavailable';
        $chada_travel_symbol = $chada_travel_available ? '✓' : '×';
        echo '<span class="chada-travel-features__status ' . esc_attr($chada_travel_class) . '" aria-label="'
            . esc_attr($chada_travel_label) . '"><span aria-hidden="true">' . esc_html($chada_travel_symbol)
            . '</span><span class="screen-reader-text">' . esc_html($chada_travel_label) . '</span></span>';
    }
}

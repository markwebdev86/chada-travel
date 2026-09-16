<?php
/**
 * Non-destructive default option values and the default Visa Countries catalog.
 *
 * The initial country catalog is a 51-country list (alphabetical by name), seeded archived (is_active = 0) with
 * placeholder processing fee/checklist values an administrator fills in before activating each country. The
 * site's own Company Country (Settings -> General) is excluded from the seeded set. Existing administrator
 * records are always preserved.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Seeder {
    private const TOUR_DESTINATIONS = [
        'Africa', 'Antarctica', 'Asia', 'Australia', 'Europe', 'Mediterranean', 'Middle East',
        'North America', 'Oceania',
        'South America', 'South East Asia',
    ];
    private const TOUR_TYPES = [
        'Adventure Tour', 'Bike Ride Tour', 'City Tour', 'Cultural & Heritage Tour', 'Eco & Sustainable Tour',
        'Eco-Adventure Tour', 'Hiking Tour', 'Historical Monuments Tour', 'Luxury Life Tour', 'Refreshing Tour',
        'Wellness and Leisure Tour', 'Bus Tour', 'Pilgrimage Tour', 'Company or Industrial Tour', 'Educational Tour',
    ];

    /** @return list<string> */
    public static function get_default_tour_destination_slugs(): array {
        return array_map([CHADA_TRAVEL_Tour_Term_Repository::class, 'slug'], self::TOUR_DESTINATIONS);
    }

    /** @return list<string> */
    public static function get_default_tour_type_slugs(): array {
        return array_map([CHADA_TRAVEL_Tour_Term_Repository::class, 'slug'], self::TOUR_TYPES);
    }
    /**
     * The default Visa Countries catalog: ISO 3166-1 alpha-2 code + name, ordered alphabetically by name.
     *
     * @var list<array{code: string, name: string}>
     */
    private const COUNTRY_CATALOG = [
        ['code' => 'AR', 'name' => 'Argentina'],
        ['code' => 'AU', 'name' => 'Australia'],
        ['code' => 'AT', 'name' => 'Austria'],
        ['code' => 'BE', 'name' => 'Belgium'],
        ['code' => 'BT', 'name' => 'Bhutan'],
        ['code' => 'BR', 'name' => 'Brazil'],
        ['code' => 'CA', 'name' => 'Canada'],
        ['code' => 'CN', 'name' => 'China'],
        ['code' => 'DK', 'name' => 'Denmark'],
        ['code' => 'FI', 'name' => 'Finland'],
        ['code' => 'FR', 'name' => 'France'],
        ['code' => 'DE', 'name' => 'Germany'],
        ['code' => 'GR', 'name' => 'Greece'],
        ['code' => 'HU', 'name' => 'Hungary'],
        ['code' => 'IS', 'name' => 'Iceland'],
        ['code' => 'ID', 'name' => 'Indonesia'],
        ['code' => 'IE', 'name' => 'Ireland'],
        ['code' => 'IL', 'name' => 'Israel'],
        ['code' => 'IT', 'name' => 'Italy'],
        ['code' => 'JP', 'name' => 'Japan'],
        ['code' => 'JO', 'name' => 'Jordan'],
        ['code' => 'KW', 'name' => 'Kuwait'],
        ['code' => 'LV', 'name' => 'Latvia'],
        ['code' => 'LT', 'name' => 'Lithuania'],
        ['code' => 'MY', 'name' => 'Malaysia'],
        ['code' => 'MX', 'name' => 'Mexico'],
        ['code' => 'NP', 'name' => 'Nepal'],
        ['code' => 'NL', 'name' => 'Netherlands'],
        ['code' => 'NZ', 'name' => 'New Zealand'],
        ['code' => 'NO', 'name' => 'Norway'],
        ['code' => 'PH', 'name' => 'Philippines'],
        ['code' => 'PL', 'name' => 'Poland'],
        ['code' => 'PT', 'name' => 'Portugal'],
        ['code' => 'QA', 'name' => 'Qatar'],
        ['code' => 'RU', 'name' => 'Russia'],
        ['code' => 'SA', 'name' => 'Saudi Arabia'],
        ['code' => 'SG', 'name' => 'Singapore'],
        ['code' => 'ZA', 'name' => 'South Africa'],
        ['code' => 'KR', 'name' => 'South Korea'],
        ['code' => 'ES', 'name' => 'Spain'],
        ['code' => 'SE', 'name' => 'Sweden'],
        ['code' => 'CH', 'name' => 'Switzerland'],
        ['code' => 'TW', 'name' => 'Taiwan'],
        ['code' => 'TH', 'name' => 'Thailand'],
        ['code' => 'TR', 'name' => 'Türkiye'],
        ['code' => 'UA', 'name' => 'Ukraine'],
        ['code' => 'AE', 'name' => 'United Arab Emirates'],
        ['code' => 'GB', 'name' => 'United Kingdom'],
        ['code' => 'US', 'name' => 'United States of America'],
        ['code' => 'VA', 'name' => 'Vatican City'],
        ['code' => 'VN', 'name' => 'Vietnam'],
    ];

    /**
     * Returns the default Visa Countries catalog (alphabetical by name), optionally excluding one country code -
     * used to skip the site's own Company Country so it never appears as a visa destination for itself.
     *
     * @return list<array<string, mixed>>
     */
    public static function get_initial_country_records(
        string $chada_travel_currency = CHADA_TRAVEL_Config::DEFAULT_CURRENCY,
        string $chada_travel_exclude_country_code = ''
    ): array {
        $chada_travel_currency = CHADA_TRAVEL_Config::sanitize_currency($chada_travel_currency);
        $chada_travel_exclude_country_code = strtoupper(trim($chada_travel_exclude_country_code));
        $chada_travel_records = [];
        $chada_travel_sort_order = 0;
        foreach (self::COUNTRY_CATALOG as $chada_travel_country) {
            if ($chada_travel_country['code'] === $chada_travel_exclude_country_code) {
                continue;
            }
            $chada_travel_records[] = [
                'code' => $chada_travel_country['code'],
                'name' => $chada_travel_country['name'],
                'sort_order' => ++$chada_travel_sort_order,
                'processing_fee' => '0.00',
                'currency' => $chada_travel_currency,
                'checklist_version' => $chada_travel_country['code'] . '-DEFAULT',
                'full_details' => '',
                'guide_attachment_id' => 0,
                'checklist_attachment_id' => 0,
                'is_active' => 0,
            ];
        }
        return $chada_travel_records;
    }

    /**
     * Returns the generic MVP visa-document requirement set applied to every seeded country's checklist version.
     * These are example/testing requirement labels for Stage 5 upload testing, the same caveat already used for
     * the initial country processing fees; do not treat them as confirmed production checklist content.
     *
     * @return list<array<string, mixed>>
     */
    public static function get_initial_requirement_records(): array {
        return [
            [
                'label' => 'Valid Passport', 'description' => 'Passport bio page must be clearly legible.',
                'is_required' => true, 'mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
                'max_bytes' => CHADA_TRAVEL_Config::DEFAULT_UPLOAD_MAX_BYTES, 'sort_order' => 1,
            ],
            [
                'label' => '2x2 ID Photo',
                'description' => 'White background, taken within the last 6 months.',
                'is_required' => true, 'mime_types' => ['image/jpeg', 'image/png'],
                'max_bytes' => CHADA_TRAVEL_Config::DEFAULT_UPLOAD_MAX_BYTES, 'sort_order' => 2,
            ],
            [
                'label' => 'Proof of Financial Capacity',
                'description' => 'Bank certificate or latest 3-month bank statement.',
                'is_required' => true, 'mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
                'max_bytes' => CHADA_TRAVEL_Config::DEFAULT_UPLOAD_MAX_BYTES, 'sort_order' => 3,
            ],
        ];
    }

    /** Seeds missing Tour Destinations and Tour Types without overwriting owner edits. */
    public static function seed_tour_terms(object $chada_travel_wpdb): void {
        foreach ([
            CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION => self::TOUR_DESTINATIONS,
            CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE => self::TOUR_TYPES,
        ] as $chada_travel_taxonomy => $chada_travel_names) {
            foreach ($chada_travel_names as $chada_travel_sort_order => $chada_travel_name) {
                $chada_travel_slug = CHADA_TRAVEL_Tour_Term_Repository::slug($chada_travel_name);
                $chada_travel_exists = $chada_travel_wpdb->get_var($chada_travel_wpdb->prepare(
                    'SELECT chada_travel_tour_term_id FROM ' . CHADA_TRAVEL_Tour_Term_Repository::table($chada_travel_wpdb)
                        . ' WHERE chada_travel_taxonomy = %s AND chada_travel_term_slug = %s LIMIT 1',
                    $chada_travel_taxonomy,
                    $chada_travel_slug
                ));
                if ($chada_travel_exists) {
                    continue;
                }
                $chada_travel_now = gmdate('Y-m-d H:i:s');
                $chada_travel_wpdb->query($chada_travel_wpdb->prepare(
                    'INSERT IGNORE INTO ' . CHADA_TRAVEL_Tour_Term_Repository::table($chada_travel_wpdb)
                        . ' (chada_travel_taxonomy, chada_travel_term_name, chada_travel_term_slug, chada_travel_is_active,'
                        . ' chada_travel_is_default, chada_travel_sort_order, chada_travel_created_at, chada_travel_updated_at)'
                        . ' VALUES (%s, %s, %s, %d, %d, %d, %s, %s)',
                    $chada_travel_taxonomy,
                    $chada_travel_name,
                    $chada_travel_slug,
                    1,
                    $chada_travel_taxonomy === CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE ? 1 : 0,
                    $chada_travel_sort_order + 1,
                    $chada_travel_now,
                    $chada_travel_now
                ));
            }
        }
        self::mark_default_tour_types($chada_travel_wpdb);
    }

    /** Marks known built-in Tour Types during upgrades without changing administrator-managed fields. */
    private static function mark_default_tour_types(object $chada_travel_wpdb): void {
        $chada_travel_slugs = self::get_default_tour_type_slugs();
        $chada_travel_placeholders = implode(',', array_fill(0, count($chada_travel_slugs), '%s'));
        $chada_travel_sql = 'UPDATE ' . CHADA_TRAVEL_Tour_Term_Repository::table($chada_travel_wpdb)
            . ' SET chada_travel_is_default = 1 WHERE chada_travel_taxonomy = %s AND chada_travel_term_slug IN (' . $chada_travel_placeholders . ')';
        $chada_travel_wpdb->query($chada_travel_wpdb->prepare(
            $chada_travel_sql,
            array_merge([CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE], $chada_travel_slugs)
        ));
    }

    /**
     * Mirrors `chada_travel_country_fees` into the `chada_travel_countries` table (needed for the document requirements FK)
     * and, only for a country/checklist_version that currently has zero requirement rows, seeds the generic MVP
     * requirement set above. Never overwrites administrator-managed requirement rows.
     */
    public static function seed_country_catalog(object $chada_travel_wpdb): void {
        $chada_travel_country_fees = (array) get_option('chada_travel_country_fees', []);
        CHADA_TRAVEL_Country_Repository::sync_all($chada_travel_wpdb, $chada_travel_country_fees);

        $chada_travel_currency        = (string) get_option('chada_travel_currency', CHADA_TRAVEL_Config::DEFAULT_CURRENCY);
        $chada_travel_company_country = (string) get_option('chada_travel_company_country', 'PH');
        foreach (self::get_initial_country_records($chada_travel_currency, $chada_travel_company_country) as $chada_travel_seed_country) {
            $chada_travel_country_id = CHADA_TRAVEL_Country_Repository::find_id_by_code($chada_travel_wpdb, $chada_travel_seed_country['code']);
            if ($chada_travel_country_id <= 0) {
                continue;
            }
            $chada_travel_current_version = self::current_checklist_version(
                $chada_travel_country_fees,
                $chada_travel_seed_country['code'],
                (string) $chada_travel_seed_country['checklist_version']
            );
            $chada_travel_has_requirements = CHADA_TRAVEL_Requirement_Repository::version_has_requirements(
                $chada_travel_wpdb,
                $chada_travel_country_id,
                $chada_travel_current_version
            );
            if ($chada_travel_has_requirements) {
                continue;
            }
            foreach (self::get_initial_requirement_records() as $chada_travel_requirement) {
                CHADA_TRAVEL_Requirement_Repository::insert($chada_travel_wpdb, $chada_travel_country_id, array_merge($chada_travel_requirement, [
                    'checklist_version' => $chada_travel_current_version,
                ]));
            }
        }
    }

    /** @param list<array<string, mixed>> $chada_travel_country_fees */
    private static function current_checklist_version(
        array $chada_travel_country_fees,
        string $chada_travel_code,
        string $chada_travel_fallback_version
    ): string {
        foreach ($chada_travel_country_fees as $chada_travel_country) {
            if ((string) ($chada_travel_country['code'] ?? '') === $chada_travel_code) {
                return (string) ($chada_travel_country['checklist_version'] ?? $chada_travel_fallback_version);
            }
        }
        return $chada_travel_fallback_version;
    }

    /** @return array<string, mixed> */
    public static function get_option_defaults(): array {
        return array_merge(CHADA_TRAVEL_Config::get_free_option_defaults(), CHADA_TRAVEL_SMTP_Settings::get_option_defaults(), [
            'chada_travel_country_fees' => self::get_initial_country_records(),
            'chada_travel_status_labels' => CHADA_TRAVEL_Workflow::get_status_labels(),
        ]);
    }

    /** Adds missing defaults and initial countries without overwriting administrator-managed country records. */
    public static function seed_options(): void {
        $chada_travel_defaults = self::get_option_defaults();
        unset($chada_travel_defaults['chada_travel_country_fees']);
        foreach ($chada_travel_defaults as $chada_travel_option_name => $chada_travel_default_value) {
            add_option($chada_travel_option_name, $chada_travel_default_value, '', false);
        }
        $chada_travel_currency        = (string) get_option('chada_travel_currency', CHADA_TRAVEL_Config::DEFAULT_CURRENCY);
        $chada_travel_company_country = (string) get_option('chada_travel_company_country', 'PH');
        add_option(
            'chada_travel_country_fees',
            self::get_initial_country_records($chada_travel_currency, $chada_travel_company_country),
            '',
            false
        );
        $chada_travel_existing_countries = (array) get_option('chada_travel_country_fees', []);
        $chada_travel_merged_countries   = self::merge_country_records(
            $chada_travel_existing_countries,
            $chada_travel_currency,
            $chada_travel_company_country
        );
        if ($chada_travel_existing_countries !== $chada_travel_merged_countries) {
            update_option('chada_travel_country_fees', $chada_travel_merged_countries, false);
        }
    }

    /**
     * Adds missing initial countries after existing records so administrator changes and display order remain intact.
     *
     * @param list<array<string, mixed>> $chada_travel_existing_countries
     * @param string                     $chada_travel_currency Existing site's configured transaction currency.
     * @param string                     $chada_travel_exclude_country_code Skips this country code (the site's own
     *                                   Company Country) when adding missing catalog entries.
     * @return list<array<string, mixed>>
     */
    public static function merge_country_records(
        array $chada_travel_existing_countries,
        string $chada_travel_currency = CHADA_TRAVEL_Config::DEFAULT_CURRENCY,
        string $chada_travel_exclude_country_code = ''
    ): array {
        $chada_travel_codes          = [];
        $chada_travel_next_sort_order = 0;
        $chada_travel_currency        = CHADA_TRAVEL_Config::sanitize_currency($chada_travel_currency);
        foreach ($chada_travel_existing_countries as $chada_travel_country) {
            $chada_travel_code = strtoupper((string) ($chada_travel_country['code'] ?? ''));
            if ($chada_travel_code !== '') {
                $chada_travel_codes[$chada_travel_code] = true;
            }
            $chada_travel_next_sort_order = max($chada_travel_next_sort_order, (int) ($chada_travel_country['sort_order'] ?? 0));
        }
        foreach (self::get_initial_country_records($chada_travel_currency, $chada_travel_exclude_country_code) as $chada_travel_country) {
            if (isset($chada_travel_codes[$chada_travel_country['code']])) {
                continue;
            }
            $chada_travel_country['sort_order'] = ++$chada_travel_next_sort_order;
            $chada_travel_country['currency']   = $chada_travel_currency;
            $chada_travel_existing_countries[]  = $chada_travel_country;
        }
        return array_values($chada_travel_existing_countries);
    }
}

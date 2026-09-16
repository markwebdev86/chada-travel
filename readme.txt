=== Chada Travel - Agency Manager ===
Contributors: markwebdev86
Tags: travel agency, visa, tours, bookings, payments
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage Tours, Visa Countries, customer applications, bookings, documents, checkout, and manual payments in WordPress.

== Description ==

Chada Travel - Agency Manager is a standalone GPL-licensed WordPress plugin for travel agencies. The stable 1.0.0 tag
is available as the WordPress.org release artifact.

The plugin provides:

* Tour creation, editing, built-in Tour Destinations and Tour Types, Featured Tours, Tour Search, public Tour pages, and image processing.
* Visa Country and guide management with configurable fees, requirements, and public country information.
* A staged customer checkout for visa applications, Bank Payment, and Digital Wallet payments.
* Booking review, payment-proof review, document uploads, protected files, email notifications, and audit history.
* Administrator settings for company information, pages, policies, payments, documents, Tours, Visa Countries, and email.
* An administrator Features screen describing the capabilities available in this release.

Bank Accounts, Travel Dates per Tour, and Downloadable Files are core workflows. Required fields, valid ISO dates,
permitted media types, configured upload-size ceilings, authorization, and protected-file controls remain enforced
server-side. When the plugin is loaded or activated, it requests a 300-second PHP execution and socket-runtime budget
for the current request where the host permits it. Higher host values are preserved; persistent PHP, upload, web-server,
and proxy limits remain hosting-configuration responsibilities, and explicit request timeouts remain bounded.

The separately distributed Pro add-on provides Tours Export and Import through the shared `taapp_tours_workbook` XLSX
v2 contract, including Tour Destinations, Tour Types, Travel Dates, Downloadable Files, and portable media. Free keeps
the core Tour data and the `chada_travel_admin_tours_transfer_html` extension seam; it does not load Pro transfer logic.

Activation adds only missing canonical Tour Destination and Tour Type records. It preserves administrator edits, archived
records, custom records, and existing Tour relationships. Repeated activation is safe and does not reset existing data.

== Installation ==

1. Download the stable ZIP release from WordPress.org.
2. In WordPress, open **Plugins > Add New Plugin > Upload Plugin** and upload the ZIP.
3. Activate **Chada Travel - Agency Manager** through the **Plugins** menu.
4. Open **Chada Travel > Settings** and complete the General, Policies & Consent, Payment Method, Pages & Links,
   Documents & Uploads, Tours, Visa Countries, and Email settings.
5. Add the supplied shortcodes to the pages assigned in **Pages & Links**.
6. Create Tours with the supplied built-in Tour Types.

Regional values are configured manually in General Settings. The plugin does not require a third-party regional lookup
service and does not send installer or customer data to any third-party regional lookup.

The plugin stores payment proofs and Visa documents in protected plugin-managed storage and serves them only through
capability- or token-authorized workflows.

== Frequently Asked Questions ==

= Which payment methods are included? =

Bank Payment and Digital Wallet are included. Both require administrator review before protected resources are released.

= How are regional settings configured? =

Administrators configure country, timezone, currency, and date and time formats manually in General Settings. No
third-party regional lookup service is required.

= Which Tour Types are included? =

The plugin includes 15 built-in Tour Types: Adventure, Bike Ride, Bus, City, Company or Industrial, Cultural & Heritage,
Eco & Sustainable, Eco-Adventure, Educational, Hiking, Historical Monuments, Luxury Life, Pilgrimage, Refreshing, and
Wellness and Leisure.

= Which shortcodes are available? =

Use `[chada_travel_visa_application]` for the staged visa checkout. Other available shortcodes include
`[chada_travel_payment_proof]`, `[chada_travel_visa_documents]`, `[chada_travel_tour_search_form]`,
`[chada_travel_tour_search_results]`, `[chada_travel_featured_tours_sidebar rows="3"]`, and
`[chada_travel_featured_tours design="bg-image" rows="1" columns="3"]`.

= Where are customer uploads stored? =

Payment proofs and Visa documents are stored in protected plugin-managed storage and served only by authorized workflows.

= Does Chada Travel change PHP execution time or timeout settings? =

It requests a 300-second execution and socket budget for the current PHP request when active. It does not write hosting
configuration files or change persistent PHP, upload, web-server, or proxy limits. The Dashboard readiness notice reports
when the current PHP values remain below the requested budget.

== Screenshots ==

1. Visa Country guide and public country information workflow.
2. Staged visa checkout with payment and document-upload steps.
3. Tour management, Tour Search, and public Tour detail workflow.

== Changelog ==

= 1.0.0 =

* Initial release.
* Added Tours, taxonomies, Featured Tours, Tour Search, image processing, visa checkout, manual payments, document workflows, consent, security controls, and audit history.
* Added Dashboard checkout-readiness notifications for incomplete setup and operational settings.
* Added manual regional country, timezone, currency, and date and time formatting settings.
* Added sequential taxonomy row numbers and public Tour links from the administrator tables.

== Upgrade Notice ==

= 1.0.0 =

Initial WordPress.org release. Complete the Settings tabs and review the generated workflow pages after activation.

== Support ==

* [WordPress.org support forum](https://wordpress.org/support/plugin/chada-travel/)
* [GitHub Issues](https://github.com/markwebdev86/chada-travel/issues)

When reporting an issue, include the plugin version, WordPress version, PHP version, steps to reproduce, and relevant
non-sensitive error details. Do not post protected customer documents or private site information.

== License ==

Chada Travel - Agency Manager is licensed under the GNU General Public License, version 2 or later.

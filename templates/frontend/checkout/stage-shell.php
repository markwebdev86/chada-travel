<?php
/**
 * Generic Stage 1-5 <section> wrapper, reused by every checkout stage. See CHADA_TRAVEL_Checkout::render_stage().
 *
 * @var int $chada_travel_stage
 * @var string $chada_travel_hidden_attr_html Either '' or ' hidden'; code-owned fixed literal, not user data.
 * @var string $chada_travel_heading_class_attr_html Either '' or ' class="chada-travel-sr-only"'; code-owned, not user data.
 * @var string $chada_travel_heading_html Pre-escaped "Stage N: Label" heading text.
 * @var string $chada_travel_body_html Pre-built HTML for this stage's own content.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_stage));
assert(isset($chada_travel_hidden_attr_html));
assert(isset($chada_travel_heading_class_attr_html));
assert(isset($chada_travel_heading_html));
assert(isset($chada_travel_body_html));

$chada_travel_section_open_html = '<section class="chada-travel-screen" id="chada-travel-stage-' . (int) $chada_travel_stage
    . '" data-chada-travel-stage="' . (int) $chada_travel_stage . '"' . $chada_travel_hidden_attr_html . '>';
$chada_travel_heading_open_html = '<h2' . $chada_travel_heading_class_attr_html . '>';
?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from an int cast and code-owned fixed literals, not user data. ?>
<?php echo $chada_travel_section_open_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from a code-owned fixed literal, not user data. ?>
<?php echo $chada_travel_heading_open_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_heading_html; ?>
</h2>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_body_html; ?>
</section>

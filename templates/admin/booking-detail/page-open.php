<?php
/**
 * Static "<div class='wrap chada-travel-admin'>" page open for Booking Detail. Unlike the list-screen
 * templates/admin/shared/page-header.php, this screen's heading is not static/immediately-following markup - it is
 * CHADA_TRAVEL_Admin_Bookings::booking_label()'s dynamic result, deep inside header.php - so this template only opens the
 * wrap div. CHADA_TRAVEL_Admin_Booking_Detail::render() closes it itself once all four tab panels have rendered.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;
?>
<div class="wrap chada-travel-admin">

<?php
// Reusable HTML pieces. Every dynamic value goes through e().

/** The PASS GO wordmark (text-based, so it stays sharp at any size). */
function logo(): string
{
    return '<span class="logo" role="img" aria-label="PASS GO">'
        . '<span class="logo-pass">PASS</span><span class="logo-go">GO</span>'
        . '<span class="logo-check">' . icon('check', 12) . '</span></span>';
}

function badge(string $text, string $variant = '', string $iconName = ''): string
{
    return '<span class="badge' . ($variant !== '' ? ' badge-' . e($variant) : '') . '">'
        . ($iconName !== '' ? icon($iconName, 14) : '') . e($text) . '</span>';
}

/** A tappable card for one service in a list. */
function service_card(array $s, string $href): string
{
    $html = '<a class="card card-link service-card" href="' . e($href) . '">';
    $html .= '<span class="card-icon">' . icon('landmark', 22) . '</span>';
    $html .= '<span class="card-body">';
    $html .= '<span class="card-title">' . e($s['name']) . '</span>';
    $html .= '<span class="card-sub">' . e($s['institution_name']) . '</span>';
    $html .= '<span class="badges">' . badge($s['category_name'])
        . badge('About ' . (int) $s['estimated_minutes'] . ' min', '', 'clock')
        . ($s['requires_appointment'] ? '' : badge('No appointment needed', 'ok'))
        . '</span>';
    $html .= '</span>';
    $html .= '<span class="card-chevron">' . icon('chevron', 20) . '</span>';
    $html .= '</a>';
    return $html;
}

/** Inline form error text linked to a field with aria-describedby. */
function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<p class="field-error" id="err-' . e($field) . '">' . e($errors[$field]) . '</p>' : '';
}

function describedby(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="err-' . e($field) . '"' : '';
}

/** Human text for an appointment status. */
function status_label(string $status): string
{
    $labels = ['booked' => 'Booked', 'cancelled' => 'Cancelled', 'completed' => 'Completed'];
    return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
}

function status_badge(string $status): string
{
    $variants = ['booked' => 'ok', 'cancelled' => 'muted', 'completed' => 'done'];
    return badge(status_label($status), isset($variants[$status]) ? $variants[$status] : '');
}

/** Compact appointment row used on home and "my appointments". */
function appointment_card(array $a, bool $showStatus = true): string
{
    $html = '<a class="card card-link appt-card" href="' . e(url('appointment.php?id=' . (int) $a['id'])) . '">';
    $html .= '<span class="appt-date"><span class="appt-day">' . e(to_datetime($a['starts_at'])->format('j')) . '</span>'
        . '<span class="appt-month">' . e(to_datetime($a['starts_at'])->format('M')) . '</span></span>';
    $html .= '<span class="card-body"><span class="card-title">' . e($a['service_name']) . '</span>';
    $html .= '<span class="card-sub">' . e(fmt_date($a['starts_at'])) . ' at ' . e(fmt_time($a['starts_at'])) . ' · ' . e($a['institution_name']) . '</span>';
    $html .= '<span class="badges"><span class="ticket-code-sm">' . e($a['ticket_code']) . '</span>'
        . ($showStatus ? status_badge($a['status']) : '') . '</span></span>';
    $html .= '<span class="card-chevron">' . icon('chevron', 20) . '</span></a>';
    return $html;
}

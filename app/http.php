<?php
declare(strict_types=1);

/** True when the request came from HTMX rather than a normal navigation. */
function is_htmx_request(): bool
{
    return ($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true';
}

/** Escape every dynamic value before it reaches HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Render a view from app/views.
 *
 * The path is confined to the views root: template names come from application
 * code, never from request input.
 */
function view(string $template, array $data = []): string
{
    $viewsRoot = realpath(__DIR__ . '/views');
    if ($viewsRoot === false) {
        throw new RuntimeException('View directory does not exist.');
    }
    $path = realpath($viewsRoot . '/' . ltrim($template, '/'));
    if ($path === false || !str_starts_with($path, $viewsRoot . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException("Invalid view: {$template}");
    }
    extract($data, EXTR_SKIP);
    ob_start();
    try {
        require $path;
        return (string) ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method Not Allowed');
    }
}

function request_integer(string $name): ?int
{
    $value = $_POST[$name] ?? $_GET[$name] ?? null;
    if ($value === null || $value === '') {
        return null;
    }
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    return $filtered === false ? null : $filtered;
}

/** Trimmed request string, length-capped so it can't blow up a query or a log row. */
function request_string(string $name, int $maxLength = 255): string
{
    $value = trim((string) ($_POST[$name] ?? $_GET[$name] ?? ''));
    return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength) : $value;
}

/** The client IP, as an inet-compatible string. */
function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * A stable id for this request, so every activity_log row written while serving
 * it can be correlated.
 */
function request_id(): string
{
    static $id = null;
    if ($id === null) {
        $id = bin2hex(random_bytes(8));
    }
    return $id;
}

/** Security headers for pages that must never be cached or framed. */
function send_no_store_headers(): void
{
    header('Cache-Control: no-store');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
}

/**
 * Redirect that works for both HTMX and normal navigations.
 *
 * Auth transitions (login, logout, the 2FA challenge) must be real navigations
 * so the session id regenerates and the new CSRF token reaches the meta tag.
 */
function redirect(string $path): never
{
    if (is_htmx_request()) {
        header('HX-Redirect: ' . $path);
    } else {
        header('Location: ' . $path);
    }
    exit;
}

/** Medium date format, per the design system: "Jan 15, 2026". */
function format_date(?string $timestamp): string
{
    if ($timestamp === null || $timestamp === '') {
        return '—';
    }
    return (new DateTimeImmutable($timestamp))->format('M j, Y');
}

/** Time format, per the design system: "2:30 PM". */
function format_time(?string $timestamp): string
{
    if ($timestamp === null || $timestamp === '') {
        return '—';
    }
    return (new DateTimeImmutable($timestamp))->format('g:i A');
}

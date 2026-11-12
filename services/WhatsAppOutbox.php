<?php
declare(strict_types=1);
require_once __DIR__ . '/WhatsAppNotifier.php';

function whatsAppTemplateText(string $value, int $limit, bool $redactContacts = true): string
{
    $value = preg_replace('/[\x00-\x20\x7f\s]+/u', ' ', strip_tags($value)) ?? '';
    if ($redactContacts) {
        $value = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $value) ?? '';
        $value = preg_replace('/(?<!\d)(?:\+?62|0)8(?:[ ()-]?\d){7,11}(?!\d)|(?<!\d)\+?\d{10,15}(?!\d)/', '[nomor]', $value) ?? '';
    }
    $value = trim($value);
    if (function_exists('mb_substr')) {
        $value = mb_substr($value, 0, $limit, 'UTF-8');
    } else {
        preg_match('/^.{0,' . $limit . '}/us', $value, $match);
        $value = $match[0] ?? '';
    }
    return $value !== '' ? $value : '-';
}

/** Call inside the same transaction as pengaduan + riwayat_status. No HTTP occurs here. */
function enqueueWhatsAppNotification(mysqli $conn, int $pengaduanId, array $report): void
{
    $config = whatsAppConfig();
    if (!$config['WA_ENABLED']) {
        return;
    }
    $template = (string)$config['WA_TEMPLATE_NAME'];
    $language = (string)$config['WA_TEMPLATE_LANG'];
    if ($pengaduanId < 1 || !preg_match('/^[a-z0-9_]{1,128}$/D', $template)
        || !preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/D', $language)) {
        throw new RuntimeException('whatsapp_queue_configuration_invalid');
    }
    $payload = json_encode([
        'no_laporan' => whatsAppTemplateText((string)($report['no_laporan'] ?? ''), 64, false),
        'nama_kategori' => whatsAppTemplateText((string)($report['nama_kategori'] ?? ''), 80),
        'lokasi' => whatsAppTemplateText((string)($report['lokasi'] ?? ''), 80),
        'status' => whatsAppTemplateText((string)($report['status'] ?? 'Menunggu'), 20),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $recipients = dbQuery($conn, "SELECT o.id_admin, o.recipient, u.no_wa
        FROM whatsapp_admin_optins o INNER JOIN users u ON u.id_user = o.id_admin
        WHERE o.revoked_at IS NULL AND u.role = 'admin'");
    foreach ($recipients as $admin) {
        if (normalizeWhatsAppRecipient((string)$admin['no_wa']) !== $admin['recipient']) {
            continue; // A changed phone number needs fresh consent for that number.
        }
        dbStatement($conn, "INSERT INTO whatsapp_notification_outbox
            (pengaduan_id, admin_id, recipient, event, template_name, template_lang, payload,
                next_retry_at, created_at, updated_at)
            VALUES (?, ?, ?, 'pengaduan.created', ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE id = id", 'iissss', [
                $pengaduanId, (int)$admin['id_admin'], $admin['recipient'], $template, $language, $payload,
            ]);
    }
}

/** Atomic UPDATE/LIMIT works without SKIP LOCKED, including common shared-host MySQL versions. */
function claimWhatsAppJob(mysqli $conn): ?array
{
    $token = bin2hex(random_bytes(32));
    dbStatement($conn, "UPDATE whatsapp_notification_outbox
        SET status = 'processing', lock_token = ?, locked_at = UTC_TIMESTAMP(),
            attempts = attempts + 1, updated_at = UTC_TIMESTAMP()
        WHERE status = 'pending' AND next_retry_at <= UTC_TIMESTAMP()
        ORDER BY id ASC LIMIT 1", 's', [$token]);
    return dbOne($conn, 'SELECT * FROM whatsapp_notification_outbox WHERE lock_token = ?', 's', [$token]);
}

function whatsAppConsentStillValid(mysqli $conn, array $job): bool
{
    $admin = dbOne($conn, "SELECT o.recipient, u.no_wa FROM whatsapp_admin_optins o
        INNER JOIN users u ON u.id_user = o.id_admin
        WHERE o.id_admin = ? AND o.revoked_at IS NULL AND u.role = 'admin'", 'i', [(int)$job['admin_id']]);
    return $admin !== null && $admin['recipient'] === $job['recipient']
        && normalizeWhatsAppRecipient((string)$admin['no_wa']) === $job['recipient'];
}

function completeWhatsAppJob(mysqli $conn, array $job, array $result, int $maxAttempts): void
{
    $status = $result['status'];
    if (!in_array($status, ['accepted', 'pending', 'failed', 'uncertain', 'cancelled'], true)) {
        throw new LogicException('whatsapp_invalid_result');
    }
    if ($status === 'pending' && (int)$job['attempts'] >= $maxAttempts) {
        $status = 'failed';
    }
    $delay = whatsAppRetryDelay((int)$job['attempts']);
    $error = $result['error_code'] ?? null;
    if ($error !== null && !preg_match('/^[a-z0-9_]{1,96}$/D', $error)) {
        $error = 'sanitized_worker_error';
    }
    dbStatement($conn, "UPDATE whatsapp_notification_outbox
        SET status = ?, provider_id = ?, last_error = ?,
            next_retry_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND),
            lock_token = NULL, locked_at = NULL, updated_at = UTC_TIMESTAMP()
        WHERE id = ? AND status = 'processing' AND lock_token = ?", 'sssiis', [
            $status, $result['provider_id'] ?? null, $error, $delay, (int)$job['id'], $job['lock_token'],
        ]);
    safeLog('whatsapp_job_result', ['job_id' => (int)$job['id'], 'status' => $status, 'code' => $error]);
}

function processWhatsAppJobs(mysqli $conn, WhatsAppNotifier $notifier, int $limit = 10): int
{
    $notifier->assertReady(); // Invalid credentials/config do not consume queue attempts.
    $config = whatsAppConfig();
    $maxAttempts = max(1, min(10, (int)$config['WA_MAX_ATTEMPTS']));
    dbStatement($conn, "UPDATE whatsapp_notification_outbox SET status = 'failed',
        last_error = 'retry_limit_reached', updated_at = UTC_TIMESTAMP()
        WHERE status = 'pending' AND attempts >= ?", 'i', [$maxAttempts]);
    // A crash may have occurred after Meta accepted a request. Never automatically reclaim it.
    dbStatement($conn, "UPDATE whatsapp_notification_outbox SET status = 'uncertain',
        last_error = 'worker_interrupted_unknown_outcome', lock_token = NULL,
        locked_at = NULL, updated_at = UTC_TIMESTAMP()
        WHERE status = 'processing' AND locked_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)");
    $processed = 0;
    while ($processed < max(1, min(50, $limit)) && ($job = claimWhatsAppJob($conn)) !== null) {
        try {
            if (!whatsAppConsentStillValid($conn, $job)) {
                $result = ['status' => 'cancelled', 'provider_id' => null, 'error_code' => 'admin_consent_revoked_or_changed'];
            } else {
                $notifier->buildPayload($job); // Validate before transport is started.
                $result = $notifier->send($job);
            }
        } catch (Throwable $exception) {
            // The transport may already have submitted the message. Do not retry blindly.
            $result = ['status' => 'uncertain', 'provider_id' => null, 'error_code' => 'worker_exception_unknown_outcome'];
        }
        completeWhatsAppJob($conn, $job, $result, $maxAttempts);
        $processed++;
    }
    return $processed;
}

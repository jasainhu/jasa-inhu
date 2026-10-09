<?php
/**
 * SMTP Mailer & Email Dispatcher JASA INHU
 * Menangani pengiriman email otomatis (OTP, Verifikasi, Notifikasi Sistem) via Gmail SMTP
 * Tanpa library eksternal (Zero Dependencies, Native PHP Socket + TLS/SSL)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

class SimpleSmtpMailer {
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $from_name;
    private string $encryption;
    private int $timeout;
    private ?string $last_error = null;

    public function __construct(
        ?string $host = null,
        ?int $port = null,
        ?string $username = null,
        ?string $password = null,
        ?string $from_name = null,
        ?string $encryption = null,
        int $timeout = 8
    ) {
        $this->host = $host ?: (string)get_setting('smtp_host', 'smtp.gmail.com');
        $raw_port = (string)get_setting('smtp_port', '587');
        $clean_port = (int)preg_replace('/[^0-9]/', '', $raw_port);
        $this->port = $port ?: ($clean_port ?: 587);
        $this->username = $username !== null ? $username : (string)get_setting('smtp_user', '');
        $this->password = $password !== null ? $password : (string)get_setting('smtp_pass', '');
        $this->from_name = $from_name ?: (string)get_setting('smtp_from_name', 'JASA INHU Resmi');
        $this->encryption = $encryption ?: (string)get_setting('smtp_secure', 'tls');
        $this->timeout = 3; // Timeout cepat agar pendaftar tidak menunggu lama jika port diblokir ISP
    }

    public function getLastError(): ?string {
        return $this->last_error;
    }

    public function isConfigured(): bool {
        return !empty($this->username) && !empty($this->password);
    }

    /**
     * Kirim email menggunakan socket SMTP murni
     */
    public function send(string $to_email, string $subject, string $html_body, string $plain_body = '', string $recipient_name = ''): bool {
        $to_email = trim($to_email);
        if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
            $this->last_error = "Alamat email tujuan tidak valid: {$to_email}";
            return false;
        }

        if (!$this->isConfigured()) {
            $this->last_error = "Konfigurasi akun SMTP Gmail belum diisi di Pengaturan Admin.";
            return false;
        }

        $remote_host = ($this->encryption === 'ssl' || $this->port === 465) ? "ssl://{$this->host}" : "tcp://{$this->host}";
        
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $socket = @stream_socket_client(
            "{$remote_host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            if ($errno == 10060) {
                $this->last_error = "Port SMTP {$this->host}:{$this->port} diblokir oleh provider internet (ISP) lokal Anda (Error 10060 Timeout). Gunakan opsi Webhook HTTPS Google Apps Script untuk pengiriman bebas blokir.";
            } else {
                $this->last_error = "Gagal terhubung ke server SMTP {$this->host}:{$this->port} ({$errstr})";
            }
            return false;
        }

        stream_set_timeout($socket, $this->timeout);

        $read = function() use ($socket): string {
            $data = '';
            while ($line = fgets($socket, 512)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };

        $write = function(string $cmd) use ($socket): void {
            fwrite($socket, $cmd . "\r\n");
        };

        $greeting = $read();
        if (substr($greeting, 0, 3) !== '220') {
            $this->last_error = "Respon server tidak valid saat koneksi: " . trim($greeting);
            fclose($socket);
            return false;
        }

        // Kirim EHLO
        $write("EHLO " . gethostname());
        $ehlo = $read();

        // Jika port 587 atau enkripsi TLS, aktifkan STARTTLS
        if ($this->port === 587 || $this->encryption === 'tls') {
            $write("STARTTLS");
            $tls_response = $read();
            if (substr($tls_response, 0, 3) !== '220') {
                $this->last_error = "STARTTLS ditolak server: " . trim($tls_response);
                fclose($socket);
                return false;
            }

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                $this->last_error = "Negosiasi enkripsi TLS dengan Gmail gagal.";
                fclose($socket);
                return false;
            }

            // EHLO ulang setelah TLS aktif
            $write("EHLO " . gethostname());
            $read();
        }

        // Otentikasi AUTH LOGIN
        $write("AUTH LOGIN");
        $auth_resp = $read();
        if (substr($auth_resp, 0, 3) !== '334') {
            $this->last_error = "Server tidak mendukung AUTH LOGIN: " . trim($auth_resp);
            fclose($socket);
            return false;
        }

        $write(base64_encode($this->username));
        $user_resp = $read();
        if (substr($user_resp, 0, 3) !== '334') {
            $this->last_error = "Username SMTP ditolak: " . trim($user_resp);
            fclose($socket);
            return false;
        }

        $write(base64_encode(str_replace(' ', '', $this->password)));
        $pass_resp = $read();
        if (substr($pass_resp, 0, 3) !== '235') {
            $this->last_error = "Autentikasi Gmail gagal (Pastikan menggunakan Google App Password 16 karakter): " . trim($pass_resp);
            fclose($socket);
            return false;
        }

        // MAIL FROM
        $write("MAIL FROM: <" . $this->username . ">");
        $from_resp = $read();
        if (substr($from_resp, 0, 3) !== '250') {
            $this->last_error = "MAIL FROM ditolak: " . trim($from_resp);
            fclose($socket);
            return false;
        }

        // RCPT TO
        $write("RCPT TO: <" . $to_email . ">");
        $to_resp = $read();
        if (substr($to_resp, 0, 3) !== '250') {
            $this->last_error = "RCPT TO ditolak: " . trim($to_resp);
            fclose($socket);
            return false;
        }

        // DATA
        $write("DATA");
        $data_resp = $read();
        if (substr($data_resp, 0, 3) !== '354') {
            $this->last_error = "Perintah DATA ditolak: " . trim($data_resp);
            fclose($socket);
            return false;
        }

        // Siapkan Header & Body Multipart
        $boundary = "bnd_" . md5(uniqid(microtime(), true));
        $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encoded_from = '=?UTF-8?B?' . base64_encode($this->from_name) . '?= <' . $this->username . '>';
        $encoded_to = !empty($recipient_name) 
            ? '=?UTF-8?B?' . base64_encode($recipient_name) . '?= <' . $to_email . '>' 
            : $to_email;

        if (empty($plain_body)) {
            $plain_body = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html_body));
        }

        $headers = [
            "Date: " . date('r'),
            "From: {$encoded_from}",
            "To: {$encoded_to}",
            "Subject: {$encoded_subject}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "X-Mailer: JasaInhu-Mailer/1.0"
        ];

        $payload = implode("\r\n", $headers) . "\r\n\r\n";
        
        // Plain text part
        $payload .= "--{$boundary}\r\n";
        $payload .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $payload .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $payload .= chunk_split(base64_encode($plain_body)) . "\r\n";

        // HTML part
        $payload .= "--{$boundary}\r\n";
        $payload .= "Content-Type: text/html; charset=UTF-8\r\n";
        $payload .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $payload .= chunk_split(base64_encode($html_body)) . "\r\n";

        $payload .= "--{$boundary}--\r\n";
        $payload .= ".";

        $write($payload);
        $final_resp = $read();

        $write("QUIT");
        @fclose($socket);

        if (substr($final_resp, 0, 3) !== '250') {
            $this->last_error = "Pengiriman data email gagal: " . trim($final_resp);
            return false;
        }

        return true;
    }
}

/**
 * Kirim email via Google Apps Script Webhook (Port 443 HTTPS - 100% Bebas Blokir ISP)
 */
function send_via_google_webhook(
    string $webhook_url,
    string $to_email,
    string $subject,
    string $html_body,
    string $plain_body = '',
    string $from_name = 'JASA INHU Resmi'
): array {
    $payload = json_encode([
        'to'        => $to_email,
        'subject'   => $subject,
        'html'      => $html_body,
        'plain'     => $plain_body ?: strip_tags($html_body),
        'from_name' => $from_name
    ]);

    $ch = curl_init(trim($webhook_url));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'error' => "Gagal terhubung ke Webhook Google: " . $err];
    }
    if ($httpCode >= 400) {
        return ['success' => false, 'error' => "Webhook Google HTTP Error {$httpCode}: " . substr((string)$response, 0, 150)];
    }

    $decoded = json_decode((string)$response, true);
    if (is_array($decoded) && isset($decoded['status']) && $decoded['status'] === 'ok') {
        return ['success' => true, 'response' => $response];
    }

    // Jika response mengandung error teks/HTML dari Google
    if (stripos((string)$response, 'error') !== false || stripos((string)$response, 'exception') !== false) {
        return ['success' => false, 'error' => "Google Apps Script Error: " . trim(strip_tags(substr((string)$response, 0, 250)))];
    }

    return ['success' => true, 'response' => $response];
}

/**
 * Fungsi utama untuk kirim email sistem JASA INHU
 * Otomatis mencatat ke email_logs
 */
function send_system_email(
    string $recipient_email,
    string $subject,
    string $html_body,
    string $plain_body = '',
    string $recipient_name = ''
): array {
    $recipient_email = trim($recipient_email);
    $webhook_url = trim((string)get_setting('gmail_webhook_url', ''));
    $mailer = new SimpleSmtpMailer();
    
    $status = 'sent';
    $err_msg = null;
    $simulated = false;

    // Prioritas 1: Jika Webhook Google Apps Script diisi (Port 443 HTTPS - Bebas Blokir ISP)
    if (!empty($webhook_url)) {
        $from_name = (string)get_setting('smtp_from_name', 'JASA INHU Resmi');
        $whRes = send_via_google_webhook($webhook_url, $recipient_email, $subject, $html_body, $plain_body, $from_name);
        if ($whRes['success']) {
            $status = 'sent';
            $err_msg = null;
        } else {
            $status = 'failed';
            $err_msg = $whRes['error'];
        }
    }
    // Prioritas 2: Gunakan SMTP Socket Langsung
    elseif ($mailer->isConfigured()) {
        $ok = $mailer->send($recipient_email, $subject, $html_body, $plain_body, $recipient_name);
        if ($ok) {
            $status = 'sent';
            $err_msg = null;
        } else {
            $status = 'failed';
            $err_msg = $mailer->getLastError();
        }
    }
    // Prioritas 3: Mode Simulasi Dev jika kredensial belum diisi
    else {
        $status = 'simulated';
        $simulated = true;
        $err_msg = 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.';
    }

    // Catat ke tabel email_logs untuk audit trail admin
    try {
        $db = get_db();
        $stmtLog = $db->prepare("
            INSERT INTO email_logs 
            (recipient_email, recipient_name, subject, body_text, status, error_message, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $body_logged = !empty($plain_body) ? $plain_body : strip_tags($html_body);
        // Clean non-ASCII for 3-byte safe utf8
        $clean_body = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $body_logged);
        $clean_subject = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $subject);
        $clean_err = $err_msg ? preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $err_msg) : null;

        $stmtLog->execute([
            $recipient_email,
            $recipient_name ?: null,
            $clean_subject,
            $clean_body,
            $status,
            $clean_err
        ]);
        $log_id = (int)$db->lastInsertId();
    } catch (Exception $e) {
        $log_id = 0;
        error_log("Failed to insert into email_logs: " . $e->getMessage());
    }

    return [
        'success'      => ($status === 'sent'),
        'simulated'    => $simulated,
        'status'       => $status,
        'error'        => $err_msg,
        'log_id'       => $log_id,
        'recipient'    => $recipient_email
    ];
}

/**
 * Render template email profesional JASA INHU
 */
function render_verification_email_html(string $name, string $otp_code, string $verify_link): string {
    $base_url = BASE_URL;
    $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Akun JASA INHU</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #334155;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f1f5f9; padding: 30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 580px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%); padding: 32px 30px; text-align: center;">
                            <div style="font-size: 26px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; margin-bottom: 4px;">
                                JASA INHU
                            </div>
                            <div style="font-size: 13px; color: #ccfbf1; font-weight: 500;">
                                Platform Marketplace Jasa Lokal Kab. Indragiri Hulu
                            </div>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 36px 30px;">
                            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 12px;">
                                Verifikasi Keamanan Akun Anda
                            </h2>
                            <p style="font-size: 15px; line-height: 1.6; color: #475569; margin: 0 0 20px 0;">
                                Halo <strong>{$safe_name}</strong>, terima kasih telah mendaftar di <strong>JASA INHU</strong>. Demi keamanan bersama serta mencegah akun dan pesanan fiktif, silakan masukkan kode 6-digit di bawah ini:
                            </p>

                            <!-- Kode OTP Box -->
                            <div style="background-color: #f0fdfa; border: 2px dashed #0d9488; border-radius: 12px; padding: 24px 20px; text-align: center; margin: 25px 0;">
                                <div style="font-size: 12px; font-weight: 700; color: #0f766e; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;">
                                    KODE VERIFIKASI RESMI
                                </div>
                                <div style="font-size: 40px; font-weight: 800; color: #0f172a; letter-spacing: 10px; font-family: 'Courier New', monospace; margin: 6px 0;">
                                    {$otp_code}
                                </div>
                                <div style="font-size: 13px; color: #64748b; margin-top: 8px;">
                                    Berlaku selama <strong>15 Menit</strong>. Jangan berikan kode ini kepada siapa pun.
                                </div>
                            </div>

                            <!-- Tombol Link Otomatis -->
                            <div style="text-align: center; margin: 30px 0 25px 0;">
                                <a href="{$verify_link}" style="display: inline-block; background-color: #0d9488; color: #ffffff; font-size: 15px; font-weight: 700; text-decoration: none; padding: 14px 32px; border-radius: 8px; box-shadow: 0 2px 6px rgba(13, 148, 136, 0.3);">
                                    Aktifkan Akun Saya Otomatis &raquo;
                                </a>
                            </div>

                            <p style="font-size: 13px; line-height: 1.5; color: #64748b; margin: 20px 0 0 0;">
                                Atau salin tautan berikut ke browser Anda jika tombol di atas tidak dapat diklik:<br>
                                <a href="{$verify_link}" style="color: #0d9488; word-break: break-all; font-size: 12px;">{$verify_link}</a>
                            </p>

                            <!-- Security / Anti-Prank Note -->
                            <div style="background-color: #f8fafc; border-left: 4px solid #f59e0b; padding: 14px 16px; border-radius: 6px; margin-top: 25px;">
                                <div style="font-size: 12px; color: #78350f; font-weight: 600; margin-bottom: 2px;">
                                    Pemberitahuan Perlindungan Komunitas:
                                </div>
                                <div style="font-size: 12px; color: #92400e; line-height: 1.5;">
                                    Sistem verifikasi ini diterapkan untuk memastikan seluruh pemohon dan penyedia jasa adalah warga asli terpercaya, menghindari pesanan fiktif, serta melindungi teknisi lokal kami.
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 30px; text-align: center;">
                            <p style="font-size: 12px; color: #94a3b8; margin: 0 0 6px 0;">
                                Surat elektronik otomatis dari Sistem Resmi JASA INHU &bull; Kab. Indragiri Hulu, Riau
                            </p>
                            <p style="font-size: 11px; color: #cbd5e1; margin: 0;">
                                Jika Anda tidak merasa mendaftar di JASA INHU, abaikan pesan ini. Akun fiktif akan terhapus otomatis.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

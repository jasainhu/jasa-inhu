<?php
/**
 * Core Engine: "Kak Inhu" AI Agent & Virtual Concierge
 * JASA INHU - Marketplace Jasa Lokal Kabupaten Indragiri Hulu
 * 
 * Supports:
 * 1. Google Gemini Flash LLM (if API Key is configured in admin settings)
 * 2. Smart Local Heuristic NLP Fallback (0ms latency, 100% offline-ready, 0 API cost)
 * 3. Deep Database Integration with service_providers, categories, and districts
 */

class JasaInhuAIAgent {

    /**
     * Daftar 14 Kecamatan di Indragiri Hulu beserta sinonim & desa populernya
     */
    private static array $districtMap = [
        1  => ['name' => 'Rengat', 'aliases' => ['rengat', 'rengat kota', 'sekip', 'pasar rengat', 'danau raja', 'kampung dagang', 'kampung pulau', 'kampung besar']],
        2  => ['name' => 'Rengat Barat', 'aliases' => ['rengat barat', 'pematang reba', 'reba', 'pematangreba', 'danau baru', 'bukit petaling']],
        3  => ['name' => 'Pasir Penyu', 'aliases' => ['pasir penyu', 'air molek', 'airmolek', 'molek', 'batu gajah', 'serangge']],
        4  => ['name' => 'Seberida', 'aliases' => ['seberida', 'belilas', 'blilas', 'simpang empat belilas', 'simpang kulim', 'pangkalan kasai']],
        5  => ['name' => 'Batang Cenaku', 'aliases' => ['batang cenaku', 'cenaku', 'kuala kilan', 'pejangki']],
        6  => ['name' => 'Batang Gansal', 'aliases' => ['batang gansal', 'gansal', 'seberida lama', 'sungai akar', 'keritang inhu']],
        7  => ['name' => 'Kelayang', 'aliases' => ['kelayang', 'simpang kelayang', 'polak pisang', 'bongkal malang']],
        8  => ['name' => 'Rakit Kulim', 'aliases' => ['rakit kulim', 'kelayang kulim', 'petonggan']],
        9  => ['name' => 'Peranap', 'aliases' => ['peranap', 'gumanti', 'batu rizal', 'katipo']],
        10 => ['name' => 'Batang Peranap', 'aliases' => ['batang peranap', 'selunak', 'pematang']],
        11 => ['name' => 'Kuala Cenaku', 'aliases' => ['kuala cenaku', 'tambak', 'kuala mulya']],
        12 => ['name' => 'Sungai Lala', 'aliases' => ['sungai lala', 'sungailala', 'kelawat', 'perkebunan sungai lala']],
        13 => ['name' => 'Lirik', 'aliases' => ['lirik', 'gudang batu', 'rejosari lirik', 'sukajadi lirik']],
        14 => ['name' => 'Lubuk Batu Jaya', 'aliases' => ['lubuk batu jaya', 'lbj', 'kulim jaya', 'pondok gelugur']]
    ];

    /**
     * Pemetaan kata kunci keluhan/masalah ke Kategori Layanan
     */
    private static array $categoryKeywordMap = [
        'Servis AC' => [
            'id' => 3,
            'keywords' => ['ac', 'cuci ac', 'freon', 'bocor ac', 'ac bocor', 'ac netes', 'ac panas', 'ac mati', 'tambah freon', 'split', 'bongkar pasang ac', 'dingin']
        ],
        'Servis Motor' => [
            'id' => 1,
            'keywords' => ['motor', 'bengkel', 'montir', 'mogok', 'ban bocor', 'tambal ban', 'ganti oli', 'oli', 'servis motor', 'injeksi', 'karbu', 'vario', 'beat', 'scoopy', 'nmax', 'rem', 'rantai', 'busi', 'tune up motor']
        ],
        'Servis Mobil' => [
            'id' => 2,
            'keywords' => ['mobil', 'bengkel mobil', 'montir mobil', 'tune up mobil', 'ganti oli mobil', 'aki', 'kaki-kaki', 'radiator', 'mesin mobil', 'avanza', 'innova']
        ],
        'Elektronik' => [
            'id' => 4,
            'keywords' => ['kulkas', 'mesin cuci', 'tv', 'televisi', 'kipas angin', 'dispenser', 'magic com', 'rice cooker', 'setrika', 'microwave', 'elektronik']
        ],
        'Listrik' => [
            'id' => 5,
            'keywords' => ['listrik', 'korslet', 'korsleting', 'mcb', 'lampu', 'stop kontak', 'kabel', 'instalasi listrik', 'mati lampu', 'pln']
        ],
        'Bangunan' => [
            'id' => 6,
            'keywords' => ['tukang', 'bangunan', 'renovasi', 'rumah', 'cat rumah', 'pengecatan', 'plester', 'pasang bata', 'keramik', 'lantai', 'bocor atap', 'bocor rumah', 'genteng', 'semen']
        ],
        'Tukang Kayu' => [
            'id' => 7,
            'keywords' => ['kayu', 'tukang kayu', 'pintu', 'kusen', 'lemari', 'kitchen set', 'mebel', 'meja', 'kursi kayu', 'perabot']
        ],
        'Tukang Las' => [
            'id' => 8,
            'keywords' => ['las', 'tukang las', 'pagar', 'kanopi', 'teralis', 'baja ringan', 'besi', 'bengkel las', 'folding gate', 'rolling door']
        ],
        'Pertanian & Perkebunan' => [
            'id' => 9,
            'keywords' => ['sawit', 'panen sawit', 'kebun', 'dodos', 'egrek', 'tebas', 'semprot gulma', 'pupuk', 'perawatan kebun', 'karet', 'deres karet', 'lahan']
        ],
        'Angkutan' => [
            'id' => 10,
            'keywords' => ['angkut', 'pickup', 'pikup', 'truk', 'sewa pickup', 'pindahan rumah', 'kirim barang', 'mobil barang', 'ekspedisi lokal']
        ],
        'Kebersihan' => [
            'id' => 11,
            'keywords' => ['potong rumput', 'rumput', 'kebersihan', 'bersih rumah', 'sedot wc', 'toren', 'kuras toren', 'sampah', 'babat rumput']
        ],
        'Acara & Hiburan' => [
            'id' => 12,
            'keywords' => ['tenda', 'sound system', 'sound', 'orgen', 'organ tunggal', 'musik', 'kursi pesta', 'teratak', 'pesta', 'nikah', 'wedding', 'hajatan', 'mc acara']
        ],
        'Fotografi & Videografi' => [
            'id' => 13,
            'keywords' => ['foto', 'fotografer', 'video', 'videografer', 'wisuda', 'prewedding', 'shooting', 'dokumentasi', 'album foto']
        ],
        'Teknologi' => [
            'id' => 14,
            'keywords' => ['komputer', 'laptop', 'pc', 'printer', 'install ulang', 'windows', 'wifi', 'cctv', 'servis hp', 'handphone', 'smartphone', 'gadget']
        ],
        'Pendidikan' => [
            'id' => 15,
            'keywords' => ['les', 'guru', 'privat', 'guru les', 'ngaji', 'mengaji', 'kursus', 'bimbel', 'belajar anak', 'matematika']
        ],
        'Jahit & Konveksi' => [
            'id' => 150019,
            'keywords' => ['jahit', 'tukang jahit', 'konveksi', 'baju', 'celana', 'seragam', 'kebaya', 'vermak', 'permak levis']
        ],
        'Kuliner & Katering' => [
            'id' => 120019,
            'keywords' => ['kuliner', 'katering', 'catering', 'nasi kotak', 'snack box', 'kue', 'tumpeng', 'makanan', 'lapar', 'pesan antar makan']
        ],
        'Medical' => [
            'id' => 18,
            'keywords' => ['perawat', 'bidan', 'suntik', 'tensi', 'rawat luka', 'home care', 'kesehatan', 'panggil dokter', 'fisioterapi']
        ],
        'Kecantikan & Spa' => [
            'id' => 180020,
            'keywords' => ['salon', 'makeup', 'rias pengantin', 'facial', 'spa', 'rambut', 'potong rambut', 'barbershop']
        ],
        'Undangan & Percetakan' => [
            'id' => 210020,
            'keywords' => ['undangan', 'cetak', 'percetakan', 'spanduk', 'banner', 'brosur', 'stiker', 'souvenir', 'plakat', 'sablon']
        ],
        'Jasa Suruh' => [
            'id' => 300019,
            'keywords' => ['suruh', 'jasa suruh', 'belanja', 'antri', 'titip', 'antar surat', 'kurir', 'bantu belanja']
        ]
    ];

    /**
     * Memproses pesan masuk dari user
     */
    public static function handleMessage(string $userMessage, array $conversationHistory = []): array {
        $message = trim($userMessage);
        if ($message === '') {
            return [
                'status'  => 'error',
                'message' => 'Pesan tidak boleh kosong.'
            ];
        }

        $db = get_db();

        // 1. Ekstraksi Kecamatan & Kategori dari Pesan
        $detectedDistrict = self::detectDistrict($message);
        $detectedCategory = self::detectCategory($message);

        // 2. Query Mitra yang Cocok dari Database
        $providers = self::findMatchingProviders($db, $detectedCategory, $detectedDistrict, $message);

        // 3. Tentukan apakah menggunakan Gemini LLM atau Fallback Lokal
        $geminiKey = get_setting('gemini_api_key', '');
        $aiReply = '';

        if (!empty($geminiKey)) {
            $geminiResponse = self::askGemini($geminiKey, $message, $providers, $detectedCategory, $detectedDistrict, $conversationHistory);
            if ($geminiResponse !== false && trim($geminiResponse) !== '') {
                $aiReply = $geminiResponse;
            }
        }

        // Jika Gemini belum disetel atau ada kendala koneksi API, gunakan Smart Local Fallback
        if (empty($aiReply)) {
            $aiReply = self::buildLocalResponse($message, $providers, $detectedCategory, $detectedDistrict);
        }

        // 4. Siapkan Saran Cepat (Quick Chips)
        $quickReplies = self::generateQuickReplies($detectedCategory, $detectedDistrict);

        return [
            'status'        => 'success',
            'reply'         => $aiReply,
            'providers'     => $providers,
            'detected'      => [
                'category'  => $detectedCategory ? $detectedCategory['name'] : null,
                'district'  => $detectedDistrict ? $detectedDistrict['name'] : null,
            ],
            'quick_replies' => $quickReplies
        ];
    }

    /**
     * Deteksi Kecamatan di Indragiri Hulu dari kalimat pengguna
     */
    private static function detectDistrict(string $text): ?array {
        $lower = strtolower($text);

        foreach (self::$districtMap as $id => $data) {
            foreach ($data['aliases'] as $alias) {
                if (preg_match('/\b' . preg_quote($alias, '/') . '\b/i', $lower)) {
                    return [
                        'id'   => $id,
                        'name' => $data['name']
                    ];
                }
            }
        }
        return null;
    }

    /**
     * Deteksi Kategori Layanan dari keluhan/kebutuhan dengan scoring cerdas
     */
    private static function detectCategory(string $text): ?array {
        $lower = strtolower($text);
        $bestCategory = null;
        $highestScore = 0;

        foreach (self::$categoryKeywordMap as $catName => $data) {
            $catScore = 0;
            foreach ($data['keywords'] as $kw) {
                if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $lower)) {
                    // Beri bobot lebih tinggi untuk kata yang lebih panjang/spesifik
                    $catScore += (mb_strlen($kw) * 2);
                }
            }

            if ($catScore > $highestScore) {
                $highestScore = $catScore;
                $bestCategory = [
                    'id'   => $data['id'],
                    'name' => $catName
                ];
            }
        }

        return $bestCategory;
    }

    /**
     * Cari mitra aktif di database yang relevan
     */
    private static function findMatchingProviders(PDO $db, ?array $category, ?array $district, string $queryText): array {
        $where = ["u.is_active = 1"];
        $params = [];

        // Filter kategori jika terdeteksi
        if ($category) {
            $where[] = "sp.primary_category_id = ?";
            $params[] = $category['id'];
        }

        // Filter kecamatan jika terdeteksi (cek domisili provider ATAU service_areas jangkauan)
        if ($district) {
            $where[] = "(sp.district_id = ? OR sp.id IN (SELECT provider_id FROM service_areas WHERE district_id = ?))";
            $params[] = $district['id'];
            $params[] = $district['id'];
        }

        // Jika tidak ada kategori yang terdeteksi, lakukan pencarian teks di nama/headline/deskripsi
        if (!$category && !$district) {
            $cleanText = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $queryText);
            $words = array_filter(explode(' ', $cleanText), fn($w) => mb_strlen($w) >= 3);
            if (!empty($words)) {
                $subLikes = [];
                foreach (array_slice($words, 0, 3) as $w) {
                    $subLikes[] = "(sp.business_name LIKE ? OR sp.headline LIKE ? OR sp.description LIKE ?)";
                    $params[] = "%{$w}%";
                    $params[] = "%{$w}%";
                    $params[] = "%{$w}%";
                }
                $where[] = "(" . implode(' OR ', $subLikes) . ")";
            }
        }

        $sql = "
            SELECT sp.id, sp.business_name, sp.headline, sp.image_url, sp.rating_avg, 
                   sp.reviews_count, sp.completed_jobs, sp.hourly_rate_min, sp.hourly_rate_max,
                   sp.is_verified, sp.serves_home_visit, sp.has_physical_store,
                   u.name as owner_name, u.phone, 
                   sc.name as category_name, sc.icon as category_icon,
                   d.name as district_name
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            JOIN service_categories sc ON sp.primary_category_id = sc.id
            LEFT JOIN districts d ON sp.district_id = d.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY sp.is_verified DESC, sp.rating_avg DESC, sp.completed_jobs DESC
            LIMIT 5
        ";

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $isNearbyFallback = false;
            // Jika pencarian dengan kecamatan menghasilkan 0, cari mitra di kategori yang sama se-Inhu
            if (empty($rows) && $category && $district) {
                $nearbySql = "
                    SELECT sp.id, sp.business_name, sp.headline, sp.image_url, sp.rating_avg, 
                           sp.reviews_count, sp.completed_jobs, sp.hourly_rate_min, sp.hourly_rate_max,
                           sp.is_verified, sp.serves_home_visit, sp.has_physical_store,
                           u.name as owner_name, u.phone, 
                           sc.name as category_name, sc.icon as category_icon,
                           d.name as district_name
                    FROM service_providers sp
                    JOIN users u ON sp.user_id = u.id
                    JOIN service_categories sc ON sp.primary_category_id = sc.id
                    LEFT JOIN districts d ON sp.district_id = d.id
                    WHERE u.is_active = 1 AND sp.primary_category_id = ?
                    ORDER BY sp.is_verified DESC, sp.rating_avg DESC, sp.completed_jobs DESC
                    LIMIT 5
                ";
                $stmtNear = $db->prepare($nearbySql);
                $stmtNear->execute([$category['id']]);
                $rows = $stmtNear->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $isNearbyFallback = true;
                }
            }

            // Format data provider untuk response JSON yang siap pakai di frontend
            $formatted = [];
            foreach ($rows as $r) {
                $cleanPhone = preg_replace('/[^0-9]/', '', $r['phone'] ?? '');
                if (str_starts_with($cleanPhone, '08')) {
                    $cleanPhone = '628' . substr($cleanPhone, 2);
                }

                // Pesan WA ramah yang langsung menyebut rekomendasi Kak Inhu
                $waText = "Halo " . rawurlencode($r['business_name']) . ", saya dapat rekomendasi jasa Anda dari Kak Inhu di website Jasa Inhu. Apakah melayani pengerjaan saat ini?";
                $waUrl = !empty($cleanPhone) ? "https://wa.me/{$cleanPhone}?text={$waText}" : null;

                $formatted[] = [
                    'id'                => (int)$r['id'],
                    'business_name'     => $r['business_name'],
                    'headline'          => $r['headline'] ?: 'Mitra Jasa Terpercaya di Indragiri Hulu',
                    'category_name'     => $r['category_name'],
                    'category_icon'     => $r['category_icon'] ?: 'fa-wrench',
                    'district_name'     => $r['district_name'] ?: 'Indragiri Hulu',
                    'is_nearby'         => $isNearbyFallback,
                    'rating_avg'        => number_format((float)$r['rating_avg'], 1),
                    'reviews_count'     => (int)$r['reviews_count'],
                    'completed_jobs'    => (int)$r['completed_jobs'],
                    'is_verified'       => (bool)$r['is_verified'],
                    'serves_home_visit' => (bool)$r['serves_home_visit'],
                    'rate_min'          => (float)$r['hourly_rate_min'],
                    'rate_max'          => (float)$r['hourly_rate_max'],
                    'rate_display'      => $r['hourly_rate_min'] > 0 ? 'Mulai Rp ' . number_format($r['hourly_rate_min'], 0, ',', '.') : 'Hubungi Mitra',
                    'image_url'         => $r['image_url'] ?: null,
                    'profile_url'       => BASE_URL . '/provider_detail.php?id=' . $r['id'],
                    'order_url'         => BASE_URL . '/order.php?provider_id=' . $r['id'],
                    'wa_url'            => $waUrl
                ];
            }
            return $formatted;
        } catch (Exception $e) {
            error_log("Kak Inhu findMatchingProviders error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Memanggil Google Gemini API (Flash Model)
     */
    private static function askGemini(string $apiKey, string $userMessage, array $providers, ?array $category, ?array $district, array $history): string|false {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);

        // Susun Ringkasan Mitra untuk prompt context Gemini
        $providerContext = "Daftar Mitra Nyata yang Tersedia di Jasa Inhu saat ini:\n";
        if (!empty($providers)) {
            foreach ($providers as $idx => $p) {
                $num = $idx + 1;
                $providerContext .= "{$num}. {$p['business_name']} (Kategori: {$p['category_name']}, Wilayah: {$p['district_name']}, Rating: {$p['rating_avg']}/5, Pekerjaan Selesai: {$p['completed_jobs']}x, " . ($p['is_verified'] ? 'Terverifikasi KTP' : 'Mitra Baru') . ")\n";
            }
        } else {
            $providerContext .= "Saat ini belum ada mitra terdaftar spesifik untuk pencarian ini.\n";
        }

        $systemInstruction = "Kamu adalah 'Kak Inhu', asisten virtual dan AI Concierge ramah, cerdas, dan tanggap dari platform JASA INHU (marketplace jasa lokal resmi Kabupaten Indragiri Hulu, Riau).\n"
            . "Tugasmu:\n"
            . "1. Menyapa pengguna dengan hangat, ramah, khas Riau / Indonesia yang sopan.\n"
            . "2. Memahami keluhan pengguna (misal: AC bocor, motor mogok, butuh tukang las, panen sawit, dll).\n"
            . "3. Merekomendasikan mitra nyata yang terlampir di data konteks. Sebutkan kelebihan mereka (misal: rating bagus, terverifikasi, sudah menyelesaikan banyak pesanan).\n"
            . "4. Jika ada mitra cocok, ajak pengguna untuk klik tombol 'Chat WA Langsung' atau 'Lihat Profil' pada kartu di bawah.\n"
            . "5. Jika tidak ada mitra cocok di kecamatan tersebut, sarankan pengguna untuk memasang 'Permintaan Jasa Terbuka (Tender)' agar penyedia jasa di sekitar Inhu yang siap bisa langsung memberikan penawaran harga, atau sarankan hubungi Admin CS.\n"
            . "6. Jawab secara padat, jelas (maksimal 2-3 paragraf ringkas), jangan bertele-tele.";

        $fullPrompt = "Konteks Mitra Jasa Inhu:\n{$providerContext}\n\n"
            . ($district ? "Kecamatan Terdeteksi: {$district['name']}\n" : "")
            . ($category ? "Kategori Terdeteksi: {$category['name']}\n" : "")
            . "Pesan Pengguna: \"{$userMessage}\"\n\n"
            . "Berikan jawaban sebagai Kak Inhu:";

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $systemInstruction . "\n\n" . $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'     => 0.7,
                'maxOutputTokens' => 600,
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode !== 200 || !$response) {
            error_log("Kak Inhu Gemini API error (HTTP {$httpCode}): " . ($err ?: $response));
            return false;
        }

        $json = json_decode($response, true);
        $candidates = $json['candidates'] ?? [];
        if (!empty($candidates[0]['content']['parts'][0]['text'])) {
            return trim($candidates[0]['content']['parts'][0]['text']);
        }

        return false;
    }

    /**
     * Mesin Pintar Heuristik Lokal (Fallback jika tanpa Gemini API)
     * Menghasilkan jawaban yang sangat relevan, ramah, dan cepat.
     */
    private static function buildLocalResponse(string $userMessage, array $providers, ?array $category, ?array $district): string {
        $count = count($providers);
        $catName = $category ? $category['name'] : 'jasa yang Anda butuhkan';
        $distName = $district ? 'wilayah ' . $district['name'] : 'Kabupaten Indragiri Hulu';

        // Jika menemukan mitra cocok
        if ($count > 0) {
            $greetings = [
                "Halo! Kak Inhu siap bantu! 🤖✨",
                "Siap, senang bisa bantu Anda hari ini! 🤖🙌",
                "Tentu, ada kabar baik untuk Anda! 🤖💡"
            ];
            $greeting = $greetings[array_rand($greetings)];

            $topMitra = $providers[0];
            $isNearby = !empty($topMitra['is_nearby']);

            $response = "{$greeting}\n\n";
            if ($isNearby && $district) {
                $response .= "Untuk wilayah **{$district['name']}**, mitra spesifik sedang dalam perjalanan pendaftaran. Namun Kak Inhu sudah carikan **mitra {$catName} terdekat** yang siap dipanggil / melayani ke area Anda:\n\n";
            } else {
                $response .= "Kak Inhu sudah mencarikan **{$count} mitra terbaik** untuk layanan **{$catName}** di {$distName}.\n\n";
            }
            $response .= "Rekomendasi utama: **{$topMitra['business_name']}** " . ($topMitra['is_verified'] ? '⭐ (Terverifikasi KTP)' : '') . " dengan rating **{$topMitra['rating_avg']}/5** ({$topMitra['completed_jobs']} pekerjaan selesai).\n\n";
            $response .= "Silakan klik tombol **'Chat WA Langsung'** pada kartu di bawah ini untuk konsultasi/tanya biaya, atau klik **'Lihat Toko'** untuk melihat portofolio hasil kerjanya ya!";

            return $response;
        }

        // Jika ada kategori tapi belum ada mitra di kecamatan tersebut
        if ($category) {
            return "Halo! Untuk kategori **{$catName}** di {$distName}, saat ini mitra kami sedang dalam proses penambahan kuota aktif.\n\n"
                . "💡 **Solusi Praktis dari Kak Inhu:**\n"
                . "1. Anda bisa buat **Permintaan Terbuka (Tender)** di Jasa Inhu, agar seluruh mitra teknisi se-Inhu yang sanggup langsung memberikan penawaran harga hemat ke Anda!\n"
                . "2. Atau klik tombol **Bantuan CS WhatsApp** di pojok kanan bawah, admin kami siap mencarikan teknisi rekanan terdekat secara manual.";
        }

        // Pertanyaan umum / sapaan
        $lower = strtolower($userMessage);
        if (preg_match('/\b(halo|hai|pagi|siang|sore|malam|assalamualaikum|tes|test|kak inhu)\b/i', $lower)) {
            return "Halo! Saya **Kak Inhu** 🤖, asisten virtual resmi Jasa Inhu.\n\n"
                . "Ada yang bisa Kak Inhu bantu hari ini di Indragiri Hulu? Contohnya Anda bisa ketik:\n"
                . "• *\"Cari tukang servis AC di Air Molek\"*\n"
                . "• *\"Motor mogok butuh montir di Rengat\"*\n"
                . "• *\"Butuh regu panen sawit di Belilas\"*\n"
                . "• *\"Tukang las bikin kanopi di Pematang Reba\"*\n\n"
                . "Ketik saja kebutuhan Anda, Kak Inhu langsung carikan mitranya!";
        }

        // Jawaban standar untuk pertanyaan lainnya
        return "Terima kasih atas pertanyaannya! Kak Inhu siap mencarikan solusi jasa terbaik untuk Anda di seluruh wilayah Indragiri Hulu.\n\n"
            . "Bisa tolong sebutkan jenis jasa yang dicari (misal: servis AC, montir motor, tukang las, tukang bangunan) beserta lokasi kecamatan Anda (Rengat, Belilas, Air Molek, dll)? Kak Inhu akan langsung carikan mitranya!";
    }

    /**
     * Menghasilkan saran cepat (quick reply chips)
     */
    private static function generateQuickReplies(?array $category, ?array $district): array {
        if ($category) {
            $catId = $category['id'];
            if ($catId === 3) { // AC
                return ['❄️ Cuci AC Air Molek', '❄️ Servis AC Belilas', '❄️ AC Rengat', '📋 Pasang Tender'];
            } elseif ($catId === 1) { // Motor
                return ['🏍️ Montir Mogok Rengat', '🏍️ Servis Motor Belilas', '🏍️ Ganti Oli Air Molek', '📋 Pasang Tender'];
            } elseif ($catId === 9) { // Sawit
                return ['🌴 Regu Panen Belilas', '🌴 Tebas Semprot Seberida', '🌴 Mandiri Tani Sawit', '📋 Pasang Tender'];
            } elseif ($catId === 8) { // Las
                return ['🔥 Pagar Kanopi Rengat', '🔥 Bengkel Las Pasir Penyu', '📋 Pasang Tender'];
            }
        }

        // Default saran beragam
        return [
            '❄️ Servis AC Bocor',
            '🏍️ Montir Motor Panggilan',
            '🌴 Regu Panen Sawit',
            '⚡ Perbaikan Listrik',
            '🍔 Kuliner & Katering'
        ];
    }
}

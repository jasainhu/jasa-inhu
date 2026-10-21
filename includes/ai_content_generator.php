<?php
/**
 * Agent 2: AI Content & Promo Studio
 * JASA INHU - Marketplace Jasa Lokal Kabupaten Indragiri Hulu
 * 
 * Mesin pembuat materi promosi, script video TikTok/Reels viral lokal,
 * dan teks broadcast medsos otomatis berbasis AI (Google Gemini + Smart Local Matrix).
 */

class JasaInhuAIContentStudio {

    /**
     * Generator Script Video Pendek (TikTok / Instagram Reels / YouTube Shorts)
     */
    public static function generateVideoScript(string $categoryName, string $districtName, string $angle): array {
        $apiKey = get_setting('gemini_api_key', '');

        if (!empty($apiKey)) {
            $geminiResult = self::callGeminiVideoScript($apiKey, $categoryName, $districtName, $angle);
            if ($geminiResult !== false) {
                return $geminiResult;
            }
        }

        // Fallback Heuristik Cerdas Lokal (Super Cepat, Rp 0 Biaya)
        return self::buildLocalVideoScript($categoryName, $districtName, $angle);
    }

    /**
     * Generator Copywriting Medsos (Status WA, Grup Facebook Inhu, Feed IG)
     */
    public static function generateSocialCopy(string $categoryName, string $districtName, string $platform): array {
        $apiKey = get_setting('gemini_api_key', '');

        if (!empty($apiKey)) {
            $geminiResult = self::callGeminiSocialCopy($apiKey, $categoryName, $districtName, $platform);
            if ($geminiResult !== false) {
                return $geminiResult;
            }
        }

        return self::buildLocalSocialCopy($categoryName, $districtName, $platform);
    }

    /**
     * Generator Spotlight Mitra Harian (Rekomendasi Toko Spesifik)
     */
    public static function generatePartnerSpotlight(int $providerId): array {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT sp.*, u.name as owner_name, u.phone, sc.name as category_name, d.name as district_name,
                   (SELECT COUNT(*) FROM reviews r WHERE r.provider_id = sp.id) as real_reviews
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            JOIN service_categories sc ON sp.primary_category_id = sc.id
            LEFT JOIN districts d ON sp.district_id = d.id
            WHERE sp.id = ?
            LIMIT 1
        ");
        $stmt->execute([$providerId]);
        $provider = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$provider) {
            return [
                'status'  => 'error',
                'message' => 'Mitra tidak ditemukan.'
            ];
        }

        $business = $provider['business_name'];
        $cat = $provider['category_name'];
        $district = $provider['district_name'] ?: 'Kabupaten Indragiri Hulu';
        $rating = number_format((float)$provider['rating_avg'], 1);
        $jobs = (int)$provider['completed_jobs'];
        $verifiedText = $provider['is_verified'] ? "✅ Terverifikasi KTP & Usaha Resmi" : "🌟 Mitra Rekanan JASA INHU";
        $url = BASE_URL . "/provider_detail.php?id=" . $provider['id'];

        $post = "🌟 *REKOMENDASI MITRA TERBAIK INHU HARI INI* 🌟\n\n"
            . "Lagi pusing cari tukang atau teknisi **{$cat}** yang jujur dan berpengalaman di sekitar **{$district}**?\n\n"
            . "👉 Kenalin: *{$business}*\n"
            . "⭐ Rating: {$rating} / 5.0 ({$jobs} pekerjaan sukses diselesaikan)\n"
            . "🛡️ Status: {$verifiedText}\n\n"
            . "💬 *Kenapa Warga Inhu Puas Langganan di Sini?*\n"
            . "• Pengerjaan rapi, teliti, dan komunikatif\n"
            . "• Tarif transparan dan bersahabat untuk kantong warga\n"
            . "• Bisa konsultasi gratis terlebih dahulu sebelum mulai kerja\n\n"
            . "📲 *Mau Pesan / Tanya Ongkos Sekarang?*\n"
            . "Cek profil toko & langsung hubungi via WhatsApp resmi:\n"
            . "🔗 {$url}\n\n"
            . "#jasainhu #mitrainhu #kabupatenindragirihulu #jasa" . strtolower(str_replace([' ', '&'], '', $cat)) . " #" . strtolower(str_replace(' ', '', $district));

        return [
            'status'   => 'success',
            'title'    => "Spotlight Mitra: {$business}",
            'copy'     => $post,
            'provider' => $provider
        ];
    }

    /**
     * Memanggil Google Gemini untuk Video Script
     */
    private static function callGeminiVideoScript(string $apiKey, string $categoryName, string $districtName, string $angle): array|false {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);

        $prompt = "Kamu adalah Creative Director dan Copywriter Video TikTok/Reels spesialis konten lokal Indonesia dan Riau.\n"
            . "Buatlah 1 script video TikTok / Instagram Reels durasi 30-45 detik yang sangat viral, relatable, dan memicu rasa penasaran untuk mempromosikan platform digital JASA INHU (marketplace jasa lokal Kabupaten Indragiri Hulu, Riau).\n\n"
            . "Target Kategori Jasa: {$categoryName}\n"
            . "Target Wilayah: {$districtName}\n"
            . "Sudut Pandang / Angle: {$angle}\n\n"
            . "Format Output harus dalam bentuk JSON valid dengan struktur kunci persis seperti berikut:\n"
            . "{\n"
            . "  \"title\": \"Judul Konsep Video\",\n"
            . "  \"hook\": \"Kalimat hook 3 detik pertama yang bikin orang berhenti scrolling\",\n"
            . "  \"visual_hook\": \"Deskripsi apa yang harus disorot kamera pada 3 detik pertama\",\n"
            . "  \"scenes\": [\n"
            . "    {\"scene\": 1, \"visual\": \"keterangan visual\", \"voiceover\": \"kalimat narasi dialog\"},\n"
            . "    {\"scene\": 2, \"visual\": \"keterangan visual\", \"voiceover\": \"kalimat narasi dialog\"},\n"
            . "    {\"scene\": 3, \"visual\": \"keterangan visual\", \"voiceover\": \"kalimat narasi dialog\"}\n"
            . "  ],\n"
            . "  \"call_to_action\": \"Kalimat penutup mengajak orang buka jasainhu.gafahrandi.my.id atau tanya Kak Inhu\",\n"
            . "  \"caption\": \"Teks caption postingan siap upload\",\n"
            . "  \"hashtags\": \"#hashtag1 #hashtag2\"\n"
            . "}\n"
            . "Berikan HANYA JSON tanpa awalan atau akhiran markdown triple backticks.";

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature'     => 0.8,
                'maxOutputTokens' => 1200,
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $json = json_decode($response, true);
            $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $rawText = trim($rawText);
            // Bersihkan jika ada codefence
            $rawText = preg_replace('/^```(?:json)?\s*/i', '', $rawText);
            $rawText = preg_replace('/\s*```$/', '', $rawText);
            $parsed = json_decode($rawText, true);
            if (is_array($parsed) && isset($parsed['hook'])) {
                $parsed['status'] = 'success';
                $parsed['engine'] = 'Google Gemini AI';
                return $parsed;
            }
        }

        return false;
    }

    /**
     * Memanggil Google Gemini untuk Social Copy
     */
    private static function callGeminiSocialCopy(string $apiKey, string $categoryName, string $districtName, string $platform): array|false {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);

        $prompt = "Buatkan teks promosi media sosial yang sangat menggugah dan persuasif untuk mengajak warga memesan jasa lewat platform JASA INHU (marketplace jasa lokal Kabupaten Indragiri Hulu, Riau).\n"
            . "Kategori: {$categoryName}\n"
            . "Wilayah: {$districtName}\n"
            . "Platform: {$platform} (Gaya bahasa sesuaikan: jika WhatsApp buat akrab dan singkat, jika Facebook buat cerita warga / info komunitas, jika Instagram buat estetik & informatif).\n"
            . "Sertakan CTA untuk buka https://jasainhu.gafahrandi.my.id atau tanya asisten AI 'Kak Inhu'.\n"
            . "Berikan jawaban teks lengkap siap pakai dengan emoji menarik.";

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature'     => 0.75,
                'maxOutputTokens' => 800,
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $json = json_decode($response, true);
            $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if (trim($rawText) !== '') {
                return [
                    'status' => 'success',
                    'engine' => 'Google Gemini AI',
                    'copy'   => trim($rawText)
                ];
            }
        }

        return false;
    }

    /**
     * Matriks Script TikTok Lokal Inhu (Heuristik Cerdas Berdasarkan Angle)
     */
    private static function buildLocalVideoScript(string $categoryName, string $districtName, string $angle): array {
        $loc = ($districtName && $districtName !== 'Seluruh Inhu') ? $districtName : 'Indragiri Hulu';

        if (str_contains(strtolower($categoryName), 'ac')) {
            $hook = "Warga {$loc} jangan mau kena getok harga servis AC lagi! Tonton ini dulu!";
            $visualHook = "Sorot AC yang meneteskan air ke ember atau kipas angin yang ditaruh dekat kasur.";
            $scenes = [
                ['scene' => 1, 'visual' => "Kamera menyorot ekspresi gerah kepanasan di kamar sambil mengipasi diri pakai buku.", 'voiceover' => "Lagi enak-enak tidur di {$loc}, tiba-tiba AC kamar netes air dan cuma keluar angin anget doang? Mana cuaca lagi terik-teriknya!"],
                ['scene' => 2, 'visual' => "Layar HP membuka website jasainhu.gafahrandi.my.id dan klik ikon 'Tanya Kak Inhu' di kanan bawah.", 'voiceover' => "Sekarang gak usah repot nanya-nanya ke grup WA. Buka aja Jasa Inhu, langsung tanya ke Kak Inhu (asisten AI). Otomatis dicarikan teknisi AC terdekat yang tarifnya transparan dan terverifikasi KTP!"],
                ['scene' => 3, 'visual' => "Tampilan kartu teknisi AC berating 5 bintang dengan tombol 'Chat WA Langsung'.", 'voiceover' => "Tinggal klik 'Chat WA Langsung', teknisi langsung meluncur ke rumah tanpa perantara ribet. Dinginnya balik, kantong aman!"]
            ];
            $cta = "Yuk simpan website ini sekarang: jasainhu.gafahrandi.my.id (Link ada di bio!).";
            $caption = "AC kamar di {$loc} mulai netes atau bau apek? Jangan tunggu kompresor jebol! ❄️ Langsung carikan teknisi terdekat di JASA INHU!";
            $hashtags = "#ServisACInhu #Info" . str_replace(' ', '', $loc) . " #JasaInhu #Rengat #AirMolek #Belilas";
        } elseif (str_contains(strtolower($categoryName), 'motor')) {
            $hook = "Pernah gak motor kamu mati mendadak di jalan sepi daerah {$loc}? Panik banget!";
            $visualHook = "Kamera merekam motor yang didorong di pinggir jalan lintas sambil melihat HP kebingungan.";
            $scenes = [
                ['scene' => 1, 'visual' => "Pengendara mendorong motor di pinggir jalan dengan muka lelah.", 'voiceover' => "Momen paling apes di {$loc}: rantai putus atau ban bocor pas mau buru-buru kerja atau pulang malam."],
                ['scene' => 2, 'visual' => "Buka HP, masuk ke website Jasa Inhu, ketik 'Motor mogok di {$loc}'.", 'voiceover' => "Jangan panik dorong jauh-jauh. Sekarang ada JASA INHU! Ada montir panggilan siaga yang siap datang ke lokasi kamu."],
                ['scene' => 3, 'visual' => "Mekanik datang bawa kunci-kunci servis, motor kembali menyala.", 'voiceover' => "Bisa cek ulasan pelanggan lain dan chat WA langsung mekaniknya. Praktis banget kan?"]
            ];
            $cta = "Wajib save link ini di HP kamu buat jaga-jaga: jasainhu.gafahrandi.my.id!";
            $caption = "Warga {$loc} wajib simpan ini! Kalo motor mogok di jalan, gak usah panik dorong motor. Panggil montir terdekat via Jasa Inhu! 🏍️💨";
            $hashtags = "#BengkelMotorInhu #MontirPanggilan #Info" . str_replace(' ', '', $loc) . " #JasaInhu #Rengat";
        } elseif (str_contains(strtolower($categoryName), 'sawit') || str_contains(strtolower($categoryName), 'kebun')) {
            $hook = "Punya kebun sawit di {$loc} tapi pusing nyari regu panen yang jujur dan gesit?";
            $visualHook = "Kamera menyorot buah sawit yang sudah masak di pohon atau hamparan kebun kelapa sawit.";
            $scenes = [
                ['scene' => 1, 'visual' => "Buah sawit mulai lewat masa panen di pokok pohon.", 'voiceover' => "Musim panen tiba, tapi tenaga kerja langka? Kalo telat panen, brondolan rontok dan hasil timbangan anjlok!"],
                ['scene' => 2, 'visual' => "Buka portal Jasa Inhu, cari 'Jasa Tenaga Panen & Perawatan Sawit'.", 'voiceover' => "Kini pemilik kebun di {$loc} bisa langsung temukan tim panen borongan, jasa semprot piringan, dan tebas gawangan terpercaya di Jasa Inhu."],
                ['scene' => 3, 'visual' => "Tampilan profil regu panen lengkap dengan rating ulasan pemilik kebun lain.", 'voiceover' => "Bisa nego ongkos borongan langsung sama ketuanya via WhatsApp. Hasil panen tuntas, kebun pun terawat rapi!"]
            ];
            $cta = "Cek regu panen sawit terdekat di jasainhu.gafahrandi.my.id!";
            $caption = "Solusi pemilik kebun sawit se-Indragiri Hulu! Cari regu panen, pemupukan, dan semprot gulma sekarang semudah klik HP di Jasa Inhu! 🌴🚜";
            $hashtags = "#PetaniSawitInhu #PanenSawit #SawitRiau #Belilas #Seberida #JasaInhu";
        } else {
            // General Category Template
            $hook = "Warga {$loc} ternyata banyak yang belum tahu trik gampang cari tukang ini!";
            $visualHook = "Orang sedang scrolling HP sambil melihat rumah atau barang rusak, lalu tersenyum saat menemukan solusinya.";
            $scenes = [
                ['scene' => 1, 'visual' => "Tampilan berbagai kebutuhan rumah: pipa bocor, pagar besi rusak, instalasi listrik, dsb.", 'voiceover' => "Kalo lagi butuh tukang {$categoryName} di {$loc}, biasanya pada bingung mau nanya ke siapa kan?"],
                ['scene' => 2, 'visual' => "Website jasainhu.gafahrandi.my.id dibuka, klik 'Tanya Kak Inhu' robot AI.", 'voiceover' => "Tinggal buka Jasa Inhu! Platform digital khusus Kabupaten Indragiri Hulu. Ada asisten pintar 'Kak Inhu' yang langsung carikan mitra terdekat dalam hitungan detik."],
                ['scene' => 3, 'visual' => "Pilihan mitra lengkap dengan badge Terverifikasi KTP dan tombol Chat WA.", 'voiceover' => "Mitra-mitranya sudah terverifikasi KTP, ada ulasan jujur warga sekitar, dan tarifnya hemat bersahabat!"]
            ];
            $cta = "Coba sekarang dan rasakan kemudahannya di jasainhu.gafahrandi.my.id!";
            $caption = "Cari tukang dan jasa apa aja di {$loc} sekarang gak pake ribet! Temukan mitra terverifikasi di JASA INHU! 🚀";
            $hashtags = "#JasaInhu #Info" . str_replace(' ', '', $loc) . " #IndragiriHulu #LayananJasaLokal";
        }

        return [
            'status'         => 'success',
            'engine'         => 'Smart Local Heuristic Matrix',
            'title'          => "Script Video {$categoryName} - {$loc}",
            'hook'           => $hook,
            'visual_hook'    => $visualHook,
            'scenes'         => $scenes,
            'call_to_action' => $cta,
            'caption'        => $caption,
            'hashtags'       => $hashtags
        ];
    }

    /**
     * Generator Copywriting Status WA / FB Group
     */
    private static function buildLocalSocialCopy(string $categoryName, string $districtName, string $platform): array {
        $loc = ($districtName && $districtName !== 'Seluruh Inhu') ? $districtName : 'Indragiri Hulu';

        if ($platform === 'whatsapp_story') {
            $copy = "❄️ *Lagi butuh {$categoryName} di sekitar {$loc}?*\n\n"
                . "Gak usah pusing nanya ke sana-ke mari. Sekarang ada platform resmi warga Inhu:\n"
                . "👉 *jasainhu.gafahrandi.my.id*\n\n"
                . "Tinggal tanya ke *'Kak Inhu'* (Asisten AI di pojok kanan bawah), langsung dicarikan mitra terdekat yang siap datang ke rumah! 🚀\n\n"
                . "⭐ Rating transparan\n"
                . "🛡️ Mitra Terverifikasi KTP\n"
                . "📲 Langsung Chat WA ke tukangnya!";
        } elseif ($platform === 'facebook_group') {
            $copy = "Assalamu'alaikum & Halo warga grup {$loc} dan sekitarnya 🙏\n\n"
                . "Izin berbagi info bermanfaat untuk bapak/ibu yang mungkin lagi butuh bantuan pengerjaan *{$categoryName}* (atau servis rumah, motor, kulkas, las, dll).\n\n"
                . "Sekarang Kabupaten Indragiri Hulu sudah punya website marketplace jasa lokal sendiri, namanya **JASA INHU**:\n"
                . "🌐 https://jasainhu.gafahrandi.my.id\n\n"
                . "✅ Semua mitra terverifikasi identitas KTP resminya\n"
                . "✅ Ada fitur asisten pintar (Kak Inhu AI) yang otomatis mencarikan teknisi paling dekat dengan rumah bapak/ibu\n"
                . "✅ Tarif bersahabat & langsung terhubung ke WhatsApp mitra tanpa potongan calo\n\n"
                . "Semoga info ini membantu bapak/ibu yang sedang butuh bantuan perbaikan. Silakan disimpan ya link-nya siapa tahu sewaktu-waktu butuh darurat! Terima kasih admin grup. 🙏";
        } else {
            // Instagram / Facebook Feed Post
            $copy = "✨ *Solusi Cepat Kebutuhan {$categoryName} di {$loc}!* ✨\n\n"
                . "Sering ngerasa repot pas barang di rumah rusak tapi gak tau tukang mana yang bisa dipercaya dan gak nembak harga?\n\n"
                . "Kini hadir **JASA INHU** — pusat berkumpulnya para penyedia jasa profesional dan pekerja terampil se-Kabupaten Indragiri Hulu!\n\n"
                . "Kenapa harus cari di JASA INHU?\n"
                . "🔹 Mitra Terverifikasi KTP & Usaha\n"
                . "🔹 Ada Asisten Pintar 24 Jam 'Kak Inhu' siap carikan mitra terdekat\n"
                . "🔹 Pilihan tarif fleksibel & ulasan transparan dari sesama warga\n\n"
                . "👉 Kunjungi: https://jasainhu.gafahrandi.my.id (Klik link di Bio!)\n\n"
                . "#jasainhu #layanan" . strtolower(str_replace(' ', '', $categoryName)) . " #info" . strtolower(str_replace(' ', '', $loc)) . " #kabupatenindragirihulu #rengat #airmolek #belilas #peranap";
        }

        return [
            'status' => 'success',
            'engine' => 'Smart Local Matrix',
            'copy'   => $copy
        ];
    }
}

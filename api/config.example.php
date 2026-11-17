<?php
// Bu dosyayı "kv_config.php" adıyla public_html'in BİR ÜST klasörüne (ana dizininize) kopyalayın
// ve anahtarınızı yazın. O konuma koyamıyorsanız bu klasörde "config.php" adıyla da çalışır.
return [
    'ANTHROPIC_API_KEY'  => 'BURAYA_API_ANAHTARINIZI_YAZIN',
    'MODEL'              => 'claude-haiku-5-5',
    'LIMIT_PER_IP_10MIN' => 15,    // bir ziyaretçi 10 dakikada en fazla kaç soru sorabilir
    'LIMIT_PER_DAY'      => 2000,  // sitenin günlük toplam soru sınırı (bütçe koruması)
    'MAX_TOKENS'         => 900,
    'TRANSLATE_PER_DAY'  => 400,   // sesli anlatım için günlük en fazla yeni çeviri (çeviriler bir kez yapılıp saklanır)
    'REKLAM_RAPOR_ANAHTARI' => '',  // reklam istatistiklerini görmek için kendi belirlediğiniz uzun bir parola: /api/reklam.php?rapor=PAROLA

    // ---- Gerçek sesli anlatım (MP3) ----
    'TTS_PROVIDER'     => 'google',          // 'google' (Chirp 3 HD) veya 'elevenlabs'
    'GOOGLE_TTS_KEY'   => '',                // Google Cloud > API ve Hizmetler > Kimlik bilgileri > API anahtarı (yalnızca Text-to-Speech API'ye kısıtlayın)
    'GOOGLE_TTS_VOICE' => 'Charon',          // Chirp 3 HD ses adı: Charon, Kore, Puck, Aoede, Orus, Leda…
    'ELEVENLABS_KEY'   => '',
    'ELEVENLABS_VOICE_ID' => '',             // ElevenLabs'te seçtiğiniz sesin kimliği
    'TTS_PER_DAY'      => 300,               // günde en fazla kaç yeni ses dosyası üretilsin (bütçe koruması)
    'ERISIM_RAPOR_ANAHTARI' => '',         // erişim bildirimlerini görmek için parola: /api/erisim.php?rapor=PAROLA (boşsa reklam parolası kullanılır)
    'TTS_TOPLU_PAROLA' => '',                // toplu üretim için uzun bir parola: /api/seslendir.php?toplu=PAROLA
];

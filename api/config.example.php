<?php
// Bu dosyayı "kv_config.php" adıyla public_html'in BİR ÜST klasörüne (ana dizininize) kopyalayın
// ve anahtarınızı yazın. O konuma koyamıyorsanız bu klasörde "config.php" adıyla da çalışır.
return [
    'ANTHROPIC_API_KEY'  => 'BURAYA_API_ANAHTARINIZI_YAZIN',
    'MODEL'              => 'claude-haiku-5-5',
    'LIMIT_PER_IP_10MIN' => 15,    // bir ziyaretçi 10 dakikada en fazla kaç soru sorabilir
    'LIMIT_PER_DAY'      => 2000,  // sitenin günlük toplam soru sınırı (bütçe koruması)
    'MAX_TOKENS'         => 700,
];

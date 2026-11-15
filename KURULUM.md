# kulturvarliklari.com.tr — Yayına alma rehberi

Bu klasör, sitenin yayına hazır statik hâlidir. Sunucuda veritabanı, PHP veya başka bir yazılım gerekmez; dosyaları yüklemeniz yeterlidir.

## Paketin içeriği

| Dosya / klasör | Ne işe yarar |
|---|---|
| `index.html` | Ana sayfa: slayt, akıllı arama, filtreler, sesli tur, "Rehbere sor" |
| `yer/` | Her müze ve ören yeri için ayrı sayfa (358 sayfa, Google'da tek tek çıkabilmesi için) |
| `img/` | 264 fotoğraf (Wikimedia Commons, özgür lisanslı) |
| `fonts/` | Yazı tipleri (sitede barınıyor; Google'a istek gitmez) |
| `kaynaklar.html` | Fotoğraf lisansları (silinmemeli) |
| `404.html` | Bulunamayan sayfa |
| `sitemap.xml`, `robots.txt` | Arama motorları için |
| `.htaccess` | Apache sunucular için: HTTPS yönlendirme, önbellek, 404 |
| `site.css`, `favicon.svg` | Ortak stil ve site simgesi |
| `api/` | Yapay zeka rehberinin sunucu kodu (PHP) |

`KURULUM.md` (bu dosya) sunucuya yüklenmek zorunda değildir.

## Seçenek A — Mevcut hosting (cPanel / Plesk)

1. Hosting panelinize girin, **Dosya Yöneticisi**'ni açın.
2. `public_html` (veya alan adının kök klasörü) içine bu klasördeki **tüm dosyaları** yükleyin. Gizli dosya olan `.htaccess`'in de yüklendiğinden emin olun.
3. Panelden **SSL / Let's Encrypt / AutoSSL** ile ücretsiz sertifikayı etkinleştirin.
4. `https://kulturvarliklari.com.tr` adresini açıp kontrol edin.

## Seçenek B — Ücretsiz statik hosting (Cloudflare Pages veya Netlify)

1. Cloudflare Pages ya da Netlify'da hesap açın.
2. "Yeni site" → "Dosyaları sürükle-bırak" ile bu klasörü yükleyin.
3. "Custom domain" bölümünden `kulturvarliklari.com.tr` ekleyin.
4. Alan adını aldığınız firmanın panelinde, platformun verdiği DNS kayıtlarını (veya ad sunucularını) girin. Değişikliğin yayılması birkaç saat sürebilir.
5. SSL bu platformlarda otomatik gelir.

## Yapay zeka rehberini açma (API anahtarı)

"Rehbere sor" bölümü, API anahtarı girilince yapay zeka (Claude Haiku 5.5) ile yanıt verir. Anahtar girilmezse site yine çalışır; rehber bu durumda sitedeki kayıtlı bilgilerden yanıt verir.

1. **Anahtarı alın:** platform.claude.com adresinde hesap açın, ödeme yöntemi ekleyin ve **aylık harcama limiti** belirleyin (örneğin 10 $). Ardından yeni bir API anahtarı oluşturun. Anahtarı kimseyle paylaşmayın, sohbetlere veya e-postaya yapıştırmayın.
2. **PHP sürümü:** cPanel'de "PHP Sürümü Seç" bölümünden **PHP 8.0 veya üstünü** seçin; `curl` eklentisinin açık olduğundan emin olun (çoğu hostingde varsayılan olarak açıktır).
3. **Anahtarı yerleştirin:** Dosya Yöneticisi'nde `public_html/api/config.example.php` dosyasını, `public_html`'in **bir üst klasörüne** (ana dizininiz) `kv_config.php` adıyla kopyalayın. Dosyayı düzenleyip `BURAYA_API_ANAHTARINIZI_YAZIN` yerine anahtarınızı yazın ve kaydedin.
   - Ana dizine dosya koyamıyorsanız aynı dosyayı `public_html/api/config.php` adıyla da oluşturabilirsiniz; `.htaccess` bu dosyaya dışarıdan erişimi engeller.
4. **Deneyin:** Sitede "Rehbere sor" bölümüne bir soru yazın. Yanıt yazılırken üç nokta animasyonu görünür ve yanıttaki yer adları tıklanabilir olur.

**Güvenlik ve bütçe korumaları (config dosyasından değiştirilebilir):**
- Bir ziyaretçi 10 dakikada en fazla 15 soru sorabilir.
- Sitenin günlük toplam soru sınırı 2000'dir; aşılırsa rehber o gün kayıtlı bilgilerle yanıt vermeye devam eder.
- İstekler yalnızca kendi alan adınızdan kabul edilir; anahtar tarayıcıya hiç gönderilmez.

**Maliyet:** Her soru, sitedeki tüm yer listesini (yaklaşık 9.000 token) bağlam olarak gönderir. Haiku 5.5 fiyatlarıyla bu, soru başına kabaca 0,1 ABD senti eder (yaklaşık 1.000 soru ≈ 1 $). Aynı bağlam önbellekten okunduğunda daha da ucuzdur. Yine de harcama limiti koymanızı öneririz.

**Kişisel veri:** Ziyaretçi soruları yanıt üretmek için Anthropic'e (yurt dışındaki bir hizmet sağlayıcıya) iletilir. Sitede bu konuda bir uyarı var; KVKK açısından bir aydınlatma metni eklemeniz gerekip gerekmediğini bir hukukçuya danışın.

## Yayından sonra

1. **Google Search Console**'a `kulturvarliklari.com.tr` alan adını ekleyin ve doğrulayın.
2. "Site haritaları" bölümüne `sitemap.xml` yazıp gönderin.

## Bilmeniz gerekenler

- **Fotoğraf lisansları:** Fotoğrafların çoğu CC BY / CC BY-SA lisanslıdır. Fotoğrafçı adının ve lisansın görünmesi lisansın şartıdır. Bu bilgiler her yerin detayında ve `kaynaklar.html`'de var; kaldırmayın.
- **Sesli anlatım ve sesli tur:** Ziyaretçinin tarayıcısındaki yerleşik sesle okunur; ses kalitesi cihaza göre değişir.
- **Çerez:** Site çerez kullanmıyor ve ziyaretçi takibi yapmıyor. İleride ölçüm aracı eklerseniz KVKK kapsamında aydınlatma metni ve çerez onayı gerekir (hukuki danışmanlık değildir).
- **Metinler:** 8 yerin ayrıntılı tarihçesi var; diğerlerinde kısa bir tanıtım duruyor. Tarihsel metinlerin yayından önce bir uzman tarafından gözden geçirilmesi önerilir.

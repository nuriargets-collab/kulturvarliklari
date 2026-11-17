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

## Ek dillerde sesli anlatım (Rusça, Fransızca, İspanyolca, Arapça)

Yer sayfalarındaki "Diğer diller" menüsü, metni ilk istendiğinde yapay zekâ ile çevirir ve sunucuda saklar (`public_html`'in bir üst klasöründe `kv_cache` klasörü oluşur; oluşturulamazsa `api/cache` kullanılır). Aynı yerin aynı dildeki çevirisi bir daha API'ye gitmez. Tüm yerlerin dört dile çevirisi toplamda yaklaşık 1.400 çeviridir ve tek seferlik maliyeti birkaç dolar civarındadır. Günlük yeni çeviri sınırı `TRANSLATE_PER_DAY` ayarıyla (varsayılan 400) değiştirilebilir. API anahtarı yoksa bu menü "çeviri şu anda alınamadı" uyarısı verir; TR, EN ve DE her durumda çalışır.

## Gerçek sesli anlatım (MP3)

Her yer sayfasındaki "Dinle" düğmesi önce sunucudaki gerçek ses kaydını (`ses/<dil>/<SID>-<özet>.mp3`) çalar. Kayıt yoksa tarayıcının kendi sesine geçer ve arka planda kaydın üretilmesini ister. Metin düzeltildiğinde dosya adı değişir, yeni kayıt kendiliğinden üretilir.

1. **Google Cloud hesabı (önerilen):** console.cloud.google.com → yeni proje → "Cloud Text-to-Speech API"yi etkinleştirin → faturalandırmayı açın → *API ve Hizmetler › Kimlik bilgileri › API anahtarı oluştur*. Anahtarı **yalnızca Text-to-Speech API** ile kısıtlayın.
2. `kv_config.php` dosyasına (public_html'in bir üst klasörü) şunları ekleyin. Anahtarı yalnızca bu dosyaya yazın; sohbete veya depoya yazmayın:
   ```php
   'TTS_PROVIDER'     => 'google',
   'GOOGLE_TTS_KEY'   => 'anahtarınız',
   'GOOGLE_TTS_VOICE' => 'Charon',       // erkek; kadın ses için 'Kore' veya 'Aoede'
   'TTS_TOPLU_PAROLA' => 'uzun-bir-parola',
   ```
3. Tüm kayıtları bir kerede üretmek için tarayıcıda `https://kulturvarliklari.com.tr/api/seslendir.php?toplu=PAROLA` adresini açın. Her açılış yaklaşık 25 saniye çalışır; `"kalan": 0` görünene kadar sayfayı yenileyin.
4. Maliyet: 358 yerin Türkçe, İngilizce ve Almanca metinleri toplam yaklaşık 516.000 karakterdir. Chirp 3 HD fiyatıyla (1 milyon karakter 30 ABD doları) bütün site **bir kerelik yaklaşık 16 ABD doları** tutar. Sonraki dinlemeler ücretsizdir; dosyalar sunucuda kalır.
5. ElevenLabs tercih ederseniz: `'TTS_PROVIDER' => 'elevenlabs'`, `'ELEVENLABS_KEY'` ve `'ELEVENLABS_VOICE_ID'` yazın. Maliyeti bugünkü fiyatla yaklaşık 3 kat yüksektir.
6. Rusça, Fransızca, İspanyolca ve Arapça için önce yapay zekâ çevirisi gerekir; çevirisi yapılmış metinlerin sesi de aynı yöntemle üretilir.

Ses dosyaları `ses/` klasöründe durur. GitHub yayını bu klasöre dokunmaz.

## Engelsiz erişim rehberi

- Her yer sayfasında "♿ Engelsiz erişim" bölümü ve ziyaretçilerin bilgi gönderebileceği bir form vardır. Genel bilgi sayfası `engelsiz.html` adresindedir.
- Gönderilen bildirimler web kökünün dışındaki `kv_cache/erisim/bildirimler.jsonl` dosyasında saklanır. Listelemek için `kv_config.php` içine `'ERISIM_RAPOR_ANAHTARI' => 'parola'` yazın ve `/api/erisim.php?rapor=parola` adresini açın.
- **Yayımlama:** Doğruladığınız bilgiler `erisim.json` dosyasına işlenir ve site yeniden üretilir. Alanların değeri `var`, `kismen` veya `yok` olabilir:
  ```json
  {"EFS01": {"tekerlekli": "kismen", "yol": "kismen", "wc": "var", "otopark": "var",
             "not": {"tr": "Ana cadde boyunca düz yol; tiyatroya basamaklı çıkış."},
             "tarih": "2026-11", "kaynak": "Müze müdürlüğü"}}
  ```
  Alanlar: tekerlekli, yol, asansor, wc, otopark, sandalye, dinlenme, dokunsal, sesli, isaret.
- Tahmine dayalı bilgi yayımlamayın. Müze müdürlüklerinden yazılı bilgi istemek en güvenilir yoldur.

## Cloudflare (hız ve güvenlik)

Ücretsiz plan yeterlidir. **Sıra önemlidir; e-posta ve FTP kesintisi yaşamamak için adımları atlamayın.**

1. dash.cloudflare.com → *Add a site* → `kulturvarliklari.com.tr` → **Free** planı seçin.
2. Cloudflare mevcut DNS kayıtlarını tarar. cPanel'deki *Zone Editor* ile karşılaştırın; özellikle **MX, SPF/DKIM (TXT), mail, webmail, cpanel, ftp** kayıtlarının hepsinin listede olduğundan emin olun.
3. Turuncu bulut (proxy) **yalnızca** `kulturvarliklari.com.tr` ve `www` kayıtlarında açık olsun. `ftp`, `mail`, `webmail`, `cpanel`, `cpcalendars` ve benzeri kayıtları **gri bulut (DNS only)** yapın. Aksi hâlde FTP yayını ve e-posta çalışmaz.
4. GitHub'daki `SERVER` gizli değerini `ftp.kulturvarliklari.com.tr` (gri bulutlu kayıt) ya da hostinginizin verdiği sunucu adı yapın, ana alan adını kullanmayın.
5. Alan adını aldığınız firmanın panelinde ad sunucularını (nameserver) Cloudflare'in verdiği iki adresle değiştirin. Geçiş birkaç saat sürebilir.
6. **SSL/TLS → Overview → Full (strict).** "Flexible" seçmeyin; site sonsuz yönlendirmeye girer. cPanel'de AutoSSL sertifikasının etkin olduğunu kontrol edin.
7. **SSL/TLS → Edge Certificates:** *Always Use HTTPS* açık, *Automatic HTTPS Rewrites* açık. HSTS'yi her şey sorunsuz çalıştıktan birkaç hafta sonra açın.
8. **Speed:** Brotli, HTTP/3 ve Early Hints açık. **Rocket Loader kapalı** (sitenin betiklerini bozabilir).
9. **Caching → Configuration:** *Browser Cache TTL: Respect Existing Headers*. Görseller, MP3 ve betikler varsayılan olarak Cloudflare'de önbelleğe alınır. Sayfalar ve `api/` önbelleğe alınmaz, bu doğrudur.
10. **Security:** *Bot Fight Mode* açılabilir (Google gibi doğrulanmış botlar engellenmez). *Security → WAF → Rate limiting rules* ile `/api/` yolu için "10 saniyede 20 istekten fazlası → engelle" kuralı ekleyin.
11. **Web Analytics:** *Analytics & Logs → Web Analytics* → siteyi ekleyin ve verilen jetonu bize iletin; `site_config.json` dosyasına eklenecek. Bu istatistik çerez kullanmaz.
12. Site Cloudflare arkasına geçtiğinde PHP dosyaları ziyaretçinin gerçek IP adresini `CF-Connecting-IP` başlığından alır. Başlığa yalnızca istek gerçekten Cloudflare'den geliyorsa güvenilir; ek ayar gerekmez.

## Telefona uygulama gibi kurulum ve çevrimdışı kullanım

Site bir PWA'dır: ziyaretçi telefonda tarayıcı menüsünden "Ana ekrana ekle" ile siteyi uygulama gibi kurabilir. Açılan sayfalar cihazda saklanır; yer ve rota sayfalarındaki "Çevrimdışı kaydet" düğmesi sayfayı ve fotoğrafını internet olmadan açılabilecek şekilde kaydeder. Bu özellik yalnızca HTTPS ile çalışır (cPanel'de ücretsiz AutoSSL'in açık olduğundan emin olun).

## Google Search Console (arama motoru kaydı)

1. search.google.com/search-console adresinde **"URL ön eki"** seçeneğiyle `https://kulturvarliklari.com.tr` ekleyin.
2. Doğrulama yöntemi olarak **"HTML etiketi"**ni seçin. Size `<meta name="google-site-verification" content="XXXX">` gibi bir kod verilir. Bu koddaki `XXXX` değerini bana iletin (gizli bir bilgi değildir) ya da `sitegen/site_config.json` dosyasındaki `google_site_verification` alanına yazın; site yeniden oluşturulunca her sayfaya eklenir.
   - Alternatif: Google'ın verdiği `googleXXXX.html` dosyasını indirip doğrudan `public_html` klasörüne yükleyebilirsiniz.
3. Doğrulamadan sonra "Site haritaları" bölümüne `sitemap.xml` yazıp gönderin. Site haritası Türkçe, İngilizce ve Almanca sayfaları dil eşleştirmesiyle (hreflang) birlikte içerir.

## Ziyaret istatistikleri (çerezsiz)

Önerimiz **Cloudflare Web Analytics**: ücretsizdir, çerez kullanmaz ve kişiyi tanımlamaz; bu yüzden çerez onay penceresi gerektirmez.
1. dash.cloudflare.com'da ücretsiz hesap açın → **Web Analytics** → **Add a site** → `kulturvarliklari.com.tr`.
2. Size verilen kod parçasındaki `"token": "..."` değerini kopyalayın (gizli değildir) ve `sitegen/site_config.json` içindeki `cloudflare_analytics_token` alanına yazın ya da bana iletin. Site yeniden oluşturulduğunda tüm sayfalarda istatistik toplanmaya başlar.

## Harita

Harita altlığı OpenStreetMap'ten yüklenir; ücretsizdir, atıf (sağ alttaki yazı) kaldırılmamalıdır. Site çok yüksek trafiğe ulaşırsa OpenStreetMap'in kullanım politikası gereği ücretli bir harita sağlayıcısına (ör. MapTiler, Stadia) geçmek gerekebilir; bu durumda tek satırlık bir değişiklik yeterlidir. Konumların çoğu Wikidata'dan alınmıştır; bazılarında ilçe veya il merkezine göre yaklaşık konum kullanılır ve sayfada "yaklaşık" notu görünür.

## QR kodlu sesli rehber

`/qr/` sayfasında her il için yazdırılabilir QR kartları bulunur (arama motorlarına kapalıdır). Kod okutulduğunda yerin sayfası açılır ve "Dinle" düğmesi öne çıkar. Müzeler ve belediyelerle iş birliği yaparken bu kartları kullanabilirsiniz.

## Reklam alanı (yakında konaklama ve yemek)

Her yer sayfasında "🏨 Yakında konaklama ve yemek" düğmesi vardır (ana sayfadaki yer penceresinde de bağlantısı bulunur). Düğmeye basılınca o yerle eşleşen reklamlar "Reklam" etiketiyle gösterilir; reklam yoksa Google Haritalar'da yakındaki otel ve restoranlara bağlantı ve "Reklam verin" sayfasına yönlendirme görünür.

**Reklam eklemek:** cPanel Dosya Yöneticisi'nde `public_html` içinde `reklamlar.json` adında bir dosya oluşturun (yoksa) ve düzenleyin. Bu dosya site paketinde yer almaz; böylece siteyi her güncellediğinizde reklamlarınızın üzerine yazılmaz. Her reklam bir satırdır:

```json
[
  {
    "id": "ornek-otel-selcuk",
    "ad": "Örnek Otel",
    "tur": "Otel",
    "yerler": ["EFS01", "EFM01"],
    "lat": 37.9478, "lng": 27.3685, "mesafe_km": 10,
    "telefon": "+90 232 000 00 00",
    "web": "https://ornekotel.com",
    "aciklama": {"tr": "Efes'e 5 dakika, bahçeli butik otel.", "en": "Boutique hotel 5 minutes from Ephesus."},
    "gorsel": "img/reklam/ornek-otel.jpg",
    "baslangic": "2026-12-01", "bitis": "2027-05-31",
    "oncelik": 1
  }
]
```

- `yerler`: Reklamın kesin görüneceği yerlerin kodları (yer sayfasının adresindeki değil, kataloğdaki kod; örneğin Efes = EFS01). Kodları `data.json` dosyasında `sid` alanında bulabilirsiniz.
- `lat`, `lng`, `mesafe_km`: İşletmenin konumu; bu mesafedeki tüm yerlerde de gösterilir (varsayılan 15 km). Konumu Google Haritalar'da işletmeye sağ tıklayıp kopyalayabilirsiniz.
- `baslangic` / `bitis`: Reklamın yayın tarihleri. `"aktif": false` yazarak geçici olarak durdurabilirsiniz.
- `oncelik`: Büyük sayı üstte gösterilir (ör. üst paket = 2).
- `gorsel`: `public_html/img/reklam/` klasörüne yüklediğiniz fotoğraf (yaklaşık 400×300 piksel, JPG).
- Bir yer sayfasında en fazla 6 reklam gösterilir. JSON'da virgül ve tırnak hatası olursa reklamlar görünmez; düzenledikten sonra jsonlint.com gibi bir araçla kontrol edebilirsiniz.

**İstatistik:** Görüntülenme, web sitesi, telefon ve yol tarifi tıklamaları ay ay sayılır (kişisel veri tutulmaz). Görmek için `kv_config.php` dosyasına `'REKLAM_RAPOR_ANAHTARI' => 'uzun-bir-parola'` satırını ekleyin ve `https://kulturvarliklari.com.tr/api/reklam.php?rapor=uzun-bir-parola` adresini açın. Bu tabloyu reklamverenlere aylık rapor olarak gönderebilirsiniz.

**Yasal not:** Reklamlar "Reklam" etiketiyle ve sponsorlu bağlantı olarak işaretlenir. Reklam verenlerle fatura ve sözleşme konularında mali müşavirinize danışın.

## Kurumsal sayfalar

Hakkımızda, İletişim, KVKK Aydınlatma Metni ve Çerez Politikası sayfaları hazırdır. İletişim adresi olarak `iletisim@kulturvarliklari.com.tr` kullanıldı; bu e-posta hesabını cPanel → E-posta Hesapları bölümünden açın (veya başka bir adres isterseniz bana söyleyin). KVKK metni genel bir şablondur; yayından önce bir hukukçuya göstermeniz önerilir.

## Bilmeniz gerekenler

- **Fotoğraf lisansları:** Fotoğrafların çoğu CC BY / CC BY-SA lisanslıdır. Fotoğrafçı adının ve lisansın görünmesi lisansın şartıdır. Bu bilgiler her yer sayfasında ve `kaynaklar.html`'de var; kaldırmayın. "Wikimedia Commons fotoğraflarını göster" düğmesiyle yüklenen ek fotoğraflar doğrudan Commons'tan, yazar ve lisans bilgisiyle gösterilir.
- **Sesli anlatım:** Ziyaretçinin tarayıcısındaki yerleşik sesle okunur; ses kalitesi cihaza göre değişir.
- **Metinler:** Tüm yerlerin tarihçeleri özgün olarak yazılmıştır; yayından önce bir uzmanın gözden geçirmesi önerilir.

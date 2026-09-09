# قارئ المستندات الليبية 🇱🇾

[English](README.md) | **العربية**

**واجهة Laravel تركز على الخصوصية لقراءة جوازات السفر الليبية ومستندات مصلحة الأحوال المدنية المدعومة من الصور وملفات PDF الممسوحة ضوئيًا.**

Libya Document Reader هو مشروع مفتوح المصدر ومستقل للمطورين. يجمع بين OCR محلي، والرؤية الحاسوبية، والتحقق من MRZ وفق ICAO TD3، والاستخراج المنظم بالعربية والإنجليزية، وإشارات تحقق محافظة للمستندات، إضافة إلى حماية API Key وRate Limiting لكل عميل وCORS قابل للضبط.

> هذا المشروع ليس خدمة حكومية ليبية رسمية، وليس معتمدًا أو مصادقًا عليه أو تابعًا لجهات الجوازات أو مصلحة الأحوال المدنية الليبية. نجاح OCR أو MRZ أو وجود QR أو تطابق القالب أو اكتشاف الأختام لا يُعد إثباتًا على أن المستند أصلي.

## النسخة المنشورة Production

- الرابط الأساسي: `https://passport-api-production-6c63.up.railway.app`
- Swagger UI: `https://passport-api-production-6c63.up.railway.app/docs`
- OpenAPI: `https://passport-api-production-6c63.up.railway.app/openapi.yaml`
- Health Check: `https://passport-api-production-6c63.up.railway.app/health`
- إصدار الـAPI الحالي: `0.4.2-dev`

كل المسارات تحت `/api/v1/*` في النسخة المنشورة تتطلب API Key صالحًا. أما `/health` و`/docs` و`/openapi.yaml` فتبقى متاحة بدون مفتاح.

لإعطاء أي مبرمج أو شركة تعليمات جاهزة، استخدم [دليل العميل العربي](docs/API_CLIENT_GUIDE_AR.md).

## v0.4.2 — حماية الـAPI للإنتاج

تمت إضافة طبقة حماية مناسبة للاستخدام الفعلي:

- المصادقة باستخدام API Key في Header باسم `X-API-Key`.
- كل تطبيق أو عميل يمكن أن يحصل على مفتاح مستقل.
- النسخة المنشورة لا تخزن المفتاح النصي نفسه؛ يتم تخزين SHA-256 hash فقط.
- يتم تطبيق Rate Limit لكل عميل بعد نجاح المصادقة.
- الحد الافتراضي الحالي للعميل المنشور هو `60 طلبًا في الدقيقة` ما لم يتم تخصيص حد آخر له.
- يتم إرجاع `X-RateLimit-Limit` و`X-RateLimit-Remaining` في الاستجابة.
- عند تجاوز الحد يتم إرجاع `429` مع Header باسم `Retry-After`.
- تطبيقات الويب في المتصفح تخضع لقائمة CORS Origins مسموح بها.
- لا يجب وضع API Key في GitHub أو README أو كود Frontend عام.
- يجب إرسال المفتاح الحقيقي للعميل بصورة منفصلة عبر قناة خاصة.

### طريقة استخدام Authorize في Swagger

1. افتح Swagger من رابط `/docs`.
2. اضغط **Authorize**.
3. ضع API Key كما هو فقط، بدون كتابة `X-API-Key:` قبله.
4. اضغط **Authorize** مرة ثانية.
5. افتح الـendpoint المطلوب واضغط **Try it out**.

بعدها Swagger يرسل تلقائيًا:

```http
X-API-Key: YOUR_API_KEY
```

### مثال طلب مباشر

```bash
curl -X GET \
  'https://passport-api-production-6c63.up.railway.app/api/v1/meta' \
  -H 'Accept: application/json' \
  -H 'X-API-Key: YOUR_API_KEY'
```

### أخطاء الحماية الشائعة

- `401 missing_api_key` — لم يتم إرسال الـAPI Key.
- `401 invalid_api_key` — المفتاح المرسل غير صحيح أو غير معروف.
- `429 api_rate_limit_exceeded` — العميل تجاوز الحد المسموح من الطلبات في الدقيقة.

## v0.4.1 — إشارات التحقق لمستندات الأحوال المدنية

يضيف هذا الإصدار طبقة تحقق استرشادية تشمل:

- اكتشاف QR باستخدام OpenCV.
- فك QR محليًا عندما تسمح جودة الصورة بذلك.
- التحقق البنيوي من صيغة `CheckNumber:<UUID>` المعروفة.
- فحص مكان QR مقارنة بالنماذج المدعومة.
- اكتشاف مرشحات الأختام/البصمات ذات الحبر الأزرق.
- إرجاع مواقع نسبية للأختام بدون إرجاع قصاصات من الصورة.
- قياس العلامات النصية المتوقعة في القالب.
- فحص صيغ الأرقام الوطنية والتواريخ.
- حساب `signal_score` مجمع.
- حالات مثل `signals_consistent` و`partial_signals` و`review_recommended` و`insufficient_evidence`.
- عدم استدعاء أي رابط أو قاعدة بيانات لجهة الإصدار تلقائيًا.
- عدم إرجاع محتوى QR الخام.
- بقاء `authenticity_verified=false` إلى أن تتوفر مستقبلًا جهة إصدار موثوقة أو توقيع رقمي يمكن التحقق منه.

المستندات المدعومة حاليًا:

- **شهادة الإقامة** — Residence Certificate.
- **شهادة بالوضع العائلي** — Family Status Certificate.

فحوصات القالب مبنية على نماذج تم دعمها ومعايرتها للمشروع، ولا يتم تقديمها على أنها اعتماد رسمي لنموذج حكومي.

## v0.4 — قارئ مستندات الأحوال المدنية

يستخدم قارئ الأحوال المدنية Tesseract OCR بالعربية والإنجليزية ويجرب عدة أوضاع لتقسيم الصفحة (`PSM 4` و`3` و`11`). ويتم اختيار أقوى نتيجة OCR بصورة محافظة ثم دمج السطور المفيدة للاستخراج.

تتم قراءة OCR والتحقق على **الصفحة الكاملة بعد تجهيزها** حتى لا تضيع عناوين الصفحة أو QR أو رؤوس الجداول بسبب قصاصات مخصصة للجوازات.

يمكن لشهادة الوضع العائلي إرجاع صفوف أفراد الأسرة المكتشفة، مثل:

- الرقم الوطني.
- الاسم.
- صلة القرابة.
- تاريخ الميلاد.

ويمكن لشهادة الإقامة إرجاع حقول مثل:

- الرقم الوطني.
- رقم قيد العائلة.
- رقم ورقة العائلة.
- اسم الشخص.
- اسم الأب.
- اسم الأم.
- تاريخ الميلاد.
- المهنة.
- العنوان.
- تاريخ التسجيل في السجل المدني.

لا يتم اختراع الحقول المفقودة؛ يتم إرجاع ما استطاع OCR/Extractor اكتشافه فقط.

## v0.3.3 — ماسح الجوازات بالرؤية الحاسوبية

يتضمن ماسح الجوازات:

- اكتشاف حدود المستند باستخدام OpenCV.
- تصحيح منظور رباعي الزوايا.
- ضبط اتجاه صفحة الجواز أفقيًا عند الحاجة.
- قياس الضبابية والوهج وفرط التعريض.
- تحسين الصورة باستخدام ImageMagick.
- توليد عدة مناطق MRZ مرشحة.
- Adaptive local threshold مخصص للـMRZ.
- ترتيب مرشحات TD3 باستخدام بنية ICAO وCheck Digits.
- OCR للمنطقة المرئية بالعربية والإنجليزية.
- مقارنة محافظة بين البيانات المرئية وMRZ.

## مسارات المعالجة

### جواز السفر

`upload/PDF → document detection → perspective correction → quality gate → smart preprocessing → adaptive MRZ OCR → ICAO validation → Arabic/English visual OCR → comparison`

### مستندات الأحوال المدنية

`upload/PDF → full-page preparation → multi-layout Arabic/English OCR → document classification → structured extraction → QR/seal detection → template/field consistency → verification signals`

## API

كل أمثلة النسخة المنشورة أدناه تتطلب:

```http
X-API-Key: YOUR_API_KEY
```

### قراءة جواز سفر

`POST /api/v1/passport/scan`

```bash
curl -X POST \
  'https://passport-api-production-6c63.up.railway.app/api/v1/passport/scan' \
  -H 'Accept: application/json' \
  -H 'X-API-Key: YOUR_API_KEY' \
  -F 'passport=@passport.jpg'
```

### قراءة مستند أحوال مدنية

`POST /api/v1/civil-registry/scan`

```bash
curl -X POST \
  'https://passport-api-production-6c63.up.railway.app/api/v1/civil-registry/scan' \
  -H 'Accept: application/json' \
  -H 'X-API-Key: YOUR_API_KEY' \
  -F 'document=@civil-registry.pdf'
```

قد تتضمن الاستجابة الناجحة قسمًا مثل:

```json
{
  "data": {
    "document": {
      "type": "residence_certificate",
      "fields": {}
    },
    "verification": {
      "status": "partial_signals",
      "signal_score": 0.71,
      "authenticity_verified": false,
      "issuer_verification": {
        "status": "not_configured",
        "database_checked": false,
        "digital_signature_verified": false
      },
      "qr": {
        "detected": true,
        "decoded": true,
        "payload_format": "civil_registry_check_number",
        "structure_valid": true,
        "position_consistent": true,
        "issuer_lookup_performed": false
      },
      "seals": {
        "detected": true,
        "candidate_count": 1,
        "expected_location_match": true
      },
      "template": {
        "status": "consistent",
        "official_template_verified": false
      }
    }
  }
}
```

المثال توضيحي ولا يحتوي على بيانات هوية حقيقية.

### تحليل MRZ مباشرة

`POST /api/v1/passport/mrz/parse`

```bash
curl -X POST \
  'https://passport-api-production-6c63.up.railway.app/api/v1/passport/mrz/parse' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'X-API-Key: YOUR_API_KEY' \
  --data '{
    "line1": "P<LBYALGAIDI<<AYA<<<<<<<<<<<<<<<<<<<<<<<<<<<",
    "line2": "1234567897LBY9501016F3001019<<<<<<<<<<<<<<02"
  }'
```

بيانات MRZ في المثال اصطناعية للاختبار.

### Endpoints أخرى

- `POST /api/v1/passport/mrz/validate`
- `GET /api/v1/meta`
- `GET /health` — عام بدون مفتاح.
- `GET /openapi.yaml` — عام بدون مفتاح.
- Swagger UI: `/docs` — عام للتوثيق، والعمليات المحمية تستخدم Authorize.

## حدود الرفع والطلبات

إعدادات النسخة المنشورة حاليًا:

- الحد داخل Laravel للملف: حوالي `30 MB`.
- PHP `upload_max_filesize`: `32M`.
- PHP `post_max_size`: `40M` حتى يسمح بحجم multipart الإضافي.
- الملفات الأكبر قد ترجع HTTP `413 Content Too Large`.
- الصور أو المستندات غير المقروءة أو غير المدعومة قد ترجع `422`.
- تعطل إحدى خدمات OCR أو المعالجة اللازمة قد يرجع `503`.

يفضل ضغط/تصغير صور الكاميرا الضخمة قبل الرفع مع المحافظة على وضوح MRZ والنصوص.

## CORS وتطبيقات الويب

CORS يخص التطبيقات التي تعمل داخل المتصفح. إذا كان العميل Web App، يجب إضافة Origin الخاص به إلى قائمة المواقع المسموح بها في إعدادات الـProduction.

أما Flutter والتطبيقات الأصلية على الهاتف وLaravel/PHP backend وPython وJava و.NET والطلبات Server-to-Server فلا يطبق عليها المتصفح CORS، لكنها ما زالت تحتاج API Key صالحًا.

**مهم:** لا تضع Production API Key داخل JavaScript عام في المتصفح. إذا كان الموقع عامًا، الأفضل أن يحتفظ Backend الخاص بالموقع بالمفتاح ويقوم هو بالاتصال بالـAPI.

## الخصوصية

صُممت الـAPI لمعالجة مستندات الهوية بشكل مؤقت:

- يتم نسخ الملفات إلى مساحة تخزين مؤقتة خاصة وبأسماء عشوائية.
- عند رفع PDF تتم معالجة الصفحة الأولى فقط حاليًا.
- OCR يعمل محليًا.
- OpenCV وImageMagick يعملان محليًا.
- لا يتم إرجاع نص OCR الخام.
- لا يتم إرجاع محتوى QR الخام.
- التطبيق لا يحتفظ بصور المستندات بصورة دائمة.
- التطبيق لا يحتفظ بالبيانات المستخرجة بصورة دائمة.
- يتم حذف الملفات المؤقتة المتعقبة داخل `finally` قبل إرجاع الاستجابة الناجحة.

يجب عدم تسجيل Raw OCR/TSV أو MRZ كامل أو QR payload أو صور المستندات أو API Keys أو حقول الهوية المستخرجة في Logs.

المستودع والاختبارات الآلية يستخدمان بيانات اصطناعية فقط. **لا تضف أبدًا مستند هوية حقيقي أو API Key أو بيانات شخصية إلى GitHub.**

## الإعداد المحلي

### 1. Laravel

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### 2. متطلبات OCR والصور

macOS باستخدام Homebrew:

```bash
brew install tesseract tesseract-lang poppler imagemagick python
```

Ubuntu/Debian:

```bash
sudo apt-get update
sudo apt-get install -y tesseract-ocr tesseract-ocr-ara poppler-utils imagemagick python3 python3-venv
```

### 3. بيئة OpenCV

```bash
python3 -m venv .venv-vision
.venv-vision/bin/python -m pip install --upgrade pip
.venv-vision/bin/python -m pip install -r requirements-vision.txt
```

### 4. إعداد حماية الـAPI

للتطوير المحلي يمكن ترك الحماية غير مفعلة:

```env
DOCUMENT_API_AUTH_ENABLED=false
```

أما على سيرفر خاص أو Production:

```env
DOCUMENT_API_AUTH_ENABLED=true
DOCUMENT_API_KEY_HEADER=X-API-Key
DOCUMENT_API_DEFAULT_RATE_LIMIT=60
DOCUMENT_API_CLIENTS=client-id:SHA256_API_KEY_HASH:60
DOCUMENT_API_CORS_ORIGINS=https://your-app.example.com
CACHE_STORE=file
```

يتم إنشاء API Key خارج المستودع، ثم حساب SHA-256 له، وتخزين الـhash فقط داخل `DOCUMENT_API_CLIENTS`. أما المفتاح النصي فيتم تسليمه للعميل بشكل خاص ولا يوضع في GitHub.

إعدادات المعالجة الأخرى:

```env
PASSPORT_VISION_ENABLED=true
PASSPORT_VISION_PYTHON_BINARY=/absolute/path/to/project/.venv-vision/bin/python
PASSPORT_VISION_REJECT_LOW_QUALITY=true

CIVIL_REGISTRY_OCR_LANGUAGE=ara+eng
CIVIL_REGISTRY_OCR_PSMS=4,3,11
CIVIL_REGISTRY_OCR_TIMEOUT=30
CIVIL_REGISTRY_VERIFICATION_ENABLED=true
CIVIL_REGISTRY_VERIFICATION_TIMEOUT=15
CIVIL_REGISTRY_VERIFICATION_MAX_DIMENSION=2200
```

### 5. التشغيل

```bash
php artisan serve
```

ثم افتح:

- API: `http://localhost:8000`
- Swagger: `http://localhost:8000/docs`

## نموذج التحقق

تم تقسيم التحقق من مستندات الأحوال المدنية إلى مستويات:

1. **الاستخراج** — OCR والحقول المنظمة.
2. **الإشارات البنيوية** — صيغ الرقم الوطني والتواريخ وصيغة QR المعروفة.
3. **الإشارات البصرية** — مكان QR ومرشحات الأختام.
4. **إشارات القالب** — النصوص والعلامات المتوقعة في النماذج المدعومة.
5. **التحقق من جهة الإصدار** — **غير منفذ حاليًا** إلى أن تتوفر جهة رسمية موثوقة أو توقيع رقمي قابل للتحقق.

لذلك لا يصف النظام المستند بأنه أصلي أو مزور بشكل قطعي.

## ملاحظات أمنية

- يتم فك QR محليًا ولا يتم فتح روابطه تلقائيًا.
- QR غير المعروف لا يتم إرجاع محتواه الخام؛ يمكن تمثيله ببصمة SHA-256 فقط.
- الأوامر الخارجية يتم استدعاؤها بدون Shell interpolation غير آمن.
- اكتشاف الختم إشارة بصرية ولا يثبت من قام بوضعه.
- القوالب مبنية على عينات مدعومة وليست مخططات حكومية رسمية.
- يتم مقارنة API Keys عبر SHA-256 و`hash_equals`.
- Rate Limiting مستقل لكل عميل مصادق عليه.
- إذا تم كشف Production API Key يجب تدويره واستبداله.

راجع أيضًا [SECURITY.md](SECURITY.md).

## خارطة الطريق

### v0.4.x

- تحسين إعادة بناء صفوف الجداول العربية.
- إضافة إخفاء الحقول منخفضة الثقة.
- إضافة نماذج أحوال مدنية أخرى باستخدام عينات آمنة أو منقحة فقط.
- تحسين قراءة QR في الصور منخفضة الدقة أو المائلة.
- دراسة الأختام غير الزرقاء/أحادية اللون بدون زيادة False Positives.
- إضافة تحقق رسمي اختياري إذا توفرت جهة أو آلية رسمية موثقة.
- أدوات أسهل لإنشاء API Keys وتدويرها وتعطيلها لكل عميل.

### لاحقًا

- تطبيق Flutter بكاميرا مباشرة وإرشادات تصوير.
- OCR/Vision اختياري على الجهاز.
- أبحاث ePassport/NFC عندما يكون ذلك مناسبًا تقنيًا وقانونيًا.

## المطورة

**Aya Aljaidi — آية الجعيدي**  
GitHub: https://github.com/ayagaidi  
LinkedIn: https://www.linkedin.com/in/aya-aljaidi-a9544b208/

## الترخيص

MIT © 2026 Aya Aljaidi

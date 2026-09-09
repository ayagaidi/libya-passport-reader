# قارئ المستندات الليبية 🇱🇾

[English](README.md) | **العربية**

**واجهة Laravel تركز على الخصوصية لقراءة جوازات السفر الليبية ومستندات مصلحة الأحوال المدنية المدعومة من الصور وملفات PDF الممسوحة ضوئيًا.**

Libya Document Reader هو مشروع مفتوح المصدر ومستقل للمطورين. يجمع بين OCR محلي، والرؤية الحاسوبية، والتحقق من MRZ وفق ICAO TD3، والاستخراج المنظم بالعربية والإنجليزية، وإشارات تحقق محافظة للمستندات دون الادعاء بإثبات أصالة المستند رسميًا.

> هذا المشروع ليس خدمة حكومية ليبية رسمية، وليس معتمدًا أو مصادقًا عليه أو تابعًا لجهات الجوازات أو مصلحة الأحوال المدنية الليبية. نجاح OCR أو MRZ أو وجود QR أو تطابق القالب أو اكتشاف الأختام لا يُعد إثباتًا على أن المستند أصلي.

## v0.4.1 — إشارات التحقق لمستندات الأحوال المدنية

يضيف الإصدار v0.4.1 طبقة تحقق استرشادية لقارئ مستندات الأحوال المدنية، وتشمل:

- اكتشاف QR باستخدام OpenCV.
- فك QR محليًا عندما تسمح جودة الصورة بذلك.
- التحقق البنيوي من حمولة QR المعروفة بصيغة `CheckNumber:<UUID>`.
- فحص مدى توافق مكان QR مع النماذج المدعومة حاليًا.
- اكتشاف مرشحات الأختام/البصمات ذات الحبر الأزرق.
- إرجاع مواقع نسبية للأختام بدون إرجاع قصاصات من الصورة.
- قياس تغطية العلامات النصية المتوقعة في القالب.
- فحص اتساق صيغ الأرقام الوطنية والتواريخ.
- حساب درجة مجمعة باسم `signal_score`.
- حالات التحقق الممكنة: `signals_consistent` أو `partial_signals` أو `review_recommended` أو `insufficient_evidence`.
- عدم استدعاء أي رابط أو قاعدة بيانات لجهة الإصدار تلقائيًا.
- عدم إرجاع المحتوى الخام للـQR.
- بقاء `authenticity_verified=false` دائمًا إلى أن تتوفر مستقبلاً جهة إصدار موثوقة أو آلية توقيع رقمي يمكن التحقق منها.

مستندات الأحوال المدنية المدعومة حاليًا:

- شهادة الإقامة — **Residence Certificate**.
- شهادة بالوضع العائلي — **Family Status Certificate**.

فحوصات القالب مبنية على النماذج المدعومة التي تمت معايرتها للمشروع، ولا يتم تقديمها على أنها اعتماد رسمي لنماذج حكومية.

## v0.4 — قارئ مستندات الأحوال المدنية

يستخدم قارئ الأحوال المدنية OCR مخصصًا بالعربية والإنجليزية عبر Tesseract، ويجرب عدة أوضاع لتقسيم الصفحة (`PSM 4` و`3` و`11`). يتم اختيار أقوى نتيجة OCR بصورة محافظة، ثم دمج السطور المفيدة لعملية الاستخراج.

تتم قراءة OCR والتحقق على **الصفحة الكاملة بعد تجهيزها** حتى لا تضيع عناوين الصفحة، أو QR، أو رؤوس الجداول بسبب قصاصات مخصصة للجوازات. وتظل نتيجة Vision متاحة كبيانات تشخيصية، لكنها لا تُستخدم لقص صفحة الأحوال المدنية قبل OCR.

يمكن لاستخراج شهادة الوضع العائلي إرجاع صفوف أفراد الأسرة المكتشفة، مثل:

- الرقم الوطني.
- الاسم.
- صلة القرابة.
- تاريخ الميلاد.

ويمكن لاستخراج شهادة الإقامة إرجاع حقول مثل:

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

لا يتم إرجاع أي حقل إلا إذا اكتشفه OCR/Extractor محليًا؛ ولا يتم اختراع البيانات المفقودة.

## v0.3.3 — ماسح الجوازات المعتمد على الرؤية الحاسوبية

يتضمن ماسح الجوازات:

- اكتشاف حدود المستند باستخدام OpenCV والـcontours.
- تصحيح منظور رباعي الزوايا.
- ضبط اتجاه صفحة الجواز أفقيًا عند الحاجة.
- قياس جودة الضبابية والوهج وفرط التعريض.
- تحسين الصورة باستخدام ImageMagick وتوليد عدة مناطق MRZ مرشحة.
- Adaptive local threshold مخصص للـMRZ.
- ترتيب مرشحات TD3 اعتمادًا على بنية ICAO وcheck digits.
- OCR للمنطقة المرئية بالعربية والإنجليزية.
- مقارنة محافظة بين البيانات المرئية وMRZ.

## مسارات المعالجة

### جواز السفر

`upload/PDF → document detection → perspective correction → quality gate → smart preprocessing → adaptive MRZ OCR → ICAO validation → Arabic/English visual OCR → comparison`

### مستندات الأحوال المدنية

`upload/PDF → full-page preparation → multi-layout Arabic/English OCR → document classification → structured extraction → QR/seal detection → template/field consistency → verification signals`

## API

### قراءة جواز سفر

`POST /api/v1/passport/scan`

```bash
curl -X POST http://localhost:8000/api/v1/passport/scan \
  -H 'Accept: application/json' \
  -F 'passport=@passport.jpg'
```

### قراءة مستند أحوال مدنية

`POST /api/v1/civil-registry/scan`

```bash
curl -X POST http://localhost:8000/api/v1/civil-registry/scan \
  -H 'Accept: application/json' \
  -F 'document=@civil-registry.pdf'
```

قد تتضمن استجابة ناجحة لمستند أحوال مدنية قسم تحقق مثل:

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

```json
{
  "line1": "P<LBYALGAIDI<<AYA<<<<<<<<<<<<<<<<<<<<<<<<<<<",
  "line2": "1234567897LBY9501016F3001019<<<<<<<<<<<<<<02"
}
```

بيانات MRZ في المثال وهمية/اصطناعية للاختبار.

### Endpoints أخرى

- `POST /api/v1/passport/mrz/validate`
- `GET /api/v1/meta`
- `GET /health`
- `GET /openapi.yaml`
- Swagger UI: `/docs`

## الخصوصية

صُممت الـAPI لمعالجة مستندات الهوية بشكل مؤقت فقط:

- يتم نسخ الملفات المرفوعة إلى مساحة تخزين مؤقتة وخاصة بأسماء عشوائية.
- عند رفع PDF تتم معالجة الصفحة الأولى فقط.
- OCR يعمل محليًا.
- OpenCV وImageMagick يعملان محليًا.
- لا يتم إرجاع نص OCR الخام.
- لا يتم إرجاع محتوى QR الخام.
- التطبيق لا يحتفظ بصور المستندات بشكل دائم.
- التطبيق لا يحتفظ بالبيانات المستخرجة من المستندات بشكل دائم.
- يتم حذف جميع الملفات المؤقتة المتعقبة داخل `finally` قبل إرجاع الاستجابة الناجحة.

يجب عدم تسجيل raw OCR/TSV أو أسطر MRZ الكاملة أو محتويات QR أو صور الجوازات أو صور الأحوال المدنية أو حقول الهوية المستخرجة في السجلات.

المستودع والاختبارات الآلية يستخدمان بيانات اصطناعية فقط. **لا تقم أبدًا بإضافة مستندات هوية حقيقية أو بيانات شخصية إلى المستودع.**

## الإعداد المحلي

### 1. Laravel

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### 2. متطلبات OCR والصور

على macOS باستخدام Homebrew:

```bash
brew install tesseract tesseract-lang poppler imagemagick python
```

على Ubuntu/Debian:

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

ثم إعداد المتغيرات:

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

### 4. التشغيل

```bash
php artisan serve
```

ثم افتح:

- API: `http://localhost:8000`
- Swagger: `http://localhost:8000/docs`

## نموذج التحقق

تم تقسيم التحقق من مستندات الأحوال المدنية عمدًا إلى مستويات:

1. **الاستخراج** — OCR والحقول المنظمة.
2. **الإشارات البنيوية** — صيغ الرقم الوطني والتواريخ وصيغة QR المعروفة.
3. **الإشارات البصرية** — مكان QR ومرشحات الأختام/البصمات.
4. **إشارات القالب** — العلامات النصية المتوقعة للنماذج المدعومة.
5. **التحقق من جهة الإصدار** — **غير مطبق حاليًا** إلى أن تتوفر جهة رسمية موثوقة أو آلية توقيع رقمي يمكن التحقق منها.

المستوى الخامس فقط يمكن أن يرفع النظام بصورة جوهرية نحو تحقق رسمي من الأصالة. لذلك لا تقوم الـAPI بوصف المستند بأنه أصلي أو مزور.

## ملاحظات أمنية

- يتم فك QR محليًا ولا يتم فتح الروابط الموجودة بداخله تلقائيًا.
- إذا كان محتوى QR غير معروف، يتم تمثيله فقط ببصمة SHA-256 ولا يتم إرجاع النص الخام.
- يتم تشغيل الأوامر الخارجية باستخدام argument arrays بدل shell interpolation.
- كشف الأختام يعتمد على خصائص الصورة فقط ولا يمكنه إثبات من قام بوضع الختم.
- ملفات تعريف القوالب مبنية على معايرة عينات مدعومة، وليست مخططات رسمية للحكومة.

راجع [SECURITY.md](SECURITY.md).

## خارطة الطريق

### v0.4.x

- تحسين إعادة بناء صفوف الجداول العربية.
- إضافة إخفاء للحقول منخفضة الثقة وفق threshold واضح.
- دعم نماذج إضافية من الأحوال المدنية فقط باستخدام عينات آمنة اصطناعية أو منزوعة البيانات الحساسة.
- تحسين استرجاع QR من الصور منخفضة الدقة أو المائلة.
- دراسة كشف الأختام غير الزرقاء/أحادية اللون بدون زيادة النتائج الإيجابية الكاذبة.
- إضافة تحقق اختياري من جهة إصدار موثوقة إذا توفر تكامل رسمي وموثق.

### لاحقًا

- ماسح Flutter مباشر بالكاميرا مع إرشادات framing.
- OCR/Vision اختياري على الجهاز.
- بحث ePassport/NFC فقط عندما يكون مناسبًا تقنيًا وقانونيًا.

## الترخيص

MIT © 2026 Aya Aljaidi

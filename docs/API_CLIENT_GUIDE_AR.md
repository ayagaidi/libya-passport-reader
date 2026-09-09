# دليل استخدام Libya Document Reader API للعميل

هذا الملف مخصص لتسليمه إلى أي مبرمج أو شركة ستربط نظامها مع Libya Document Reader API.

> **مهم:** لا يحتوي هذا الدليل على API Key حقيقي. يتم إرسال المفتاح الحقيقي للعميل بشكل منفصل وآمن، ولا يجب إضافته إلى GitHub أو أي مستودع كود عام.

## 1. روابط الخدمة

- Base URL: `https://passport-api-production-6c63.up.railway.app`
- Swagger UI: `https://passport-api-production-6c63.up.railway.app/docs`
- OpenAPI: `https://passport-api-production-6c63.up.railway.app/openapi.yaml`
- Health Check: `https://passport-api-production-6c63.up.railway.app/health`
- API Version: `0.4.2-dev`

## 2. المصادقة API Key

كل endpoints تحت `/api/v1/*` تحتاج Header باسم:

```http
X-API-Key: YOUR_API_KEY
```

استبدل `YOUR_API_KEY` بالمفتاح الذي تم تسليمه لك بصورة خاصة.

لا ترسل المفتاح داخل Query String، ولا تضعه داخل GitHub أو Frontend JavaScript عام.

## 3. الاستخدام من Swagger

1. افتح `https://passport-api-production-6c63.up.railway.app/docs`.
2. اضغط زر **Authorize** أعلى Swagger.
3. ألصق API Key فقط.
4. اضغط **Authorize**.
5. اختر الـendpoint المطلوب.
6. اضغط **Try it out**.
7. ارفع الملف أو أدخل البيانات.
8. اضغط **Execute**.

بعد Authorize سيقوم Swagger بإرسال `X-API-Key` تلقائيًا.

## 4. قراءة جواز سفر

Endpoint:

```http
POST /api/v1/passport/scan
```

نوع الطلب: `multipart/form-data`

اسم الحقل: `passport`

مثال cURL:

```bash
curl -X POST \
  'https://passport-api-production-6c63.up.railway.app/api/v1/passport/scan' \
  -H 'Accept: application/json' \
  -H 'X-API-Key: YOUR_API_KEY' \
  -F 'passport=@passport.jpg'
```

الملفات المدعومة: JPEG وPNG وWebP وPDF حسب التحقق الحالي في الخدمة.

## 5. قراءة مستند أحوال مدنية

Endpoint:

```http
POST /api/v1/civil-registry/scan
```

نوع الطلب: `multipart/form-data`

اسم الحقل: `document`

مثال cURL:

```bash
curl -X POST \
  'https://passport-api-production-6c63.up.railway.app/api/v1/civil-registry/scan' \
  -H 'Accept: application/json' \
  -H 'X-API-Key: YOUR_API_KEY' \
  -F 'document=@civil-registry.pdf'
```

المستندات المدعومة حاليًا تشمل:

- شهادة الإقامة.
- شهادة بالوضع العائلي.

الاستجابة قد تحتوي على الحقول المستخرجة وإشارات QR والختم والقالب و`signal_score`، لكن `authenticity_verified` لا يعني تحققًا رسميًا من جهة الإصدار ويظل `false` بدون تكامل رسمي موثوق.

## 6. تحليل MRZ كنص

Endpoint:

```http
POST /api/v1/passport/mrz/parse
```

مثال:

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

المثال أعلاه اصطناعي ولا يحتوي على بيانات جواز حقيقية.

## 7. Endpoints أخرى

- `POST /api/v1/passport/mrz/validate`
- `GET /api/v1/meta`
- `GET /health` — لا يحتاج API Key.
- `GET /openapi.yaml` — لا يحتاج API Key.
- `/docs` — Swagger عام، لكن الـAPI operations تحتاج Authorize.

## 8. Rate Limit

الحد الافتراضي الحالي للعميل المنشور هو:

```text
60 requests / minute
```

وقد يتم إعطاء كل عميل حدًا مختلفًا.

Headers المهمة في الاستجابة:

```http
X-RateLimit-Limit
X-RateLimit-Remaining
Retry-After
```

عند تجاوز الحد ترجع الخدمة:

```http
429 Too Many Requests
```

ومثال الكود:

```json
{
  "code": "api_rate_limit_exceeded"
}
```

## 9. حدود حجم الملفات

الإعداد الحالي للنسخة المنشورة:

- Laravel file limit: حوالي 30 MB.
- PHP upload limit: 32M.
- PHP POST limit: 40M.

الملف الكبير جدًا قد يرجع:

```http
413 Content Too Large
```

يفضل ضغط الصور الكبيرة مع الحفاظ على وضوح النص وMRZ قبل الرفع.

## 10. الأخطاء المتوقعة

### 401 — API Key

بدون مفتاح:

```json
{
  "code": "missing_api_key"
}
```

مفتاح غير صحيح:

```json
{
  "code": "invalid_api_key"
}
```

### 413 — حجم الملف

الطلب تجاوز حدود الرفع.

### 422 — ملف أو قراءة غير صالحة

قد يظهر مثلًا:

```json
{
  "code": "mrz_not_detected"
}
```

أو:

```json
{
  "code": "low_image_quality"
}
```

أو رفض مستند أحوال مدنية لم يتم اكتشافه بثقة.

### 429 — Rate Limit

العميل تجاوز عدد الطلبات المسموح بها.

### 503 — خدمة معالجة غير متاحة

قد يظهر إذا تعطل أحد المتطلبات المحلية اللازمة للـOCR أو PDF processing.

## 11. CORS

إذا كان الربط من Web App يعمل داخل المتصفح، يجب إعطاء مالكة الـAPI الـOrigin الخاص بالموقع، مثل:

```text
https://app.example.com
```

حتى تتم إضافته إلى CORS allowlist.

Flutter/native mobile وServer-to-Server وLaravel/PHP backend وPython وJava و.NET لا يطبق عليها المتصفح CORS، لكنها تحتاج API Key.

**لا تضع Production API Key داخل JavaScript عام في المتصفح.** استخدم Backend موثوقًا يحتفظ بالمفتاح ويرسل الطلبات إلى الـAPI.

## 12. الخصوصية

- الملفات تعالج بصورة مؤقتة.
- التطبيق لا يحتفظ بصور المستندات بصورة دائمة.
- البيانات المستخرجة لا يتم حفظها بواسطة التطبيق.
- Raw OCR لا يتم إرجاعه.
- Raw QR payload لا يتم إرجاعه.
- الملفات المؤقتة يتم حذفها بعد المعالجة وفق مسار التطبيق.

على العميل أيضًا عدم تسجيل صور الهوية أو MRZ كامل أو API Key أو البيانات الحساسة في Logs غير محمية.

## 13. أين يمكن استخدام الـAPI؟

يمكن استخدامه من أي بيئة تستطيع إرسال HTTP requests، مثل:

- Flutter / Dart.
- Laravel / PHP.
- JavaScript / Node.js.
- Python.
- C# / .NET.
- Java / Kotlin.
- Swift.
- Desktop applications.
- أنظمة الشركات الداخلية.

## 14. Checklist قبل الربط

- استلم API Key بصورة خاصة.
- اختبر `/api/v1/meta` بالمفتاح.
- اختبر Passport Scan أو Civil Registry Scan من Swagger.
- تأكد من التعامل مع `401`, `413`, `422`, `429`, `503`.
- لا تخزن API Key في GitHub.
- إذا كان التطبيق Web، أرسل الـOrigin لإضافته إلى CORS.
- لا تعرض بيانات الهوية الحساسة في Logs.
- راقب Rate Limit headers.

## 15. المطورة والتواصل التقني

**Aya Aljaidi**

- GitHub: https://github.com/ayagaidi
- LinkedIn: https://www.linkedin.com/in/aya-aljaidi-a9544b208/

المشروع مفتوح المصدر ومستقل، ولا يمثل خدمة حكومية رسمية. إشارات QR والختم والقالب وMRZ تساعد في القراءة والتحقق البنيوي والبصري، لكنها لا تثبت أصالة المستند رسميًا بدون مصدر إصدار موثوق أو توقيع رقمي قابل للتحقق.

# Libya Document Reader — Public Demo Access 🇱🇾

This repository exposes a **public demo API key** so developers can try the hosted API without requesting a private production credential.

## Public demo key

```text
ldr_demo_public_2026_v1
```

Swagger:

```text
https://passport-api-production-6c63.up.railway.app/docs
```

The hosted Swagger UI is automatically authorized with this demo key. You can also click **Authorize** and enter the key manually.

## Limits

The public demo credential is intentionally restricted:

- **10 requests per minute per IP address**
- **120 requests per minute shared global demo cap**
- testing/demo use only
- may be rotated, reduced, or disabled at any time
- never use this key as a production credential

Private clients receive their own API keys and independent rate limits.

## Passport example

```bash
curl -X POST \
  https://passport-api-production-6c63.up.railway.app/api/v1/passport/scan \
  -H "Accept: application/json" \
  -H "X-API-Key: ldr_demo_public_2026_v1" \
  -F "passport=@passport.jpg"
```

## Civil Registry example

```bash
curl -X POST \
  https://passport-api-production-6c63.up.railway.app/api/v1/civil-registry/scan \
  -H "Accept: application/json" \
  -H "X-API-Key: ldr_demo_public_2026_v1" \
  -F "document=@document.pdf"
```

## Important privacy note

Do not use real identity documents unless you are authorized to process them. The application is designed to process uploads transiently and does not persist document images or extracted identity data, but users remain responsible for having a lawful and appropriate basis for any document they submit.

---

# التجربة العامة بالعربي

لتسهيل تجربة المشروع أونلاين، يوجد مفتاح **Public Demo** مخصص للتجارب فقط:

```text
ldr_demo_public_2026_v1
```

رابط Swagger:

```text
https://passport-api-production-6c63.up.railway.app/docs
```

Swagger يضيف المفتاح تلقائيًا عند فتح الصفحة، ويمكن أيضًا الضغط على **Authorize** وإدخاله يدويًا.

حدود مفتاح التجربة:

- 10 طلبات في الدقيقة لكل IP.
- حد إجمالي مشترك للتجربة العامة: 120 طلبًا في الدقيقة.
- مخصص للتجربة فقط وليس للاستخدام Production.
- يمكن تغييره أو إيقافه في أي وقت.
- العملاء الحقيقيون يحصلون على API Key مستقل وRate Limit مستقل.

**مهم:** لا ترفع مستندات هوية حقيقية إلا إذا كان لديك حق وصلاحية لمعالجتها.

# Sarah AI — Real-User Testing Checklist
## چک‌لیست تست کامل سیستم

> پیش از شروع: هر دو پلاگین deploy شده و فعال هستند. وردپرس روی Local by Flywheel اجرا می‌شود.

---

## ✅ پیش‌نیاز‌های اولیه (Setup Check)

- [ ] پلاگین **sarah-ai-server** فعال است
- [ ] پلاگین **sarah-ai-client** فعال است
- [ ] منوی **Sarah AI Server** در سایدبار ادمین وردپرس سرور دیده می‌شود
- [ ] منوی **Sarah AI** در سایدبار ادمین وردپرس کلاینت دیده می‌شود
- [ ] هر دو پلاگین آخرین build را دارند (`npm run build` اجرا شده)

---

## 1 — Server Admin: Platform Settings

**مسیر:** Sarah AI Server → Settings

- [ ] صفحه Settings لود می‌شود
- [ ] فیلد **OpenAI API Key** نشان داده می‌شود (masked: `••••••••xxxx`)
- [ ] کلید جدید وارد کرده و Save می‌کنید — پیام Saved ظاهر می‌شود
- [ ] صفحه reload می‌شود — کلید mask شده نمایش داده می‌شود
- [ ] فیلد **Platform Name** ذخیره می‌شود
- [ ] تاگل **Logging** کار می‌کند

---

## 2 — Server Admin: Sidebar Navigation

- [ ] **Dashboard** لود می‌شود
- [ ] **Tenants** لود می‌شود
- [ ] **Agents** لود می‌شود
- [ ] **Usage** لود می‌شود
- [ ] **Settings** لود می‌شود
- [ ] لینک‌های فعال در sidebar highlight دارند

---

## 3 — Server Admin: Agent Behavior

**مسیر:** Sarah AI Server → Agents

- [ ] لیست عوامل (gpt-4o-mini، gpt-4o، o1) نمایش داده می‌شود
- [ ] روی یک عامل: فیلد **Role** را تغییر دهید (مثلاً "customer support specialist")
- [ ] **Tone** را تغییر دهید (مثلاً Friendly)
- [ ] Save می‌کنید — پیام Saved ظاهر می‌شود
- [ ] فیلد **Custom System Prompt** متن وارد کنید — badge "Custom prompt active" ظاهر می‌شود
- [ ] Custom Prompt را پاک کنید — badge "Composed from role + tone" ظاهر می‌شود

---

## 4 — Provisioning: ایجاد Tenant کامل

**مسیر:** Sarah AI Server → Tenants → + New Tenant

### Step 1 — Tenant Info
- [ ] نام tenant وارد می‌کنید و Create می‌کنید
- [ ] Tenant detail باز می‌شود
- [ ] Status: Active

### Step 2 — Users (اختیاری)
- [ ] می‌توانید user اضافه کنید

### Step 3 — Site
- [ ] نام سایت وارد کرده و Create Site می‌زنید
- [ ] Site در لیست ظاهر می‌شود با Status: Active

### Step 4 — Account Keys
- [ ] Create Account Key می‌زنید
- [ ] کلید ساخته شده و copy می‌کنید

### Step 5 — Site Keys
- [ ] Create Site Key می‌زنید
- [ ] کلید ساخته شده و copy می‌کنید

### Step 6 — Agent Assignment
- [ ] از dropdown عامل انتخاب می‌کنید
- [ ] Assign کلیک می‌کنید — عامل اختصاص یافته نمایش داده می‌شود

### Step 7 — Agent Identity
- [ ] سایت مورد نظر را انتخاب می‌کنید
- [ ] **Agent Display Name** وارد می‌کنید (مثلاً "سارا")
- [ ] **Intro Message** وارد می‌کنید (مثلاً "من دستیار هوشمند این فروشگاه هستم.")
- [ ] **Greeting Message** وارد می‌کنید (مثلاً "سلام! چطور می‌تونم کمکتون کنم؟")
- [ ] Save — پیام Saved ظاهر می‌شود

### Step 8 — Knowledge Resources
- [ ] Add کلیک می‌کنید
- [ ] Title، Type: `text`، Source Content (چند پاراگراف متن) وارد می‌کنید
- [ ] Add می‌کنید — آیتم در لیست ظاهر می‌شود
- [ ] ستون **Processing** مقدار `none` یا `queued` دارد
- [ ] دکمه **⚙ Process** کلیک می‌کنید
- [ ] دکمه به `…` تغییر می‌کند (in-flight)
- [ ] بعد از لحظه‌ای: ستون Processing به `done` (سبز) تغییر می‌کند
  - اگر OpenAI key ست نشده: به `done` می‌رسد اما embeddings ندارد
  - اگر key ست شده: به `done` می‌رسد با embeddings کامل
- [ ] یک منبع با متن اشتباه اضافه کنید — Process بزنید — اگر خالی باشد: ستون `failed` (قرمز) می‌شود

---

## 5 — Client: تنظیمات پلاگین کلاینت

**مسیر:** Admin وردپرس کلاینت → Sarah AI → Settings

- [ ] صفحه Settings لود می‌شود
- [ ] **Server URL** وارد کنید (آدرس REST API سرور)
- [ ] **Account Key** وارد کنید
- [ ] **Site Key** وارد کنید
- [ ] **Greeting Message** وارد کنید (همان چیزی که در step 7 وارد کردید)
- [ ] Save Settings — پیام Saved ظاهر می‌شود
- [ ] صفحه reload کنید — مقادیر حفظ شده‌اند

---

## 6 — Widget: نمایش و Greeting

**مسیر:** صفحه frontend سایت کلاینت

- [ ] دکمه launcher (دایره چت) در گوشه صفحه دیده می‌شود
- [ ] کلیک — پنجره چت باز می‌شود
- [ ] **پیام Greeting** بلافاصله نمایش داده می‌شود (بدون delay — از config است)
- [ ] پنجره را ببندید و دوباره باز کنید — Greeting **تکرار نمی‌شود** (history موجود است)

---

## 7 — Widget: چت واقعی با AI

- [ ] یک سوال تایپ می‌کنید و Enter می‌زنید
- [ ] **Typing indicator** (سه نقطه متحرک) ظاهر می‌شود
- [ ] جواب AI دریافت می‌شود
- [ ] اگر OpenAI key تنظیم نشده: جواب `[TEST MODE]` دریافت می‌کنید
- [ ] اگر key تنظیم شده: جواب واقعی از OpenAI دریافت می‌شود
- [ ] نمی‌توانید در حین دریافت جواب پیام دیگری بفرستید (input disabled)

---

## 8 — Widget: Session Persistence (Task 1)

- [ ] یک پیام می‌فرستید و جواب دریافت می‌کنید
- [ ] DevTools → Application → Local Storage: کلید `sarah_ai_session_{site_key}` وجود دارد
- [ ] مقدار آن یک UUID است
- [ ] صفحه **Refresh** می‌کنید
- [ ] پنجره چت را باز می‌کنید
- [ ] پیام‌های قبلی restore شده‌اند (Task 2)
- [ ] Greeting **دوباره نشان داده نمی‌شود** (history جایگزین greeting است)

---

## 9 — Widget: History Restore (Task 2)

- [ ] چند پیام رد و بدل می‌کنید
- [ ] صفحه کاملاً refresh می‌کنید
- [ ] Widget را باز می‌کنید — **همان مکالمه** نمایش داده می‌شود
- [ ] می‌توانید مکالمه را ادامه دهید (session_uuid یکسان است)
- [ ] از DevTools: localStorage را پاک کنید
- [ ] Refresh کنید — Widget دوباره Greeting نشان می‌دهد (fresh start)

---

## 10 — Widget: Error Recovery (Task 4)

- [ ] **Server URL** را در Settings به آدرس اشتباه تغییر دهید
- [ ] Widget را باز کنید و یک پیام بفرستید
- [ ] پیام خطا ظاهر می‌شود: "Unable to connect. Please try again."
- [ ] دکمه **↺ Try again** زیر پیام خطا دیده می‌شود
- [ ] Input دوباره فعال است (می‌توانید تایپ کنید)
- [ ] دکمه Try again را بزنید — همان پیام دوباره ارسال می‌شود (بدون اینکه دوباره تایپ کنید)
- [ ] Server URL را به حالت صحیح برگردانید

---

## 11 — Widget: Reset Chat (Task 6)

- [ ] چند پیام رد و بدل کرده‌اید
- [ ] دکمه **↺** (New Chat) در header پنجره چت دیده می‌شود
- [ ] کلیک می‌کنید
- [ ] پیام‌ها پاک می‌شوند
- [ ] Greeting دوباره نشان داده می‌شود
- [ ] DevTools → LocalStorage: session_uuid پاک شده است
- [ ] یک پیام جدید می‌فرستید — session_uuid **جدید** در localStorage ذخیره می‌شود

---

## 12 — Widget: Typing Indicator (Task 5)

- [ ] یک پیام می‌فرستید
- [ ] بلافاصله **سه نقطه متحرک** در یک bubble AI ظاهر می‌شود
- [ ] بعد از رسیدن جواب، نقطه‌ها محو می‌شوند و جواب جایگزین می‌شود
- [ ] در حین نمایش indicator، دکمه Send و input هر دو disabled هستند

---

## 13 — RAG: Knowledge در جواب AI

> این تست نیاز به OpenAI key دارد و knowledge باید process شده باشد (ستون Processing = done)

- [ ] یک knowledge resource با محتوای خاص اضافه کرده‌اید (مثلاً: "ساعت کاری فروشگاه ما ۹ تا ۱۸ است.")
- [ ] Process کرده‌اید — ستون `done` است
- [ ] در widget می‌پرسید: "ساعت کاری شما چیه؟"
- [ ] جواب AI حاوی اطلاعات صحیح از knowledge است
- [ ] AI اطلاعاتی که در knowledge نیست را **اختراع نمی‌کند**

---

## 14 — Site Identity در جواب AI

> نیاز به OpenAI key و Agent Display Name تنظیم شده دارد

- [ ] از agent می‌پرسید: "اسمت چیه؟"
- [ ] جواب AI حاوی **Agent Display Name** که تنظیم کرده‌اید است (مثلاً "سارا")
- [ ] Intro Message در رفتار agent منعکس است

---

## 15 — Usage Dashboard

**مسیر:** Sarah AI Server → Usage

- [ ] بعد از ارسال چند پیام واقعی، صفحه Usage را باز کنید
- [ ] کارت‌های **Total Requests**، **Tokens In**، **Tokens Out** اعداد غیر صفر دارند
- [ ] جدول لیست sessions نمایش داده می‌شود
- [ ] فیلتر تاریخ کار می‌کند

---

## 16 — Lead Capture (Task 7)

> تست از طریق DevTools Console

- [ ] قبل از باز کردن widget، در Console:
  ```javascript
  window.SarahAiWidget.connection.lead = { name: "تست", phone: "09123456789" };
  ```
- [ ] پیامی بفرستید
- [ ] در server: Sessions را بررسی کنید — اطلاعات lead ذخیره شده است

---

## 17 — Edge Cases

### Widget بسته و باز کردن متوالی
- [ ] Widget را ۳ بار متوالی باز و بسته کنید — crash نمی‌کند
- [ ] Greeting فقط یک بار نشان داده می‌شود (history پس از اولین پیام)

### مکالمه طولانی
- [ ] ۱۰+ پیام رد و بدل کنید — auto-scroll کار می‌کند
- [ ] Refresh کنید — همه ۱۰+ پیام restore می‌شوند

### Knowledge processing failure
- [ ] یک resource از نوع `link` با URL نامعتبر اضافه کنید
- [ ] Process بزنید — ستون به `failed` تغییر می‌کند
- [ ] سیستم crash نمی‌کند

### بدون اتصال اینترنت (OpenAI)
- [ ] OpenAI key را حذف کنید
- [ ] چت باز کنید — جواب `[TEST MODE]` دریافت می‌کنید
- [ ] Knowledge processing: chunks بدون embedding ذخیره می‌شوند (done، بدون error)

---

## نتیجه کلی

| بخش | وضعیت |
|-----|-------|
| Server admin UI (navigation, settings, agents, usage) | ⬜ |
| Provisioning flow (tenant → site → keys → agent) | ⬜ |
| Site identity (agent name, intro, greeting) | ⬜ |
| Knowledge processing (add, process, chunks) | ⬜ |
| Widget basic chat | ⬜ |
| Session persistence (localStorage) | ⬜ |
| History restore | ⬜ |
| Error recovery + retry | ⬜ |
| Reset chat | ⬜ |
| RAG knowledge injection | ⬜ |
| Usage tracking | ⬜ |

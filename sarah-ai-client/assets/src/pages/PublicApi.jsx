import React, { useState } from 'react';

const FUNCTIONS = [
  {
    name: 'sarah_chat_exists',
    signature: 'sarah_chat_exists(): bool',
    description: 'بررسی می‌کند که آیا Sarah Chat نصب و Public API آن بارگذاری شده است. همیشه قبل از هر تابع دیگری این را فراخوانی کنید.',
    params: [],
    returns: { type: 'bool', desc: 'true اگر پلاگین نصب و API موجود باشد.' },
    example: `if ( ! sarah_chat_exists() ) {
    return; // پلاگین نصب نیست
}`,
  },
  {
    name: 'sarah_chat_is_ready',
    signature: 'sarah_chat_is_ready(): bool',
    description: 'بررسی می‌کند که آیا پلاگین کاملاً پیکربندی شده و آماده استفاده است (server_url، account_key، site_key و platform_key همه ست شده باشند).',
    params: [],
    returns: { type: 'bool', desc: 'true اگر همه اعتبارنامه‌های اتصال موجود باشند.' },
    example: `if ( sarah_chat_is_ready() ) {
    // پلاگین آماده است
}`,
  },
  {
    name: 'sarah_chat_setup',
    signature: 'sarah_chat_setup( array $payload ): array',
    description: 'فرایند Quick Setup را به صورت برنامه‌نویسی اجرا می‌کند. به سرور Sarah AI متصل می‌شود، tenant و site ایجاد می‌کند و اعتبارنامه‌ها را ذخیره می‌کند.',
    params: [
      { name: '$payload[\'server_url\']',     type: 'string', req: true,  desc: 'آدرس پایه WordPress REST API سرور (بدون / انتها).' },
      { name: '$payload[\'platform_key\']',   type: 'string', req: true,  desc: 'کلید احراز هویت پلتفرم.' },
      { name: '$payload[\'site_name\']',      type: 'string', req: false, desc: 'نام سایت. پیش‌فرض: نام سایت WordPress.' },
      { name: '$payload[\'site_url\']',       type: 'string', req: false, desc: 'آدرس سایت. پیش‌فرض: home_url().' },
      { name: '$payload[\'whmcs_key\']',      type: 'string', req: true, desc: 'کلید لایسنس WHMCS.' },
      { name: '$payload[\'openai_api_key\']', type: 'string', req: true, desc: 'کلید OpenAI اختصاصی سایت.' },
    ],
    returns: {
      type: 'array',
      desc: '[ success: bool, ready: bool, error: string|null ]',
    },
    example: `$result = sarah_chat_setup([
    'server_url'     => 'https://server.example.com/wp-json',
    'platform_key'   => 'your-platform-key',
    'whmcs_key'      => 'WHMCS-XXXX-XXXX',   // اجباری
    'openai_api_key' => 'sk-...',             // اجباری
]);

if ( $result['success'] && $result['ready'] ) {
    // راه‌اندازی موفق
} else {
    error_log( $result['error'] );
}`,
  },
  {
    name: 'sarah_chat_get_sessions',
    signature: 'sarah_chat_get_sessions( array $args = [] ): array',
    description: 'فهرست آخرین مکالمات (session) این سایت را از سرور دریافت می‌کند.',
    params: [
      { name: '$args[\'limit\']', type: 'int', req: false, desc: 'حداکثر تعداد نتایج (پیش‌فرض: ۲۰، حداکثر: ۱۰۰).' },
    ],
    returns: {
      type: 'array',
      desc: '[ success: bool, data: array, error: string|null ] — هر آیتم data شامل: uuid، status، created_at و …',
    },
    example: `$result = sarah_chat_get_sessions([ 'limit' => 10 ]);

if ( $result['success'] ) {
    foreach ( $result['data'] as $session ) {
        echo $session['uuid'] . ' — ' . $session['status'];
    }
}`,
  },
  {
    name: 'sarah_chat_get_session_history',
    signature: 'sarah_chat_get_session_history( string $session_uuid ): array',
    description: 'اطلاعات کامل یک مکالمه (session) به همراه تاریخچه پیام‌ها را برمی‌گرداند.',
    params: [
      { name: '$session_uuid', type: 'string', req: true, desc: 'UUID مکالمه مورد نظر.' },
    ],
    returns: {
      type: 'array',
      desc: '[ success: bool, session: array|null, messages: array, error: string|null ] — هر پیام شامل: role، content، created_at',
    },
    example: `$result = sarah_chat_get_session_history( '550e8400-e29b-41d4-a716-446655440000' );

if ( $result['success'] ) {
    foreach ( $result['messages'] as $msg ) {
        echo $msg['role'] . ': ' . $msg['content'];
    }
} else {
    echo $result['error']; // 'Session not found'
}`,
  },
  {
    name: 'sarah_chat_set_config',
    signature: 'sarah_chat_set_config( array $values ): array',
    description: 'یک یا چند مقدار پیکربندی پلاگین را تنظیم می‌کند.',
    params: [
      { name: '$values[\'widget_enabled\']',  type: 'string', req: false, desc: '"1" یا "0" — نمایش ویجت در فرانت‌اند.' },
      { name: '$values[\'greeting_message\']',type: 'string', req: false, desc: 'پیام خوشامدگویی ویجت.' },
      { name: '$values[\'server_url\']',      type: 'string', req: false, desc: 'آدرس سرور Sarah AI (با namespace).' },
      { name: '$values[\'account_key\']',     type: 'string', req: false, desc: 'کلید اکانت.' },
      { name: '$values[\'site_key\']',        type: 'string', req: false, desc: 'کلید سایت.' },
      { name: '$values[\'platform_key\']',    type: 'string', req: false, desc: 'کلید پلتفرم.' },
    ],
    returns: {
      type: 'array',
      desc: '[ success: bool, saved: string[], errors: string[], error: string|null ]',
    },
    example: `$result = sarah_chat_set_config([
    'widget_enabled'   => '1',
    'greeting_message' => 'سلام! چطور می‌تونم کمکتون کنم؟',
]);

if ( $result['success'] ) {
    // $result['saved'] = ['widget_enabled', 'greeting_message']
}`,
  },
];

function FunctionCard({ fn }) {
  const [open, setOpen] = useState(false);

  return (
    <div className="card border-0 shadow-sm mb-3">
      <div
        className="card-header bg-white border-bottom d-flex align-items-center justify-content-between"
        style={{ cursor: 'pointer' }}
        onClick={() => setOpen(o => !o)}
      >
        <div className="d-flex align-items-center gap-2">
          <span className="badge bg-primary-subtle text-primary border border-primary-subtle" style={{ fontFamily: 'monospace', fontSize: '0.7rem' }}>PHP</span>
          <code style={{ fontSize: '0.88rem', color: '#1a3460', fontWeight: 600 }}>{fn.signature}</code>
        </div>
        <span className="text-muted" style={{ fontSize: '0.8rem' }}>{open ? '▲' : '▼'}</span>
      </div>

      {open && (
        <div className="card-body p-4">
          <p className="text-secondary small mb-4" style={{ lineHeight: 1.7 }}>{fn.description}</p>

          {fn.params.length > 0 && (
            <div className="mb-4">
              <div className="fw-semibold small text-dark mb-2">پارامترها</div>
              <table className="table table-sm table-bordered" style={{ fontSize: '0.8rem' }}>
                <thead className="table-light">
                  <tr>
                    <th>نام</th>
                    <th>نوع</th>
                    <th>اجباری</th>
                    <th>توضیح</th>
                  </tr>
                </thead>
                <tbody>
                  {fn.params.map(p => (
                    <tr key={p.name}>
                      <td><code>{p.name}</code></td>
                      <td><span className="badge bg-secondary-subtle text-secondary">{p.type}</span></td>
                      <td>{p.req ? <span className="text-danger">بله</span> : <span className="text-muted">خیر</span>}</td>
                      <td className="text-secondary">{p.desc}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <div className="mb-4">
            <div className="fw-semibold small text-dark mb-2">خروجی</div>
            <div className="d-flex align-items-start gap-2">
              <code className="badge bg-success-subtle text-success border border-success-subtle" style={{ fontSize: '0.75rem' }}>{fn.returns.type}</code>
              <span className="text-secondary small">{fn.returns.desc}</span>
            </div>
          </div>

          <div>
            <div className="fw-semibold small text-dark mb-2">مثال</div>
            <pre
              style={{
                background: '#0f172a',
                color: '#e2e8f0',
                borderRadius: 8,
                padding: '1rem 1.25rem',
                fontSize: '0.78rem',
                lineHeight: 1.7,
                overflowX: 'auto',
                margin: 0,
              }}
            >{fn.example}</pre>
          </div>
        </div>
      )}
    </div>
  );
}

export default function PublicApi() {
  return (
    <>
      <div className="mb-4">
        <h1 className="h5 fw-semibold text-dark mb-1">PHP Public API</h1>
        <p className="text-muted small mb-0">
          توابع global قابل فراخوانی از هر پلاگین WordPress — بدون نیاز به دسترسی مستقیم به کلاس‌های داخلی.
        </p>
      </div>

      {/* Adapter notice */}
      <div className="alert alert-info border-0 small mb-4 py-2 px-3" style={{ background: '#eff6ff', color: '#1e40af' }}>
        <strong>Integration Adapter:</strong> برای استفاده از این توابع در پلاگین دیگری، فایل{' '}
        <code>integration/SarahChatAdapter.php</code> را کپی کنید. تمام فراخوانی‌ها را wraps می‌کند و اگر پلاگین نصب نباشد crash نمی‌دهد.
      </div>

      {/* Quick example */}
      <div className="card border-0 shadow-sm mb-4">
        <div className="card-header bg-white border-bottom">
          <span className="fw-semibold small text-dark">نمونه استفاده — Parent Product</span>
        </div>
        <div className="card-body p-0">
          <pre
            style={{
              background: '#0f172a',
              color: '#e2e8f0',
              borderRadius: '0 0 8px 8px',
              padding: '1rem 1.25rem',
              fontSize: '0.78rem',
              lineHeight: 1.7,
              overflowX: 'auto',
              margin: 0,
            }}
          >{`// ۱. بررسی وجود پلاگین
if ( ! sarah_chat_exists() ) {
    return;
}

// ۲. راه‌اندازی (یک بار، مثلاً در activation hook)
if ( ! sarah_chat_is_ready() ) {
    sarah_chat_setup([
        'server_url'     => 'https://server.example.com/wp-json',
        'platform_key'   => 'your-key',
        'whmcs_key'      => 'WHMCS-XXXX-XXXX',
        'openai_api_key' => 'sk-...',
    ]);
}

// ۳. دریافت مکالمات
$sessions = sarah_chat_get_sessions([ 'limit' => 5 ]);

// ۴. دریافت تاریخچه
$history = sarah_chat_get_session_history( $sessions['data'][0]['uuid'] ?? '' );`}</pre>
        </div>
      </div>

      {/* Function reference */}
      <div className="fw-semibold small text-dark mb-3">مرجع توابع <span className="text-muted fw-normal">(کلیک برای باز/بسته شدن)</span></div>
      {FUNCTIONS.map(fn => <FunctionCard key={fn.name} fn={fn} />)}
    </>
  );
}

// برنامج ربط واتساب لنظام مكانة
// ─────────────────────────────────────────────────────────────
// بيشتغل على كمبيوتر بالمكتب وبيمسك جلسة الواتساب (متل WhatsApp Web).
// ما بيستقبل أي اتصال من برا: هو اللي بيسأل السيرفر كل كم ثانية:
//   • heartbeat: حالة الاتصال + كود QR (بيظهر بصفحة الرسائل الجماعية بالنظام)
//   • next:      في رسالة لازم تنبعت هلق؟ (السيرفر بيقرر الفواصل والحد اليومي وساعات العمل)
//   • report:    انبعتت ولا لأ
import makeWASocket, { useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers } from '@whiskeysockets/baileys';
import pino from 'pino';
import qrcode from 'qrcode-terminal';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const DIR       = path.dirname(fileURLToPath(import.meta.url));
const AUTH_DIR  = path.join(DIR, 'auth');
const VERSION   = '1.0.0';
const HEARTBEAT_MS = 10_000;
const POLL_MS      = 8_000;

const log = (...a) => console.log(new Date().toLocaleTimeString('en-GB'), '|', ...a);

// ── الإعدادات (بتجي جاهزة بالملف المضغوط من النظام) ──
let config;
try {
  config = JSON.parse(fs.readFileSync(path.join(DIR, 'config.json'), 'utf8'));
  if (!config.server || !config.token) throw new Error('ناقص');
} catch {
  log('❌ ملف config.json مش موجود أو ناقص — نزّل البرنامج من جديد من صفحة "رسائل واتساب" بالنظام.');
  process.exit(1);
}

async function api(method, route, body) {
  const res = await fetch(`${config.server}/api/wa-gateway/${route}`, {
    method,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Gateway-Token': config.token },
    body: body ? JSON.stringify(body) : undefined,
    signal: AbortSignal.timeout(20_000),
  });
  if (res.status === 401) throw Object.assign(new Error('مفتاح الجهاز غير صحيح — نزّل البرنامج من جديد من النظام'), { fatal: true });
  if (!res.ok) throw new Error(`السيرفر رجّع ${res.status}`);
  return res.json();
}

// ── حالة الاتصال ──
let sock = null;
let state = { status: 'connecting', qr: null, phone: null, name: null };

async function heartbeat() {
  try {
    const r = await api('POST', 'heartbeat', { ...state, version: VERSION });
    if (r.command === 'logout' && sock) {
      log('🔌 طلب فصل الرقم من النظام...');
      await sock.logout().catch(() => {});
    }
  } catch (e) {
    log('⚠️ ما قدرت أوصل للسيرفر:', e.message);
    if (e.fatal) process.exit(1);
  }
}

async function connect() {
  const { state: auth, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
  const { version } = await fetchLatestBaileysVersion().catch(() => ({ version: undefined }));

  sock = makeWASocket({
    auth,
    version,
    logger: pino({ level: 'silent' }),
    browser: Browsers.windows('Makana'),
    markOnlineOnConnect: false,
    syncFullHistory: false,
  });

  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('connection.update', async ({ connection, lastDisconnect, qr }) => {
    if (qr) {
      state = { status: 'qr', qr, phone: null, name: null };
      log('📱 امسح الكود من صفحة "رسائل واتساب" بالنظام، أو من هون:');
      qrcode.generate(qr, { small: true });
      heartbeat();
    }

    if (connection === 'open') {
      const id = sock.user?.id || '';
      state = { status: 'connected', qr: null, phone: id.split(':')[0].split('@')[0], name: sock.user?.name || null };
      log(`✅ متصل بالرقم ${state.phone}`);
      heartbeat();
    }

    if (connection === 'close') {
      const code = lastDisconnect?.error?.output?.statusCode;
      if (code === DisconnectReason.loggedOut) {
        log('🔌 انفصل الرقم — بنطلع كود QR جديد...');
        fs.rmSync(AUTH_DIR, { recursive: true, force: true });
        state = { status: 'logged_out', qr: null, phone: null, name: null };
      } else {
        log('↻ انقطع الاتصال، عم نعيد المحاولة...', code ?? '');
        state = { ...state, status: 'connecting', qr: null };
      }
      heartbeat();
      setTimeout(() => connect().catch(e => log('❌', e.message)), 3_000);
    }
  });
}

// ── إرسال الرسائل ──
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
let busy = false;
let lastReason = '';

async function work() {
  if (busy || state.status !== 'connected' || !sock) return;
  busy = true;
  try {
    const { job, reason } = await api('GET', 'next');
    if (!job) {
      if (reason && reason !== lastReason) { log('⏸', reason); lastReason = reason; }
      return;
    }
    lastReason = '';

    try {
      const [res] = await sock.onWhatsApp(`${job.phone}@s.whatsapp.net`);
      if (!res?.exists) {
        await api('POST', 'report', { id: job.id, ok: false, error: 'الرقم ما عليه واتساب' });
        log(`✗ ${job.phone}: الرقم ما عليه واتساب`);
        return;
      }

      // كتابة طبيعية قبل الإرسال (مدة حسب طول الرسالة)
      await sock.presenceSubscribe(res.jid).catch(() => {});
      await sock.sendPresenceUpdate('composing', res.jid).catch(() => {});
      await sleep(Math.min(8_000, 1_500 + job.text.length * 35));
      await sock.sendPresenceUpdate('paused', res.jid).catch(() => {});

      await sock.sendMessage(res.jid, { text: job.text });
      await api('POST', 'report', { id: job.id, ok: true });
      log(`✓ انبعتت لـ ${job.phone}`);
    } catch (e) {
      await api('POST', 'report', { id: job.id, ok: false, error: String(e.message || e).slice(0, 250) }).catch(() => {});
      log(`✗ ${job.phone}:`, e.message);
    }
  } catch (e) {
    log('⚠️', e.message);
    if (e.fatal) process.exit(1);
  } finally {
    busy = false;
  }
}

log(`برنامج ربط واتساب — مكانة (${VERSION})`);
log(`السيرفر: ${config.server}`);
log('خلّي هالنافذة مفتوحة (بتقدر تصغّرها). إذا سكّرتها بيوقف الإرسال التلقائي.');

setInterval(heartbeat, HEARTBEAT_MS);
setInterval(work, POLL_MS);
heartbeat();
connect().catch(e => { log('❌', e.message); process.exit(1); });
